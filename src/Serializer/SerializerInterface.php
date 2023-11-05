<?php


namespace Jinraynor1\BplusTree\Serializer;


interface SerializerInterface
{
    public function serialize($value, $size);
    public function deserialize($value);
}