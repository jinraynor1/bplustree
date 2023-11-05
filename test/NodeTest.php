<?php


use Jinraynor1\BplusTree\Entries\OpaqueData;
use Jinraynor1\BplusTree\Entries\Record;
use Jinraynor1\BplusTree\Entries\Reference;
use Jinraynor1\BplusTree\Nodes\FreeListNode;
use Jinraynor1\BplusTree\Nodes\OverflowNode;
use Jinraynor1\BplusTree\Primitives\Byte;
use Jinraynor1\BplusTree\Primitives\Integer;
use Jinraynor1\BplusTree\Serializer\IntegerSerializer;
use Jinraynor1\BplusTree\TreeConf;
use PHPUnit\Framework\TestCase;
use Jinraynor1\BplusTree\Nodes\LonelyRootNode;
use Jinraynor1\BplusTree\Nodes\RootNode;
use Jinraynor1\BplusTree\Nodes\InternalNode;
use Jinraynor1\BplusTree\Nodes\LeafNode;
use Jinraynor1\BplusTree\Nodes\Node;

class NodeTest extends TestCase
{
    /**
     * @var TreeConf
     */
    private static $treeConf;

    public function setUp()
    {
        self::$treeConf = new TreeConf(4096, 7, 16, 16, new IntegerSerializer());
    }

    public function dataProviderNodeLimitChildren()
    {
        return array(
            array(LonelyRootNode::class, 7, 0, 6),
            array(LonelyRootNode::class, 100, 0, 99),
            array(RootNode::class, 7, 2, 7),
            array(RootNode::class, 100, 2, 100),
            array(InternalNode::class, 7, 4, 7),
            array(InternalNode::class, 100, 50, 100),
            array(LeafNode::class, 7, 3, 6),
            array(LeafNode::class, 100, 49, 99),
        );
    }

    /**
     * @test
     * @dataProvider dataProviderNodeLimitChildren
     * @param $klass
     * @param $order
     * @param $min_children
     * @param $max_children
     */
    public function nodeLimitChilden($klass, $order, $min_children, $max_children)
    {
        $node = new $klass(new TreeConf(4096, $order, 16, 16, new IntegerSerializer()));
        $this->assertEquals($min_children, $node->min_children);
        $this->assertEquals($max_children, $node->max_children);

    }

    public function dataProviderEmptyNodeSerialization()
    {
        return array(
            array(LeafNode::class),
            array(InternalNode::class),
            array(RootNode::class),
            array(LonelyRootNode::class)
        );
    }

    /**
     * @test
     * @dataProvider dataProviderEmptyNodeSerialization
     * @param $klass
     * @throws Exception
     */
    public function emptyNodeSerialization($klass)
    {
        $n1 = new $klass(self::$treeConf);
        $data = $n1->dump();

        $n2 =  $klass::createFromArgs(array('treeConf'=>self::$treeConf,'data'=> $data));
        $this->assertEquals($n1->entries, $n2->entries);

        $n3 = Node::fromPageData(self::$treeConf, $data);
        $this->assertTrue(is_a($n3, $klass));
        $this->assertEquals($n1->entries, $n3->entries);
    }

    private function assertEntriesEquals($entriesA, $entriesB)
    {
        $callback = function($entry){
          unset($entry->data);
        };
        array_map($callback,$entriesA );
        array_map($callback,$entriesB );
        $this->assertEquals($entriesA, $entriesB);

    }
    /**
     * @test
     */
    public function leafNodeSerialization()
    {
        $n1 = LeafNode::createFromArgs(array('treeConf'=>self::$treeConf, 'nextPage'=>66));
        $n1->insertEntry(new Record(self::$treeConf, 43, "43"));
        $n1->insertEntry(new Record(self::$treeConf, 42, "42"));

        $this->assertEquals( $n1->entries , array(
            new Record(self::$treeConf, 42, "42"),
            new Record(self::$treeConf, 43, "43"),
        ));
        $data = $n1->dump();

        $n2 = new LeafNode(self::$treeConf, $data);
        $this->assertEntriesEquals($n1->entries,$n2->entries);
        $this->assertEquals($n1->nextPage , $n2->nextPage );
        $this->assertEquals(66  , $n2->nextPage );

    }

    /**
     * @test
     */
    public function leafNodeSerializationNoNextPage()
    {
        $n1 = new LeafNode(self::$treeConf);
        $data = $n1->dump();
        $n2 = new LeafNode(self::$treeConf,$data);
        $this->assertNull($n1->nextPage);
        $this->assertNull($n2->nextPage);
    }

