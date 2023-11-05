<?php
namespace Jinraynor1\BplusTree\Serializer;

class StringSerializer implements SerializerInterface
{
    public function serialize($value, $size)
    {
        $data =  pack("A*",$value);

        if( !(strlen($data)<= $size)){
            return false;
        }
        return $data;

    }

    public function deserialize($value)
    {
        return unpack("A*",$value)[1];
    }
}