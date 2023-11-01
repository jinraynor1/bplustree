<?php


use Jinraynjor1\BplusTree\TreeConf;
use PHPUnit\Framework\TestCase;

class NodeTest extends TestCase
{
    /**
     * @var TreeConf
     */
    private static $treeConf;

    public function setUp()
    {
        #self::$treeConf = new TreeConf();
    }
    /**
     * @test
     */
    public function emptyNodeSerialization()
    {
        return;
        $node1 = new LonelyRootNode(self::$treeConf);
        $data = $node1->dump();

        $node2 = new LonelyRootNode(self::$treeConf, $data);
        $this->assertEquals( $node1->entries , $node2->entries);

        $node3 = Node::fromPageData(self::$treeConf, $data);
        $this->isInstanceOf($node3,LonelyRootNode::class);
        $this->assertEquals( $node1->entries , $node3->entries);
    }
}