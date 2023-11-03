<?php


namespace Jinraynjor1\BplusTree\Entries;


use Jinraynjor1\BplusTree\TreeConf;

/**
 * Entry holding opaque data
 * Class OpaqueData
 * @package Jinraynjor1\BplusTree\Entries
 */
class OpaqueData extends Entry
{
    /**
     * @var mixed|null
     */
    public $data;

    public function __construct(TreeConf $treeConf, $data = null)
    {
        $this->data = $data;
    }

    public static function createFromData(TreeConf $treeConf, $data)
    {
        return new self($treeConf,  $data);
    }

    function load($data)
    {
        $this->data = $data;
    }

    function dump()
    {
        return $this->data;
    }

    function represents(){

        return sprintf("<OpaqueData: %s>",$this->data);
    }


}