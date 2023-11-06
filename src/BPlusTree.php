<?php


namespace Jinraynor1\BplusTree;


use Jinraynor1\BplusTree\BPlusTree\EntryIterator;
use Jinraynor1\BplusTree\BPlusTree\ItemsIterator;
use Jinraynor1\BplusTree\BPlusTree\ValuesIterator;
use Jinraynor1\BplusTree\Entries\OpaqueData;
use Jinraynor1\BplusTree\Entries\Record;
use Jinraynor1\BplusTree\Entries\Reference;
use Jinraynor1\BplusTree\Exceptions\IndexError;
use Jinraynor1\BplusTree\Exceptions\KeyError;
use Jinraynor1\BplusTree\Exceptions\ValueError;
use Jinraynor1\BplusTree\Helpers\PairWiseIterator;
use Jinraynor1\BplusTree\Helpers\Slice;
use Jinraynor1\BplusTree\Helpers\SliceIterator;
use Jinraynor1\BplusTree\Memory\FileMemory;
use Jinraynor1\BplusTree\Nodes\InternalNode;
use Jinraynor1\BplusTree\Nodes\LeafNode;
use Jinraynor1\BplusTree\Nodes\LonelyRootNode;
use Jinraynor1\BplusTree\Nodes\Node;
use Jinraynor1\BplusTree\Nodes\OverflowNode;
use Jinraynor1\BplusTree\Nodes\RootNode;
use Jinraynor1\BplusTree\Serializer\IntegerSerializer;
use Jinraynor1\BplusTree\Serializer\SerializerInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class BPlusTree
{
    private $filename;
    /**
     * @var int
     */
    private $pageSize;
    /**
     * @var int
     */
    private $order;
    /**
     * @var int
     */
    private $keySize;
    /**
     * @var int
     */
    private $valueSize;
    /**
     * @var int
     */
    private $cacheSize;
    /**
     * @var SerializerInterface|null
     */
    private $serializer;
    /**
     * @var TreeConf
     */
    public $treeConf;
    /**
     * @var FileMemory
     */
    public $mem;
    /**
     * @var mixed
     */
    private $root_node_page;
    /**
     * @var bool
     */
    private $is_open;
    /**
     * @var LoggerInterface
     */
    private $logger;
    private $record;
    private $root_node;

    const DEFAULT_PAGE_SIZE = 4096;
    const DEFAULT_ORDER = 100;
    const DEFAULT_KEY_SIZE = 8;
    const DEFAULT_VALUE_SIZE = 32;
    const DEFAULT_CACHE_SIZE = 64;

    public function __construct($filename, $pageSize = self::DEFAULT_PAGE_SIZE, $order = self::DEFAULT_ORDER,
                                $keySize = self::DEFAULT_KEY_SIZE, $valueSize = self::DEFAULT_VALUE_SIZE,
                                $cacheSize = self::DEFAULT_CACHE_SIZE,
                                SerializerInterface $serializer = null)
    {

        $this->logger = new NullLogger();

        $this->filename = $filename;
        $this->pageSize = $pageSize;
        $this->order = $order;
        $this->keySize = $keySize;
        $this->valueSize = $valueSize;
        $this->cacheSize = $cacheSize;

        if (!$serializer) $serializer = new IntegerSerializer();
        $this->serializer = $serializer;

        $this->treeConf = new TreeConf(
            $pageSize, $order, $keySize, $valueSize,
            $serializer
        );

        #$this->createPartials();
        $this->mem = new FileMemory($filename, $this->treeConf, $cacheSize);

        try {
            $metadata = $this->mem->getMetadata();
            list($this->root_node_page, $this->treeConf) = $metadata;

        } catch (ValueError $e) {
            $this->InitializeEmptyTree();
        }

        $this->is_open = true;

    }

    /**
     * @param array $args
     * @return static
     */
    public static function createFromArgs(array $args)
    {
        $defaults = array(
            'filename' => null,
            'pageSize' => self::DEFAULT_PAGE_SIZE,
            'order' => self::DEFAULT_ORDER,
            'keySize' => self::DEFAULT_KEY_SIZE,
            'valueSize' => self::DEFAULT_VALUE_SIZE,
            'cacheSize' => self::DEFAULT_CACHE_SIZE,
            'serializer' => null
        );
        $args = array_merge($defaults, $args);


        return new static($args['filename'], $args['pageSize'], $args['order'],
            $args['keySize'], $args['valueSize'], $args['cacheSize'], $args['serializer']);

    }


    public function __destruct()
    {
        $this->close();
    }

    public function close()
    {
        if (!$this->is_open) {
            $this->logger->info("Tree is already closed");
            return;
        }
        $that = $this;
        $this->mem->writeTransaction(function () use ($that) {
            $that->mem->close();
            $that->is_open = false;
        });


    }

    public function checkpoint()
    {
        $that = $this;
        $this->mem->writeTransaction(function () use ($that) {
            $that->mem->performCheckpoint(true);
        });
    }

    /**
     * Insert a value in the tree.
     * @param $key : The key at which the value will be recorded, must be of the same type used by the Serializer
     * @param $value : The value to record in bytes
     * @param false $replace : If True, already existing value will be overridden, otherwise a ValueError is raised
     */
    public function insert($key, $value, $replace = false)
    {
        if (!is_string($value))
            throw new ValueError('Values must be string');

        $that = $this;
        $this->mem->writeTransaction(function () use ($that, $key, $value, $replace) {
            $node = $that->searchInTree($key, $that->rootNode());

            # Check if a record with the key already exists
            try {
                $existing_record = $node->getEntry($key);
                $noError = true;
            } catch (ValueError $e) {
                $noError = false;
                //pass
            }
            if($noError){
                if (!$replace)
                    throw new  ValueError(sprintf('Key %s already exists', $key));

                if ($existing_record->overflowPage)
                    $that->deleteOverflow($existing_record->overflowPage);

                if (strlen($value) <= $that->treeConf->getValueSize()) {
                    $existing_record->value = $value;
                    $existing_record->overflowPage = null;
                } else {
                    $existing_record->value = null;
                    $existing_record->overflowPage = $that->createOverflow(
                        $value
                    );
                }
                $that->mem->setNode($node);
                return;
            }


            if (strlen($value) <= $this->treeConf->getValueSize()) {
                $record = new Record($this->treeConf, $key, $value);
            } else {
                # Record values exceeding the max value_size must be placed
                # into overflow pages
                $first_overflow_page = $this->createOverflow($value);
                $record = new Record($this->treeConf, $key, null,
                    $first_overflow_page);
            }
            if ($node->canAddEntry()) {
                $node->insertEntry($record);
                $this->mem->setNode($node);
            } else {
                $node->insertEntry($record);
                $this->splitLeaf($node);
            }

        });
    }

    /**
     * Insert many elements in the tree at once.
     *
     * The iterable object must yield tuples (key, value) in ascending order.
     * All keys to insert must be bigger than all keys currently in the tree.
     * All inserts happen in a single transaction. This is way faster than
     * manually inserting in a loop.
     * @param $list
     */
    public function batchInsert($list)
    {
        $node = null;
        $that = $this;
        $this->mem->writeTransaction(function () use ($that, $node, $list) {

            foreach ($list as $key => $value) {
                if (is_null($node)) {
                    $node = $this->searchInTree($key, $that->rootNode());
                }

                try {
                    $biggest_entry = $node->biggestEntry();
                } catch (IndexError $e) {
                    $biggest_entry = null;
                }
                if ($biggest_entry and $key <= $biggest_entry->key)
                    throw new  ValueError('Keys to batch insert must be sorted and ' .
                        'bigger than keys currently in the tree');


                if (strlen($value) <= $that->treeConf->getValueSize()) {
                    $record = new Record($that->treeConf, $key, $value);
                } else {
                    # Record values exceeding the max value_size must be placed
                    # into overflow pages
                    $first_overflow_page = $that->createOverflow($value);
                    $record = new Record($that->treeConf, $key, null, null,
                        $first_overflow_page);
                }
                if ($node->canAddEntry()) {
                    $node->insertEntryAtTheEnd($record);
                } else {
                    $node->insertEntryAtTheEnd($record);
                    $this->splitLeaf($node);
                    $node = null;
                }
            }

            if (!is_null($node))
                $this->mem->setNode($node);

        });
    }

    public function get($key, $default = null)
    {
        $that = $this;
        $rv = null;
        $this->mem->readTransaction(function () use ($that, $key, $default, &$rv) {
            $node = $that->searchInTree($key, $this->rootNode());
            try {
                $record = $node->getEntry($key);
                $rv = $that->getValueFromRecord($record);
                assert(is_string($rv));

            } catch (ValueError $e) {
                $rv = $default;
            }
        });

        return $rv;

    }

    public function contains($item)
    {
        $that = $this;
        $rv = null;
        $this->mem->readTransaction(function () use ($that, $item, &$rv) {
            $o = "";
            if ($that->get($item, $o) == $o) {
                $rv = false;
            } else {
                $rv = true;
            }
        });

        return $rv;
    }

    public function setItem($key, $value)
    {
        $this->insert($key, $value, true);
    }

    public function getItem($item)
    {
        $that = $this;
        $rv = is_array($item) ? array() : null;

        $this->mem->readTransaction(function () use ($that, $item, &$rv) {

            if (is_array($item)) {
                # Returning a dict is the most sensible thing to do
                # as a method cannot return a sometimes a generator
                # and sometimes a normal value
                foreach ($this->iterSlice($item) as $record) {
                    $rv[$record->key] = $this->getValueFromRecord($record);
                }

            } else {
                $rv = $that->get($item);
                if (is_null($rv))
                    throw new KeyError($item);

            }

        });
        return $rv;
    }

    public function length()
    {
        $that = $this;
        $rv = 0;
        $this->mem->readTransaction(function () use ($that, &$rv) {
            $node = $that->leftRecordNode();

            while (true) {
                $rv += count($node->entries);
                if (!$node->nextPage){
                    break;
                }
                $node = $this->mem->getNode($node->nextPage);
            }
        });

        return $rv;
    }

    public function lengthHint()
    {
        $that = $this;
        $rv = 0;
        $this->mem->readTransaction(function () use ($that, &$rv) {
            $node = $that->rootNode();

            if (is_a($node, LonelyRootNode::class)) {
                # Assume that the lonely root node is half full
                $rv = floor($node->max_children / 2);
                return;
            }

            # Assume that there are no holes in pages
            $last_page = $this->mem->last_page;
            # Assume that 70% of nodes in a tree carry values
            $num_leaf_nodes = intval($last_page * 0.70);
            # Assume that every leaf node is half full
            $num_records_per_leaf_node = intval(
                ($node->max_children + $node->min_children) / 2
            );
            $rv = $num_leaf_nodes * $num_records_per_leaf_node;

        });

        return $rv;
    }

    public function iterator( $slice = null)
    {
        if(is_null($slice))
            $slice = Slice::createEmpty();

        return new ItemsIterator($this,$slice);
    }

    public function keys( $slice = null)
    {
        if(is_null($slice))
            $slice = Slice::createEmpty();

        return new ValuesIterator($this,$slice);
    }

    public function items($slice = null)
    {
        if(is_null($slice))
            $slice = Slice::createEmpty();

        return new ItemsIterator($this,$slice);
    }

    public function values( $slice = null)
    {
        if(is_null($slice))
            $slice = Slice::createEmpty();

        return new ValuesIterator($this,$slice);
    }

    public function bool()
    {
        $that = $this;
        $rv = false;
        $this->mem->readTransaction(function () use ($that, &$rv) {
            $that->iterator(function () {
                $rv = true;
            });

        });

        return $rv;
    }

    public function represents()
    {
        return sprintf("BPlusTree: %s %s", $this->filename, $this->treeConf);
    }

    private function initializeEmptyTree()
    {
        $this->root_node_page = $this->mem->nextAvailablePage();
        $that = $this;
        $this->mem->writeTransaction(function () use ($that) {
            $that->mem->setNode(new LonelyRootNode($this->treeConf, null, $this->root_node_page));
        });

        $this->mem->setMetadata($this->root_node_page, $this->treeConf);
    }

    public function rootNode()
    {

        $root_node = $this->mem->getNode($this->root_node_page);
        assert(is_a($root_node, LonelyRootNode::class) || is_a($root_node, RootNode::class));
        return $root_node;

    }

    public function leftRecordNode()
    {
        $node = $this->rootNode();
        while (!is_a($node, LonelyRootNode::class) &&  !is_a($node, LeafNode::class)) {
            $node = $this->mem->getNode($node->smallestEntry()->before);
        }
        return $node;
    }

    public function iterSlice(Slice $slice)
    {
        return new EntryIterator($this,$slice);
    }

    public function searchInTree($key, $node)
    {
        if (is_a($node, LonelyRootNode::class) || is_a($node, LeafNode::class)) {
            return $node;
        }

        $page = null;

        if ($key < $node->smallestKey())
            $page = $node->smallestEntry()->before;
        elseif ($node->biggestKey() <= $key)
            $page = $node->biggestEntry()->after;
        else {
            $iterator = new PairWiseIterator($node->entries);
            foreach ($iterator as $chunk) {
                $ref_a = $chunk[0];
                $ref_b = $chunk[1];
                if ($ref_a->key <= $key && $key < $ref_b->key) {
                    $page = $ref_a->after;
                    break;
                }
            }
        }
        assert(!is_null($page));
        $child_node = $this->mem->getNode($page);
        $child_node->parent = $node;
        return $this->searchInTree($key, $child_node);


    }

    /**
     * Split a leaf Node to allow the tree to grow.
     */
    public function splitLeaf(Node $old_node)
    {
        $parent = $old_node->parent;

        $new_node = new LeafNode($this->treeConf, null, $this->mem->nextAvailablePage(), null,
            $old_node->nextPage);

        $new_entries = $old_node->splitEntries();
        $new_node->entries = $new_entries;
        $ref = new Reference($this->treeConf,$new_node->smallestKey(),
            $old_node->page, $new_node->page);

        if (is_a($old_node, LonelyRootNode::class)) {
            # Convert the LonelyRoot into a Leaf
            $old_node = $old_node->convertToLeaf();
            $this->createNewRoot($ref);
        } elseif ($parent->canAddEntry()) {
            $parent->insertEntry($ref);
            $this->mem->setNode($parent);
        } else {
            $parent->insertEntry($ref);
            $this->splitParent($parent);
        }

        $old_node->nextPage = $new_node->page;
        $this->mem->setNode($old_node);
        $this->mem->setNode($new_node);


    }

    public function splitParent(Node $old_node)
    {
        $parent = $old_node->parent;
        $new_node = new InternalNode($this->treeConf, null, $this->mem->nextAvailablePage());
        $new_entries = $old_node->splitEntries();
        $new_node->entries = $new_entries;

        $ref = $new_node->popSmallest();
        $ref->before = $old_node->page;
        $ref->after = $new_node->page;

        if (is_a($old_node, RootNode::class)) {
            # Convert the Root into an Internal
            $old_node = $old_node->convertToInternal();
            $this->createNewRoot($ref);
        } elseif ($parent->canAddEntry()) {
            $parent->insertEntry($ref);
            $this->mem->setNode($parent);
        } else {
            $parent->insertEntry($ref);
            $this->splitParent($parent);
        }

        $this->mem->setNode($old_node);
        $this->mem->setNode($new_node);
    }

    public function createNewRoot(Reference $reference)
    {
        $new_root = new RootNode($this->treeConf, null, $this->mem->nextAvailablePage());
        $new_root->insertEntry($reference);
        $this->root_node_page = $new_root->page;
        $this->mem->setMetadata($this->root_node_page, $this->treeConf);
        $this->mem->setNode($new_root);
    }

    public function createOverflow($value)
    {
        $first_overflow_page = $this->mem->nextAvailablePage();
        $next_overflow_page = $first_overflow_page;

        $iterator = new SliceIterator($value, (new OverflowNode($this->treeConf))->getMaxPayload());
        foreach ($iterator as $sliceValue => $isLast) {
            $current_overflow_page = $next_overflow_page;

            if ($isLast)
                $next_overflow_page = null;
            else
                $next_overflow_page = $this->mem->nextAvailablePage();

            $overflow_node = new OverflowNode($this->treeConf, null,
                $current_overflow_page, $next_overflow_page
            );
            $overflow_node->insertEntryAtTheEnd(new OpaqueData($this->treeConf, $sliceValue));
            $this->mem->setNode($overflow_node);
        }

        return $first_overflow_page;
    }

    public function traverseOverflow($first_overflow_page, $callback)
    {
        $next_overflow_page = $first_overflow_page;
        while (true) {
            $overflow_node = $this->mem->getNode($next_overflow_page);
            call_user_func($callback, $overflow_node);

            $next_overflow_page = $overflow_node->nextPage;
            if (is_null($next_overflow_page)) {
                break;
            }
        }
    }

    public function readFromOverflow($first_overflow_page)
    {
        $rv = "";
        $this->traverseOverflow($first_overflow_page, function ($overflow_node) use (&$rv) {
            $rv .= $overflow_node->smallestEntry()->data;
        });
        return $rv;
    }

    public function deleteOverflow($first_overflow_page)
    {
        $this->traverseOverflow($first_overflow_page, function ($overflow_node) {
            $this->mem->delNode($overflow_node);
        });
    }

    public function getValueFromRecord(Record $record)
    {
        if (!is_null($record->getValue()))
            return $record->getValue();

        return $this->readFromOverflow($record->getOverflowPage());
    }

}