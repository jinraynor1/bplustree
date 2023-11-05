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
        return $this->getKey() == $other->getKey();
    }

    public function less($other)
    {
        return $this->getKey() < $other->getKey();
    }

    public function lessEquals($other)
    {
        return $this->getKey() <= $other->getKey();
    }


    public function greater($other)
    {
        return $this->getKey() > $other->getKey();
    }

    public function greaterEquals($other)
    {
        return $this->getKey() >= $other->getKey();
    }

}