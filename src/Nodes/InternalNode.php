<?php


namespace Jinraynor1\BplusTree\Nodes;


use Jinraynor1\BplusTree\TreeConf;

class InternalNode extends ReferenceNode
{

    public function __construct(TreeConf $treeConf, $data = null, $page = null, $parent = null)
    {
        $this->node_type_int = 3;
        $this->min_children = ceil($treeConf->getOrder() / 2);
        $this->max_children = $treeConf->getOrder();


        parent::__construct($treeConf, $data, $page, $parent);
    }
}