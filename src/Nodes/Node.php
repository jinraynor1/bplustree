<?php


namespace Jinraynor1\BplusTree\Nodes;


use Jinraynor1\BplusTree\Entries\Entry;
use Jinraynor1\BplusTree\Exceptions\IndexError;
use Jinraynor1\BplusTree\Exceptions\ValueError;
use Jinraynor1\BplusTree\Helpers\Bisect;
use Jinraynor1\BplusTree\Primitives\Integer;
use Jinraynor1\BplusTree\TreeConf;

abstract class Node
{
    protected $node_type_int;
    public $max_children = 0;
    public $min_children = 0;
    protected $entry_class = null;
    /**
     * @var TreeConf
     */
    protected $treeConf;
    /**
     * @var array
     */
    public $entries;
    /**
     * @var mixed|null
     */
    public $page;
    /**
     * @var mixed|null
     */
    public  $parent;
    /**
     * @var mixed|null
     */
    public $nextPage;

    public function __construct(TreeConf $treeConf, $data = null, $page = null, $parent = null, $nextPage = null)
    {
        $this->treeConf = $treeConf;
        $this->entries = array();
        $this->page = $page;
        $this->parent = $parent;
        $this->nextPage = $nextPage;

        if ($data) {
            $this->load($data);
        }


    }


    /**
     * @param array $args
     * @return static
     */
    public static function createFromArgs(array $args)
    {
        $defaults = array(
            'data'=>null,
            'page'=> null,
            'parent'=> null,
            'nextPage'=> null,
        );
        $args = array_merge($defaults, $args);


        return new static($args['treeConf'],$args['data'],$args['page'],$args['parent'],$args['nextPage']);

    }

    public function load($data)
    {
        if (!(strlen($data) == $this->treeConf->getPageSize())) {
            throw new \RuntimeException("Invalid length for data");
        }

        $end_used_page_length = NODE_TYPE_BYTES + USED_PAGE_LENGTH_BYTES;
        $used_page_length = Integer::fromBytes(
            substr($data, NODE_TYPE_BYTES , $end_used_page_length-NODE_TYPE_BYTES), ENDIAN
        );

        $end_header = $end_used_page_length + PAGE_REFERENCE_BYTES;

        $this->nextPage = Integer::fromBytes(
            substr($data,  $end_used_page_length,$end_header-$end_used_page_length), ENDIAN
        );

        if ($this->nextPage == 0) {
            $this->nextPage = null;
        }
        if (is_null($this->entry_class)) {
            # For Nodes that cannot hold Entries
            return;
        }
        $entryClass = $this->entry_class;
        $entryObj = (new $this->entry_class($this->treeConf));

        if (isset($entryObj->length)) {
            # For Nodes that can hold multiple sized Entries
            $entry_length = $entryObj->length;
        } else {
            # For Nodes that can hold a single variable sized Entry
            $entry_length = $used_page_length - $end_header;
        }

        if($entry_length > $used_page_length) return;
        $range = range($end_header, $used_page_length, $entry_length);
        array_pop($range);
        foreach ($range as $start_offset) {
            $entry_data = substr($data, $start_offset, $entry_length);
            $entry = $entryClass::createFromData($this->treeConf, $entry_data);
            $entry->load($entry_data); # unlike py version, manually load the data
            $this->entries[] = $entry;
        }

    }

    public function dump()
    {
        $data = "";
        foreach ($this->entries as $record) {
            $data .= $record->dump();
        }

        # used_page_length = len(header) + len(data), but the header is
        # generated later
        $used_page_length = strlen($data) + 4 + PAGE_REFERENCE_BYTES;
        if (!(0 < $used_page_length && $used_page_length <= $this->treeConf->getPageSize())) {
            throw new \Exception("Invalid page length");
        }
        if (!(strlen($data) <= $this->getMaxPayload())) {
            throw new \Exception("max data length");
        }

        if (is_null($this->nextPage)) {
            $next_page = 0;
        } else {
            $next_page = $this->nextPage;
        }
        $header = (
            pack("c",$this->node_type_int ).
            pack("va1",$used_page_length,"").
            pack("V",$next_page)
        );

        $data = $header . $data;

        $padding = $this->treeConf->getPageSize() - $used_page_length;

        if (!($padding >= 0)) {
            throw new \Exception("Invalid padding");
        }
        $data = str_pad($data ,$padding + $used_page_length,"\x00");

        if (!(strlen($data) == $this->treeConf->getPageSize())) {
            throw new \Exception("Invalid data length");
        }

        return $data;

    }

