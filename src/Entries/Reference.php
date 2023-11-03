<?php


namespace Jinraynjor1\BplusTree\Entries;


use Jinraynjor1\BplusTree\Primitives\Byte;
use Jinraynjor1\BplusTree\Primitives\Integer;
use Jinraynjor1\BplusTree\TreeConf;

/**
 * A container for a reference to other nodes.
 * Class Reference
 * @package Jinraynjor1\BplusTree\Entries
 */
class Reference extends ComparableEntry
{
    /**
     * @var TreeConf
     */
    private $treeConf;

    /**
     * @var false|mixed|null
     */
    public $key;
    /**
     * @var float|int|mixed
     */
    public $length;
    /**
     * @var mixed|null
     */
    public $data;
    /**
     * @var false|mixed|null
     */
    public $before;
    /**
     * @var false|mixed|null
     */
    public $after;

    public function __construct(TreeConf $treeConf, $key = null, $before = null, $after = null, $data = null)
    {
        $this->treeConf = $treeConf;
        $this->length = (
            2 * PAGE_REFERENCE_BYTES +
            USED_KEY_LENGTH_BYTES +
            $this->treeConf->getKeySize()
        );

        $this->data = $data;

        if ($this->data) {
            $this->key = false;
            $this->before = false;
            $this->after = false;
        } else {
            $this->key = $key;
            $this->before = $before;
            $this->after = $after;
        }
        
    }


    public static function createFromData(TreeConf $treeConf, $data)
    {
        return new self($treeConf, null, null, null, $data);
    }
    /**
     * @return mixed|null
     */
    public function getKey()
    {
        if ($this->key === false) {
            $this->load($this->data);
        }
        return $this->key;
    }

    /**
     * @param mixed|null $key
     */
    public function setKey($key)
    {
        $this->data = null;
        $this->key = $key;
    }

    /**
     * @return mixed|null
     */
    public function getBefore()
    {
        if ($this->before === false) {
            $this->load($this->data);
        }
        return $this->before;
    }

    /**
     * @param mixed|null $before
     */
    public function setBefore($before)
    {
        $this->data = null;
        $this->before = $before;
    }

    /**
     * @return mixed|null
     */
    public function getAfter()
    {
        if ($this->after === false) {
            $this->load($this->data);
        }
        return $this->after;
    }

    /**
     * @param mixed|null $after
     */
    public function setAfter($after)
    {
        $this->data = null;
        $this->after = $after;
    }

    function load($data)
    {
        if (!(strlen($data) == $this->length)) {
            throw new \RuntimeException("Invalid length for data");
        }

        $end_before = PAGE_REFERENCE_BYTES;
        $this->before = Integer::fromBytes(py_slice($data,"0:$end_before"), ENDIAN);

        $end_used_key_length = $end_before + USED_KEY_LENGTH_BYTES;

        $used_key_length = Integer::fromBytes(
                py_slice($data,"$end_before:$end_used_key_length"), ENDIAN
        );

        if (!((0 <= $used_key_length) && ($used_key_length <= $this->treeConf->getKeySize()))) {
            throw new \RuntimeException("Invalid key length");
        }

        $end_key = $end_used_key_length + $used_key_length;

        $this->key = $this->treeConf->getSerializer()->deserialize(
                py_slice($data,"$end_used_key_length:$end_key")
        );

        $start_after = $end_used_key_length + $this->treeConf->getKeySize();

        $end_after = $start_after + PAGE_REFERENCE_BYTES;

        $this->after = Integer::fromBytes(py_slice($data,"$start_after:$end_after"), ENDIAN);

    }

    function dump()
    {
        if ($this->data)
            return $this->data;

        if (!is_int($this->before)) {
            throw new \RuntimeException("prop before is not an int ");
        }

        if (!is_int($this->after)) {
            throw new \RuntimeException("prop after is not an int ");
        }

        $key_as_bytes = $this->treeConf->getSerializer()->serialize(
                $this->key, $this->treeConf->getKeySize()
        );

        $used_key_length = strlen($key_as_bytes);

        return (
            Integer::toBytes($this->before,PAGE_REFERENCE_BYTES, ENDIAN) .
            Integer::toBytes($used_key_length,USED_VALUE_LENGTH_BYTES, ENDIAN) .
            $key_as_bytes .
            Byte::nullPadding($this->treeConf->getKeySize() - $used_key_length) .
            Integer::toBytes($this->after,PAGE_REFERENCE_BYTES, ENDIAN)
        );


    }

    public function represents()
    {
        return sprintf("<Reference: key=%s before=%s after=%s>", $this->key, $this->before, $this->after);
    }



}