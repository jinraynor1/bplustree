<?php


namespace Jinraynjor1\BplusTree\Nodes;

use Jinraynjor1\BplusTree\TreeConf;

/**
 * Node that holds the actual records within the tree
 *
 * Class LeafNode
 * @package Jinraynjor1\BplusTree\Nodes
 */
class LeafNode extends RecordNode
{
    public function __construct(TreeConf $treeConf, $data = null, $page = null, $parent = null, $nextPage = null)
    {
        $this->node_type_int = 4;
        $this->min_children = ceil($treeConf->getOrder() / 2) - 1;
        $this->max_children = $treeConf->getOrder()- 1;

        parent::__construct($treeConf,$data,$page,$parent,$nextPage);

    }

}