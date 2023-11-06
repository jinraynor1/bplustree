<?php


use Jinraynor1\BplusTree\BPlusTree;
use Jinraynor1\BplusTree\Helpers\Slice;
use Jinraynor1\BplusTree\Memory\FileMemory;
use Jinraynor1\BplusTree\Nodes\LeafNode;
use Jinraynor1\BplusTree\Nodes\LonelyRootNode;
use PHPUnit\Framework\TestCase;
use Jinraynor1\BplusTree\Exceptions\ValueError;

class TreeTest extends TestCase
{
    public function setUp()
    {

        if (!defined('FILENAME'))
            define('FILENAME', '/tmp/bplustree-testfile.index');

        if (file_exists(FILENAME))
            unlink(FILENAME);

        if (file_exists(FILENAME . '-wal'))
            unlink(FILENAME . '-wal');

    }


    public function tearDown()
    {
        if (file_exists(FILENAME))
            unlink(FILENAME);

        if (file_exists(FILENAME . '-wal'))
            unlink(FILENAME . '-wal');
    }

    /**
     * @test
     */
    public function createAndLoadFile()
    {
        $b = new BPlusTree(FILENAME);
        $this->assertTrue(is_a($b->mem, FileMemory::class));
        $b->insert(5, 'foo');
        $b->close();

    }

    /**
     * @test
     */
    public function closingContextManager()
    {
        $b = $this->getMockBuilder(BPlusTree::class)
            ->setConstructorArgs(array(FILENAME, 512, 100, 8, 128))
            ->setMethods(array('close'))->getMock();

        $b->expects($this->once())->method('close');
        $b2 = clone($b); //this calls destructs
        unset($b2);
    }

    /**
     * @test
     */
    public function initialValues()
    {
        $b = BPlusTree::createFromArgs(array('filename' => FILENAME, 'pageSize' => 512, 'valueSize' => 128));
        $this->assertTrue($b->treeConf->getPageSize() == 512);
        $this->assertTrue($b->treeConf->getOrder() == 100);
        $this->assertTrue($b->treeConf->getKeySize() == 8);
        $this->assertTrue($b->treeConf->getValueSize() == 128);

        $b->close();


    }

    public function partialConstructors()
    {
        //todo: php cannot use partial constructors, maybe use the builder pattern
        #def test_partial_constructors(b):
        #node = b.RootNode()
        #record = b.Record()
        #assert node._tree_conf == b._tree_conf
        #assert record._tree_conf == b._tree_conf
    }

    private function buildBPlusTree()
    {
        return BPlusTree::createFromArgs(array(
            'filename' => FILENAME,
            'keySize' => 16,
            'valueSize' => 16,
            'order' => 4
        ));


    }

    /**
     * @test
     */
    public function insertSetItemTree()
    {
        $b = $this->buildBPlusTree();
        $b->insert(1, 'foo');

        try {
            $b->insert(1, 'bar');
            $this->assertTrue(false);
        } catch (ValueError $e) {
            $this->assertTrue(true);
        }

        $this->assertTrue($b->get(1) == 'foo');

        $b->insert(1, 'baz', true);
        $this->assertTrue($b->get(1) == 'baz');

    }

    /**
     * @test
     */
    public function getTree()
    {
        $b = $this->buildBPlusTree();
        $b->insert(1, 'foo');
        $this->assertTrue($b->get(1) == 'foo');
        $this->assertNull($b->get(2));
        $this->assertTrue($b->get(2, 'foo') == 'foo');
    }

    /**
     * @test
     */
    public function getItemTree()
    {
        $b = $this->buildBPlusTree();

        $b->insert(1, 'foo');
        $b->insert(2, 'bar');
        $b->insert(5, 'baz');

        $this->assertTrue($b->get(1) == 'foo');

        try {
            $b->getItem(4);
            $this->assertTrue(false);
        } catch (Exception $e) {
            $this->assertTrue(true);
        }


        $data = array();
        foreach($b->iterSlice(new Slice(1, 3)) as $entry){
            $data[$entry->key] = $entry->value;
        };
        $this->assertTrue($data[1] == 'foo');
        $this->assertTrue($data[2] == 'bar');

        $data = array();
        foreach($b->iterSlice(new Slice(0, 10)) as $entry){
            $data[$entry->key] = $entry->value;
        };
        $this->assertTrue($data[1] == 'foo');
        $this->assertTrue($data[2] == 'bar');
        $this->assertTrue($data[5] == 'baz');

    }