    /**
     * @test
     */
    public function rootNodeSerialization()
    {
        $n1 = new RootNode(self::$treeConf);
        $n1->insertEntry(new Reference(self::$treeConf, 43, 2, 3));
        $n1->insertEntry(new Reference(self::$treeConf, 42, 1, 2));
        $this->assertTrue($n1->entries == array(new Reference(self::$treeConf, 42, 1, 2),
                new Reference(self::$treeConf, 43, 2, 3)));
        $data = $n1->dump();

        $n2 = RootNode::createFromArgs(array('treeConf'=>self::$treeConf,'data'=>$data));
        $this->assertEntriesEquals($n1->entries,$n2->entries);
        $this->assertNull($n1->nextPage);
        $this->assertNull($n2->nextPage);

    }

    /**
     * @test
     */
    public function nodeSlots()
    {
        // todo: implement
    }

    /**
     * @test
     */
    public function getNodeFromPageData()
    {
        $data = Integer::toBytes(2,1, ENDIAN) . Byte::nullPadding(4096 - 1);
        $tree_conf = new TreeConf(4096, 7, 16, 16, new IntegerSerializer());
        $this->assertTrue( is_a(
            Node::fromPageData($tree_conf, $data, 4),
            RootNode::class
        ));
    }

    /**
     * @test
     */
    public function insertFindGetRemoveEntries()
    {
        $node = new RootNode(self::$treeConf);

        # Test empty _find_entry_index, get and remove

        $nodeMethods = array('findEntryIndex','getEntry','removeEntry');

        foreach ($nodeMethods as $method) {
            try {
                $node->$method(42);
                $this->assertTrue(false);
            } catch (Exception $e) {
                $this->assertEquals("No entry for key 42", $e->getMessage());
            }
        }


        # Test insert_entry
        $r42 = new Reference(self::$treeConf, 42, 1, 2);
        $r43 = new Reference(self::$treeConf, 43, 2, 3);
        $node->insertEntryAtTheEnd($r43);
        $node->insertEntry($r42);

        $this->assertEquals(42,$node->entries[0]->key);
        $this->assertEquals(43,$node->entries[1]->key);

        # Test _find_entry_index
        $this->assertTrue($node->findEntryIndex(42) == 0);
        $this->assertTrue($node->findEntryIndex(43) == 1);

    }

    /**
     * @test
     */
    public function smallestBiggest()
    {
        $node =  new RootNode(self::$treeConf);

        try {
            $node->popSmallest();
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertEquals("index error",$e->getMessage());
        }

        # Test insert_entry
        $r42 = new Reference(self::$treeConf, 42, 1, 2);
        $r43 = new Reference(self::$treeConf, 43, 2, 3);
        $node->insertEntry($r43);
        $node->insertEntry($r42);

        # Smallest
        $this->assertEquals($node->smallestEntry() , $r42);
        $this->assertEquals($node->smallestKey() , $r42->key);

        # Biggest
        $this->assertEquals($node->biggestEntry() , $r43);
        $this->assertEquals($node->biggestKey() , $r43->key);


        $this->assertEquals($node->popSmallest() , $r42);
        $this->assertEquals($node->entries , array($r43));
    }

    /**
     * @test
     */
    public function freeListNodeSerialization()
    {
        $n1 = FreelistNode::createFromArgs(array('treeConf' => self::$treeConf, 'nextPage' => 3));
        $data = $n1->dump();

        $n2 = FreelistNode::createFromArgs(array('treeConf' => self::$treeConf, 'data' => $data));
        $this->assertEquals($n1->nextPage , $n2->nextPage);
        
    }
    /**
     * @test
     */
    public function freeListNodeSerializationNoNextPage()
    {
        $n1 = FreelistNode::createFromArgs(array('treeConf' => self::$treeConf, 'nextPage' => null));
        $data = $n1->dump();

        $n2 = FreelistNode::createFromArgs(array('treeConf' => self::$treeConf, 'data' => $data));
        $this->assertNull($n1->nextPage );
        $this->assertNull($n2->nextPage);

    }

    /**
     * @test
     */
    public function overflowNodeSerialization()
    {
        $n1 = OverflowNode::createFromArgs(array('treeConf' => self::$treeConf, 'nextPage' => 3));
        $n1->insertEntryAtTheEnd(new OpaqueData(self::$treeConf,"foo"));
        $data = $n1->dump();

        $n2 = OverflowNode::createFromArgs(array('treeConf' => self::$treeConf, 'data' => $data));
        $this->assertEquals($n1->nextPage , $n2->nextPage);

    }

    /**
     * @test
     */
    public function overflowNodeSerializationNoNextPage()
    {
        $n1 = OverflowNode::createFromArgs(array('treeConf' => self::$treeConf, 'nextPage' => null));
        $n1->insertEntryAtTheEnd(new OpaqueData(self::$treeConf,"foo"));
        $data = $n1->dump();

        $n2 = OverflowNode::createFromArgs(array('treeConf' => self::$treeConf, 'data' => $data));
        $this->assertNull($n1->nextPage );
        $this->assertNull($n2->nextPage);
    }

}