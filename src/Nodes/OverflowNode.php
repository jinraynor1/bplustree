<?php


namespace Jinraynor1\BplusTree\Nodes;


use Jinraynor1\BplusTree\Entries\OpaqueData;
use Jinraynor1\BplusTree\TreeConf;

class OverflowNode extends Node
{
    public function __construct(TreeConf $treeConf, $data = null, $page = null, $nextPage = null)
    {
        $this->node_type_int = 5;
        $this->min_children = 1;
        $this->max_children = 1;
        $this->entry_class = "\\Jinraynor1\\BplusTree\\Entries\\OpaqueData";


        parent::__construct($treeConf, $data, $page, null, $nextPage);
    }

    public function represents()
    {
        $r = new \ReflectionClass($this);
        return sprintf("<%s: page=%s next_page=%s>", $r->getShortName(), $this->page, $this->nextPage);
    }
}