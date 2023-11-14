<?php


namespace Jinraynor1\BplusTree\Memory;


use ErrorException;
use Jinraynor1\BplusTree\Exceptions\ReachedEndOfFile;
use Jinraynor1\BplusTree\Exceptions\ValueError;
use Jinraynor1\BplusTree\Nodes\FreeListNode;
use Jinraynor1\BplusTree\Nodes\Node;
use Jinraynor1\BplusTree\Primitives\Byte;
use Jinraynor1\BplusTree\Primitives\Integer;
use Jinraynor1\BplusTree\TreeConf;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class FileMemory
{

    private $filename;
    /**
     * @var TreeConf
     */
    private $treeConf;
    /**
     * @var int
     */
    private $cacheSize;
    /**
     * @var CacheInterface
     */
    public $cache;
    /**
     * @var RWLock
     */
    public $lock;
    /**
     * @var mixed
     */
    private $fd;
    /**
     * @var mixed
     */
    private $dir_fd;
    /**
     * @var WAL
     */
    public $wal;
    /**
     * @var int
     */
    private $freelist_start_page;
    /**
     * @var int
     */
    private $root_node_page;
    /**
     * @var LoggerInterface
     */
    private $logger;
    public $last_page;

    public function __construct($filename, TreeConf $treeConf, $cacheSize = 512)
    {
        $this->logger = new NullLogger();

        $this->filename = $filename;
        $this->treeConf = $treeConf;
        $this->lock = new RWLock($filename);
        if ($cacheSize == 0) {
            $this->cache = new FakeCache();
        } else {
            $this->cache = new LRUCache(512);
        }

        list($this->fd, $this->dir_fd) = File::open_file_in_dir($filename);

        $this->wal = new WAL($filename, $treeConf->getPageSize());

        if ($this->wal->needsRecovery) {
            $this->performCheckpoint(true);
        }

        # Get the next available page
        fseek($this->fd, 0, SEEK_END);
        $last_byte = ftell($this->fd);
        $this->last_page = intval($last_byte / $this->treeConf->getPageSize());
        $this->freelist_start_page = 0;

        # Todo: Remove this, it should only be in Tree
        $this->root_node_page = 0;

    }

    /**
     * Get a node from storage.
     *
     * The cache is not there to prevent hitting the disk, the OS is already
     * very good at it. It is there to avoid paying the price of deserializing
     * the data to create the Node object and its entry. This is a very
     * expensive operation in Python.
     *
     * Since we have at most a single writer we can write to cache on
     * `set_node` if we invalidate the cache when a transaction is rolled back.
     * @param $page
     *
     * @return Node
     * @throws \Exception
     */
    public function getNode($page)
    {
        $node = $this->cache->get($page);
        if (!is_null($node))
            return $node;

        $data = $this->wal->getPage($page);
        if (!$data)
            $data = $this->readPage($page);

        $node = Node::fromPageData($this->treeConf, $data, $page);
        $this->cache->put($node->page, $node);
        return $node;
    }

    public function setNode(Node $node)
    {
        $this->wal->setPage($node->page, $node->dump());
        $this->cache->put($node->page, $node);
    }

    public function delNode(node $node)
    {
        $this->insertInFreelist($node->page);
    }

    public function delPage($page)
    {
        $this->insertInFreelist($page);
    }

    public function readTransaction($callback)
    {
        $this->lock->getReaderLock()->acquire();
        call_user_func($callback);
        $this->lock->getReaderLock()->release();

    }

    public function writeTransaction($callback)
    {
        $this->lock->getWriterLock()->acquire();
        try {
            call_user_func($callback);

            $this->wal->commit();
        } catch (ErrorException $e) {
            # When an error happens in the middle of a write
            # transaction we must roll it back and clear the cache
            # because the writer may have partially modified the Nodes
            $this->wal->rollback();
            $this->cache->clear();
        }

        $this->lock->getWriterLock()->release();



    }

    public function nextAvailablePage()
    {
        $last_freelist_page = $this->popFromFreelist();
        if (!is_null($last_freelist_page))
            return $last_freelist_page;

        $this->last_page += 1;
        return $this->last_page;

    }

    public function traverseFreeList()
    {
        if ($this->freelist_start_page == 0)
            return array(null, null);

        $second_to_last_node = null;
        $last_node = $this->getNode($this->freelist_start_page);

        while (!is_null($last_node->nextPage)) {
            $second_to_last_node = $last_node;
            $last_node = $this->getNode($second_to_last_node->nextPage);
        }

        return array($second_to_last_node, $last_node);
    }


    /**
     * Insert a page at the end of the freelist.
     */
    public function insertInFreelist($page)
    {

        list($_, $last_node) = $this->traverseFreeList();

        $this->setNode(new FreelistNode($this->treeConf, null, $page, null));

        if (is_null($last_node)) {
            # Write in metadata that the freelist got a new starting point
            $this->freelist_start_page = $page;
            $this->setMetadata(null, null);
        } else {
            $last_node->nextPage = $page;
            $this->setNode($last_node);
        }

    }

    /**
     * Remove the last page from the freelist and return its page.
     */
    public function popFromFreelist()
    {
        list($second_to_last_node, $last_node) = $this->traverseFreeList();

        if (is_null($last_node)) {
            # Freelist is completely empty, nothing to pop
            return null;
        }

        if (is_null($second_to_last_node)) {
            # Write in metadata that the freelist is empty
            $this->freelist_start_page = 0;
            $this->setMetadata(null, null);
        } else {
            $second_to_last_node->nextPage = null;
            $this->setNode($second_to_last_node);
        }
        return $last_node->page;
    }

    public function getMetadata()
    {
        try {
            $data = $this->readPage(0);
        } catch (ReachedEndOfFile $e) {
            throw new ValueError('Metadata not set yet');
        }

        $end_root_node_page = PAGE_REFERENCE_BYTES;
        $root_node_page = Integer::fromBytes(
            py_slice($data, "0:$end_root_node_page"), ENDIAN
        );
        $end_page_size = $end_root_node_page + OTHERS_BYTES;
        $page_size = Integer::fromBytes(
            py_slice($data, "$end_root_node_page:$end_page_size"), ENDIAN
        );

        $end_order = $end_page_size + OTHERS_BYTES;
        $order = Integer::fromBytes(
            py_slice($data, "$end_page_size:$end_order"), ENDIAN
        );
        $end_key_size = $end_order + OTHERS_BYTES;
        $key_size = Integer::fromBytes(
            py_slice($data, "$end_order:$end_key_size"), ENDIAN
        );
        $end_value_size = $end_key_size + OTHERS_BYTES;
        $value_size = Integer::fromBytes(
            py_slice($data, "$end_key_size:$end_value_size"), ENDIAN
        );
        $end_freelist_start_page = $end_value_size + PAGE_REFERENCE_BYTES;
        $this->freelist_start_page = Integer::fromBytes(
            py_slice($data, "$end_value_size:$end_freelist_start_page"), ENDIAN
        );
        $this->treeConf = new TreeConf(
            $page_size, $order, $key_size, $value_size, $this->treeConf->getSerializer()
        );
        $this->root_node_page = $root_node_page;
        return array($root_node_page, $this->treeConf);

    }

    public function setMetadata($root_node_page = null, $treeConf = null)
    {
        if (is_null($root_node_page))
            $root_node_page = $this->root_node_page;

        if (is_null($treeConf))
            $treeConf = $this->treeConf;

        $length = 2 * PAGE_REFERENCE_BYTES + 4 * OTHERS_BYTES;
        $data = (
            pack("V",$root_node_page) .
            pack("V",$treeConf->getPageSize()) .
            pack("V",$treeConf->getOrder()) .
            pack("V",$treeConf->getKeySize()) .
            pack("V",$treeConf->getValueSize()) .
            pack("V",$this->freelist_start_page ) .
            pack("a". ($treeConf->getPageSize() - $length),"")
        );

        $this->writePageInTree(0, $data, true);

        $this->treeConf = $treeConf;
        $this->root_node_page = $root_node_page;
    }

    public function close()
    {
        $this->performCheckpoint();
        fclose($this->fd);
        if (!is_null($this->dir_fd))
            fclose($this->dir_fd);

    }

    public function performCheckpoint($reopen_wal = false)
    {
        $this->logger->info(sprintf('Performing checkpoint of %s', $this->filename));
        $that = $this;
        $this->wal->checkpoint(function ($page, $pageData) use ($that, $reopen_wal) {
            $that->writePageInTree($page, $pageData, false);

        });
        File::fsync_file_and_dir($this->fd, $this->dir_fd);
        if ($reopen_wal)
            $this->wal = new WAL($that->filename, $that->treeConf->getPageSize());

    }

    public function readPage($page)
    {
        $start = $page * $this->treeConf->getPageSize();
        $stop = $start + $this->treeConf->getPageSize();
        assert($stop - $start == $this->treeConf->getPageSize());
        return File::read_from_file($this->fd, $start, $stop);

    }

    /**
     * Write a page of data in the tree file itself.
     *
     * To be used during checkpoints and other non-standard uses.
     * @param $page
     * @param $data
     * @param $fsync
     */
    public function writePageInTree($page, $data, $fsync)
    {
        assert(strlen($data) == $this->treeConf->getPageSize());
        fseek($this->fd, $page * $this->treeConf->getPageSize());
        File::write_to_file($this->fd, $this->dir_fd, $data, $fsync);
    }

    public function represents()
    {
        return sprintf('<FileMemory: %s>', $this->filename);

    }


}