    /**
     * @test
     */
    public function containsTree()
    {
        $b = $this->buildBPlusTree();

        $b->insert(1, 'foo');
        $this->assertTrue($b->contains(1));
        $this->assertFalse($b->contains(2));

    }

    /**
     * @test
     */
    public function lenTree()
    {
        $b = $this->buildBPlusTree();

        $this->assertTrue($b->length() == 0);
        $b->insert(1, 'foo');
        $this->assertTrue($b->length() == 1);
        foreach (range(2, 100) as $i) {
            $b->insert($i, "$i");
        }
        $this->assertTrue($b->length() == 100);

    }

    /**
     * @test
     */
    public function lengthHintTree()
    {
        $b = BPlusTree::createFromArgs(array(
                'filename' => FILENAME,
                'keySize' => 16,
                'valueSize' => 16,
                'order' => 100)
        );
        $this->assertTrue($b->lengthHint() == 49);
        $b->insert(1, 'foo');
        $this->assertTrue($b->lengthHint() == 49);
        #do not use more than 1000 because is too slow
        foreach (range(2, 1000) as $i) {
            $b->insert($i, "$i");
        }
        $this->assertTrue($b->lengthHint() == 714);

        $b->close();
    }
    
    //todo: pending tests goes here

    /**
     * @test
     */
    public function checkpoint()
    {
        $b = $this->buildBPlusTree();
        $b->checkpoint();
        $b->insert(1, 'foo');
        $this->assertTrue( empty($b->mem->wal->notCommitedPages));
        $this->assertTrue( !empty($b->mem->wal->committedPages));
        $b->checkpoint();

        $this->assertTrue( empty($b->mem->wal->notCommitedPages));
        $this->assertTrue( empty($b->mem->wal->committedPages));
        
    }

    /**
     * @test
     */
    public function leftRecordNodeInTree()
    {
        $b = BPlusTree::createFromArgs(array(
                'filename' => FILENAME,
                'order' => 3)
        );
        $this->assertTrue( $b->leftRecordNode() == $b->rootNode());
        $this->assertTrue( is_a($b->leftRecordNode(), LonelyRootNode::class));
        $b->insert(1, '1');
        $b->insert(2, '2');
        $b->insert(3, '3');

        $this->assertTrue( is_a($b->leftRecordNode(), LeafNode::class));
        $b->close();
    }

    /**
     * @test
     */
    public function overFlow()
    {
        $data = str_repeat('f', 323343);
        $b = $this->buildBPlusTree();

        $first_overflow_page = null;
        $b->mem->writeTransaction(function()use($b,$data, &$first_overflow_page){
            $first_overflow_page = $b->createOverflow($data);
            $this->assertTrue( $b->readFromOverflow($first_overflow_page) == $data);
        });

        $b->mem->readTransaction(function()use($b,$data,$first_overflow_page){
            $this->assertTrue( $b->readFromOverflow($first_overflow_page) == $data);
        });



        $this->assertTrue( $b->mem->last_page == 81);

        $b->mem->writeTransaction(function()use($b,$data, $first_overflow_page){
            $b->deleteOverflow($first_overflow_page);
        });


        $b->mem->writeTransaction(function()use($b){
            $list = range(81,2,-1);
            foreach ($list as $i){
                $this->assertTrue( $b->mem->nextAvailablePage() == $i);
            }
        });

    }

    /**
     * @test
     */
    public function batchInsert()
    {
        $b = $this->buildBPlusTree();
        $b->batchInsert(array_combine(range(0, 100),range(0, 100)));
        $b->batchInsert(array_combine(range(101, 200),range(101, 200)));

        $i = 0;
        foreach($b->items() as $k=>$v){
            assert( $k == $i);
            assert ($v == $i);
            $i += 1;
        }

        $this->assertTrue( $i == 201);
    }
}