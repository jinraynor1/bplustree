<?php


namespace Jinraynjor1\BplusTree\Nodes;


use Jinraynjor1\BplusTree\Entries\Record;
use Jinraynjor1\BplusTree\TreeConf;

class RecordNode extends Node
{
    public function __construct(TreeConf $treeConf, $data = null , $page = null, $parent = null, $nextPage = null)
    {
        $this->entry_class = Record::class;
        parent::__construct($treeConf,$data,$page,$parent,$nextPage);


    }

}