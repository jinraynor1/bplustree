<?php


namespace Jinraynor1\BplusTree\Memory;


interface CacheInterface
{
    public function get($key);
    public function put($key, $value);
    public function clear();
}