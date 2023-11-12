<?php


namespace Jinraynor1\BplusTree\Entries;


use Jinraynor1\BplusTree\Primitives\Byte;
use Jinraynor1\BplusTree\Primitives\Integer;
use Jinraynor1\BplusTree\TreeConf;

/**
 * A container for a reference to other nodes.
 * Class Reference
 * @package Jinraynor1\BplusTree\Entries
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
        assert(strlen($data) == $this->length);

        $end_before = PAGE_REFERENCE_BYTES;
        $this->before = unpack("V",substr($data,0,$end_before))[1];

        $end_used_key_length = $end_before + USED_KEY_LENGTH_BYTES;

        $used_key_length = unpack("v",substr($data,$end_before,$end_used_key_length-$end_before))[1];

        assert((0 <= $used_key_length) && ($used_key_length <= $this->treeConf->getKeySize()));

        $end_key = $end_used_key_length + $used_key_length;

        $this->key = $this->treeConf->getSerializer()->deserialize(
                substr($data,$end_used_key_length,$end_key-$end_used_key_length)
        );

        $start_after = $end_used_key_length + $this->treeConf->getKeySize();

        $end_after = $start_after + PAGE_REFERENCE_BYTES;

        $this->after = Integer::fromBytes(substr($data,$start_after,$end_after-$start_after), ENDIAN);

    }

    function dump()
    {
        if ($this->data)
            return $this->data;

        assert(is_int($this->before));

        assert(is_int($this->after));

        $key_as_bytes = $this->treeConf->getSerializer()->serialize(
                $this->key, $this->treeConf->getKeySize()
        );

        $used_key_length = strlen($key_as_bytes);

        return (
            pack("V",$this->before).
            pack("v",$used_key_length).
            $key_as_bytes.
            pack("a".($this->treeConf->getKeySize() - $used_key_length), "")  .
            pack("V",$this->after)

        );

    }

    public function represents()
    {
        return sprintf("<Reference: key=%s before=%s after=%s>", $this->key, $this->before, $this->after);
    }



}