    public function getMaxPayload()
    {
        return (
            $this->treeConf->getPageSize() - 4 - PAGE_REFERENCE_BYTES
        );
    }

    public function canAddEntry()
    {
        return $this->numChildren() < $this->max_children;
    }

    public function canDeleteEntry()
    {
        return $this->numChildren() > $this->min_children;
    }

    public function smallestKey()
    {
        return $this->smallestEntry()->getKey();
    }

    public function smallestEntry()
    {
        return $this->entries[0];
    }

    public function biggestKey()
    {
        return $this->biggestEntry()->getKey();
    }

    public function biggestEntry()
    {
        if(!isset($this->entries[count($this->entries)-1]))
            throw new IndexError("index does not exist");

        return $this->entries[count($this->entries)-1];
    }


    public function numChildren()
    {
        return count($this->entries);
    }

    public function popSmallest()
    {
        if(empty($this->entries)){
            throw new \Exception("index error");
        }
        return array_shift($this->entries);
    }

    public function insertEntry(Entry $entry)
    {
        $pos = Bisect::right($this->entries, $entry);
        array_splice($this->entries, $pos,0, array($entry));
    }

    public function insertEntryAtTheEnd(Entry $entry)
    {
        /*Insert an entry at the end of the entry list.

        This is an optimized version of `insert_entry` when it is known that
        the key to insert is bigger than any other entries.
        */
        $this->entries[] = $entry;
    }

    public function removeEntry($key)
    {
        array_splice($this->entries, $this->findEntryIndex($key), 1);
    }

    public function getEntry($key)
    {
        return $this->entries[$this->findEntryIndex($key)];
    }

    public function findEntryIndex($key)
    {
        $entryClass = $this->entry_class;
        $entry = new $entryClass($this->treeConf,
            $key  # Hack to compare and order
        );

        $i = Bisect::left($this->entries, $entry);
        if ($i != count($this->entries) && $this->entries[$i]->equals( $entry)) {
            return $i;
        }
        throw new ValueError(sprintf("No entry for key %s", $key));

    }

    public function splitEntries()
    {
        /*Split the entries in half.

        Keep the lower part in the node and return the upper one.
        */
        $len_entries = count($this->entries);
        $offset = floor($len_entries / 2);
        $rv = array_slice($this->entries, $offset);
        $this->entries = array_slice($this->entries, 0, $offset);

        assert((count($this->entries) + count($rv) == $len_entries));
        return $rv;

    }

    /**
     * @param TreeConf $treeConf
     * @param $data
     * @param $page
     * @return FreelistNode|InternalNode|LeafNode|LonelyRootNode|OverflowNode|RootNode
     * @throws \Exception
     */
    public static function fromPageData(TreeConf $treeConf, $data, $page = null)
    {
        $node_type_byte = substr($data, 0 , NODE_TYPE_BYTES);
        $node_type_int = Integer::fromBytes($node_type_byte, ENDIAN);
        if ($node_type_int == 1)
            return new LonelyRootNode($treeConf, $data, $page);
        elseif ($node_type_int == 2)
            return new RootNode($treeConf, $data, $page);
        elseif ($node_type_int == 3)
            return new InternalNode($treeConf, $data, $page);
        elseif ($node_type_int == 4)
            return new LeafNode($treeConf, $data, $page);
        elseif ($node_type_int == 5)
            return new OverflowNode($treeConf, $data, $page);
        elseif ($node_type_int == 6)
            return new FreelistNode($treeConf, $data, $page);
        else
            throw new \Exception(sprintf('No Node with type %s exists', $node_type_int));
        
    }

    public function represents()
    {
        $r = new \ReflectionClass($this);
        return sprintf("<%s: page=%s entries=%s>",$r->getShortName(),$this->page, count($this->entries));
    }

    public function equals(Node $other)
    {
        $otherReflect = new \ReflectionClass($other);
        $meReflect = new \ReflectionClass($this);
        return $otherReflect->getShortName() == $meReflect->getShortName()
            && $other->page == $this->page
            && $other->entries == $this->entries;
    }


}