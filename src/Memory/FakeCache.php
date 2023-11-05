<?php


namespace Jinraynor1\BplusTree\Memory;

/**
 * A cache that doesn't cache anything.
 *
 * Class FakeCache
 * @package Jinraynor1\BplusTree\Memory
 */
class FakeCache implements CacheInterface
{

    /**
     * @param $key
     */
    public function get($key)
    {

    }

    public function put($key, $value)
    {

    }

    public function clear()
    {

    }
}