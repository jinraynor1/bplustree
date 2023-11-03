<?php


namespace Jinraynjor1\BplusTree\Nodes;


use Jinraynjor1\BplusTree\TreeConf;

class FreeListNode extends Node
{
    public function __construct(TreeConf $treeConf, $data = null, $page = null, $nextPage = null)
    {
        $this->node_type_int = 6;
        $this->min_children = 0;
        $this->max_children = 0;



        parent::__construct($treeConf, $data, $page, null, $nextPage);
    }

    public function represents()
    {
        $r = new \ReflectionClass($this);
        return sprintf("<%s: page=%s next_page=%s>", $r->getShortName(), $this->page, $this->nextPage);
    }
}