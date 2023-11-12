<?php


namespace Jinraynor1\BplusTree\Entries;


use Jinraynor1\BplusTree\Primitives\Byte;
use Jinraynor1\BplusTree\TreeConf;
use Jinraynor1\BplusTree\Primitives\Integer;

/**
 * A container for the actual data the tree stores
 * Class Record
 * @package Jinraynor1\BplusTree\Entries
 */
class Record extends ComparableEntry
{
    public $key;
    public $value;
    public $overflowPage;
    /**
     * @var TreeConf
     */
    private $treeConf;
    public $data;
    /**
     * @var string
     */
    public $length;

    public function __construct(TreeConf $treeConf, $key = null, $value = null, $data = null, $overflowPage = null)
    {

        $this->treeConf = $treeConf;
        $this->key = $key;
        $this->value = $value;
        $this->data = $data;
        $this->overflowPage = $overflowPage;


        $this->length = (
            USED_KEY_LENGTH_BYTES + $this->treeConf->getKeySize() +
            USED_VALUE_LENGTH_BYTES + $this->treeConf->getValueSize() +
            PAGE_REFERENCE_BYTES
        );

        $this->data = $data;

        if ($this->data) {
            $this->key = false;
            $this->value = false;
            $this->overflowPage = false;
        } else {
            $this->key = $key;
            $this->value = $value;
            $this->overflowPage = $overflowPage;
        }

    }

    /**
     * @param $prop
     * @param $value
     * @throws \Exception
     */
    public function __set($prop,$value)
    {
        throw new \Exception("cannot set unknown prop '$prop', value '$value' ");
    }

    public static function createFromData(TreeConf $treeConf, $data)
    {
        return new self($treeConf, null, null, $data);
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
    public function getValue()
    {
        if ($this->value === false) {
            $this->load($this->data);
        }
        return $this->value;
    }

    /**
     * @param mixed|null $value
     */
    public function setValue($value)
    {
        $this->data = null;
        $this->value = $value;
    }

    /**
     * @return mixed|null
     */
    public function getOverflowPage()
    {
        if ($this->overflowPage === false) {
            $this->load($this->data);
        }
        return $this->overflowPage;
    }

    /**
     * @param mixed|null $overflowPage
     */
    public function setOverflowPage($overflowPage)
    {
        $this->data = null;
        $this->overflowPage = $overflowPage;
    }

    /**
     * @return TreeConf
     */
    public function getTreeConf()
    {
        return $this->treeConf;
    }

    /**
     * @param TreeConf $treeConf
     */
    public function setTreeConf($treeConf)
    {
        $this->treeConf = $treeConf;
    }

    /**
     * @return mixed|null
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * @param mixed|null $data
     */
    public function setData($data)
    {
        $this->data = $data;
    }


    function load($data)
    {

        assert(strlen($data) == $this->length);

        $end_used_key_length = USED_KEY_LENGTH_BYTES;

        $used_key_length = unpack("v",substr($data, 0,2))[1];

        assert((0 <= $used_key_length) && ($used_key_length <= $this->treeConf->getKeySize()));

        $end_key = $end_used_key_length + $used_key_length;
        $this->key = $this->treeConf->getSerializer()->deserialize(
            substr($data, $end_used_key_length,$end_key-$end_used_key_length)
        );

        $start_used_value_length = (
            $end_used_key_length + $this->treeConf->getKeySize()
        );

        $end_used_value_length = (
            $start_used_value_length + USED_VALUE_LENGTH_BYTES
        );


        $used_value_length = unpack("v",
            substr($data, $start_used_value_length,$end_used_value_length-$start_used_value_length)
        )[1];

        assert(((0 <= $used_value_length) && ($used_value_length <= $this->treeConf->getValueSize())));


        $end_value = $end_used_value_length + $used_value_length;

        $start_overflow = $end_used_value_length + $this->treeConf->getValueSize();
        $end_overflow = $start_overflow + PAGE_REFERENCE_BYTES;
        $overflow_page = unpack("v",
            substr($data, $start_overflow,$end_overflow-$start_overflow)
        )[1];

        if ($overflow_page) {
            $this->overflowPage = $overflow_page;
            $this->value = null;
        } else {
            $this->overflowPage = null;
            $this->value = substr($data, $end_used_value_length,$end_value-$end_used_value_length);
        }
    }

    function dump()
    {
        if ($this->data){
            return $this->data;
        }

        assert(!(is_null($this->value) or is_null($this->overflowPage)));

        $key_as_bytes = $this->treeConf->getSerializer()->serialize(
            $this->getKey(), $this->treeConf->getKeySize()
        );
        $used_key_length = strlen($key_as_bytes);
        $overflow_page = $this->overflowPage ?: 0;
        if ($overflow_page) {
            $value = '';
        } else {
            $value = $this->value;
        }
        $used_value_length = strlen($value);

        return (
            pack("v",$used_key_length) .
            $key_as_bytes .
            pack("a".($this->treeConf->getKeySize() - $used_key_length), "")  .
            pack("v",$used_value_length) .
            $value .
            pack("a".($this->treeConf->getValueSize() - $used_value_length), "").
            pack("V",$overflow_page)
        );
    }

    public function represents()
    {
        if ($this->overflowPage) {
            return sprintf('<Record: %s overflowing value>' , $this->key);
        }

        if ($this->value) {
            return sprintf('<Record: %s value=%s>',
                $this->key, py_slice($this->value, "0:16"));
        }

        return sprintf('<Record: %s unknown value>', $this->key);

    }



}