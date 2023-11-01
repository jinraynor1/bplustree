<?php


use PHPUnit\Framework\TestCase;
use Jinraynjor1\BplusTree\Serializer\StringSerializer;
use Jinraynjor1\BplusTree\Serializer\IntegerSerializer;

class SerializerTest extends TestCase
{



    /**
     * @test
     */
    public function serializeString()
    {
        $stringSerializer = new StringSerializer();
        $serialized = $stringSerializer->serialize("mamá",5);
        $this->assertSame(5,strlen($serialized));
        $this->assertSame("mamá",$serialized);
        $deserialize = $stringSerializer->deserialize($serialized);
        $serialized = $stringSerializer->serialize("mamá", 5);
        $this->assertSame($serialized,$deserialize);

    }



    /**
     * @test
     */
    public function serializeInteger()
    {
        $intSerializer = new IntegerSerializer();
        $encoded = $intSerializer->serialize(42, 2);
        $this->assertSame("*\x00",$encoded);
        $decoded = $intSerializer->deserialize("*\x00");
        $this->assertSame(42,$decoded);


        $encoded = $intSerializer->serialize(42, 4);
        $this->assertSame("*\x00\x00\x00",$encoded);
        $decoded = $intSerializer->deserialize("*\x00\x00\x00");
        $this->assertSame(42,$decoded);


    }


}