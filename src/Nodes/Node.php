<?php


namespace Jinraynjor1\BplusTree\Nodes;


use Jinraynjor1\BplusTree\Primitives\Integer;
use Jinraynjor1\BplusTree\TreeConf;

abstract class Node
{
    private $node_type_int;
    private $max_children = 0;
    private $min_children = 0;
    private $entry_class = null;
    /**
     * @var TreeConf
     */
    private $treeConf;
    /**
     * @var array
     */
    private $entries;
    /**
     * @var mixed|null
     */
    private $page;
    /**
     * @var mixed|null
     */
    private $parent;
    /**
     * @var mixed|null
     */
    private $nextPage;

    public function __construct(TreeConf $treeConf, $data = null , $page = null, $parent = null, $nextPage = null)
    {
        $this->treeConf = $treeConf;
        $this->entries = array();
        $this->page = $page;
        $this->parent = $parent;
        $this->nextPage = $nextPage;

        if($data){
            $this->load($data);
        }


    }

    public function load($data)
    {
        if (!(strlen($data) == $this->treeConf->getPageSize())) {
            throw new \RuntimeException("Invalid length for data");
        }

        $end_used_page_length = NODE_TYPE_BYTES + USED_PAGE_LENGTH_BYTES;
        $used_page_length = Integer::fromBytes(
                py_slice($data,NODE_TYPE_BYTES.":$end_used_page_length"), ENDIAN
        );

    }
}