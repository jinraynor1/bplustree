<?php


namespace Jinraynjor1\BplusTree;


use Jinraynjor1\BplusTree\Serializer\SerializerInterface;

class TreeConf
{
    private $pageSize;
    private $order;
    private $keySize;
    private $valueSize;
    /**
     * @var SerializerInterface
     */
    private $serializer;

    public function __construct($pageSize, $order, $keySize, $valueSize, SerializerInterface $serializer)
    {

        $this->pageSize = $pageSize;
        $this->order = $order;
        $this->keySize = $keySize;
        $this->valueSize = $valueSize;
        $this->serializer = $serializer;
    }

    /**
     * @return mixed
     */
    public function getPageSize()
    {
        return $this->pageSize;
    }

    /**
     * @return mixed
     */
    public function getOrder()
    {
        return $this->order;
    }

    /**
     * @return mixed
     */
    public function getKeySize()
    {
        return $this->keySize;
    }

    /**
     * @return mixed
     */
    public function getValueSize()
    {
        return $this->valueSize;
    }

    /**
     * @return SerializerInterface
     */
    public function getSerializer()
    {
        return $this->serializer;
    }

}