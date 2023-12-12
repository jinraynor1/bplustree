<?php


namespace Jinraynor1\BplusTree\Nodes;


use Jinraynor1\BplusTree\Entries\Record;
use Jinraynor1\BplusTree\TreeConf;

class RecordNode extends Node
{
    public function __construct(TreeConf $treeConf, $data = null , $page = null, $parent = null, $nextPage = null)
    {
        $this->entry_class = "\\Jinraynor1\\BplusTree\\Entries\\Record";
        parent::__construct($treeConf,$data,$page,$parent,$nextPage);


    }

}