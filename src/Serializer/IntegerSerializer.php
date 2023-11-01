<?php

namespace Jinraynjor1\BplusTree\Serializer;


use Jinraynjor1\BplusTree\Primitives\Integer;

class IntegerSerializer implements SerializerInterface
{


    public function serialize($value, $size)
    {
        return Integer::toBytes($value,$size,ENDIAN);
    }


    public function deserialize($value)
    {
        return  Integer::fromBytes($value,ENDIAN);
    }

}