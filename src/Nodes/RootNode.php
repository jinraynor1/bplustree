<?php


namespace Jinraynjor1\BplusTree\Nodes;


use Jinraynjor1\BplusTree\TreeConf;

class RootNode extends ReferenceNode
{
    /**
     * The first node at the top of the tree.
     * @param TreeConf $treeConf
     * @param null $data
     * @param null $page
     * @param null $parent
     */


    public function __construct(TreeConf $treeConf, $data = null, $page = null, $parent = null)
    {
        $this->node_type_int = 2;
        $this->min_children = 2;
        $this->max_children = $treeConf->getOrder() ;


        parent::__construct($treeConf, $data, $page, $parent);
    }

    public function converToInternal()
    {
        $internal = InternalNode::createFromArgs(array('treeConf'=>$this->treeConf, 'page'=>$this->page));
        $internal->entries = $this->entries;
        return $internal;
    }

}