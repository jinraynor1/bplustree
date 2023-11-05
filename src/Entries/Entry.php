<?php


namespace Jinraynor1\BplusTree\Entries;


abstract class Entry
{
    /**
     * Deserialize data into an object
     * @param $data
     * @return mixed
     */
    abstract function load($data);

    /**
     * Serialize object to data.
     * @return mixed
     */
    abstract function dump();
}