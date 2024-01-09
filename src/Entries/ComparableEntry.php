<?php


namespace Jinraynor1\BplusTree\Entries;

/**
 * Entry that can be sorted against other entries based on their key
 * Class ComparableEntry
 * @package Jinraynor1\BplusTree\Entries
 */
abstract class ComparableEntry extends Entry
{
    protected $key;

    abstract function getKey();
    public function equals($other)
    {
        return strnatcmp($this->getKey() , $other->getKey())===0;
    }

    public function less($other)
    {
        return strnatcmp($this->getKey() , $other->getKey())<0;
    }

    public function lessEquals($other)
    {
        return strnatcmp($this->getKey() , $other->getKey())<=0;
    }


    public function greater($other)
    {
        return strnatcmp($this->getKey() , $other->getKey())>0;
    }

    public function greaterEquals($other)
    {
        return strnatcmp($this->getKey() , $other->getKey())>=0;
    }

}