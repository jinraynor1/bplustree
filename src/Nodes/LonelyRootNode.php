<?php


namespace Jinraynor1\BplusTree\Nodes;


use Jinraynor1\BplusTree\TreeConf;

/**
 * A Root node that holds records.
 * It is an exception for when there is only a single node in the tree.
 *
 * Class LonelyRootNode
 * @package Jinraynor1\BplusTree\Nodes
 */
class LonelyRootNode extends RecordNode
{
    public function __construct(TreeConf $treeConf, $data = null, $page = null, $parent= null)
    {
        $this->node_type_int = 1;
        $this->min_children = 0;
        $this->max_children = $treeConf->getOrder() -1;

        parent::__construct($treeConf,$data,$page,$parent);
    }

    public function convertToLeaf()
    {
        $leaf = LeafNode::createFromArgs(array('treeConf'=>$this->treeConf,'page'=>$this->page));
        $leaf->entries = $this->entries;
        return $leaf;
    }
}