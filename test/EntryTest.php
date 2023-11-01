<?php

use Jinraynjor1\BplusTree\Entries\OpaqueData;
use Jinraynjor1\BplusTree\Entries\Reference;
use Jinraynjor1\BplusTree\Serializer\IntegerSerializer;
use Jinraynjor1\BplusTree\Serializer\StringSerializer;
use \PHPUnit\Framework\TestCase;
use Jinraynjor1\BplusTree\Entries\Record;
use Jinraynjor1\BplusTree\TreeConf;

class EntryTest extends TestCase
{

    /**
     * @var TreeConf
     */
    private static $treeConf;

    public function setUp()
    {
        self::$treeConf = new TreeConf(4096, 4, 16, 16, new IntegerSerializer());
    }

    /**
     * @test
     */
    public function recordIntSerialization()
    {
        $r1 = new Record(self::$treeConf, 42, 'foo');
        $data = $r1->dump();

        $r2 = Record::createFromData(self::$treeConf, $data);
        $this->assertTrue($r1->equals($r2));
        $this->assertEquals($r1->getValue(), $r2->getValue());
        $this->assertEquals($r1->getOverflowPage(), $r2->getOverflowPage());

    }

    /**
     * @test
     */
    public function recordStrSerialization()
    {
        $treeConf = new TreeConf(4096, 4, 40, 40, new StringSerializer());
        $r1 = new Record($treeConf, '0', "0");
        $data = $r1->dump();
        $r2 = Record::createFromData($treeConf, $data);
        $this->assertTrue($r1->equals($r2));
        $this->assertEquals($r1->getValue(), $r2->getValue());
        $this->assertEquals($r1->getOverflowPage(), $r2->getOverflowPage());
    }

    /**
     * @test
     */
    public function recordIntSerializationOverflowValue()
    {
        $r1 = new Record(self::$treeConf, $key = 42, $value = null, $data = null, $overflow = 5);
        $data = $r1->dump();
        $r2 = Record::createFromData(self::$treeConf, $data);
        $this->assertTrue($r1->equals($r2));
        $this->assertEquals($r1->getValue(), $r2->getValue());
        $this->assertEquals($r1->getOverflowPage(), $r2->getOverflowPage());

    }

    /**
     * @test
     */
    public function recordRepresents()
    {
        $r1 = new Record(self::$treeConf, 42, 'foo');
        $this->assertEquals("<Record: 42 value=foo>", $r1->represents());
        $r1->setValue(false);
        $this->assertEquals("<Record: 42 unknown value>", $r1->represents());
        $r1->setOverflowPage(5);
        $this->assertEquals("<Record: 42 overflowing value>", $r1->represents());

    }

    /**
     * @test
     */
    public function recordRestrictAccessToUnknownProperty()
    {
        $this->setExpectedException("Exception", "cannot set unknown prop");
        $r1 = new Record(self::$treeConf, 42, 'foo');
        $r1->foo = true;
    }

    /**
     * @test
     */
    public function recordLazyLoad()
    {
        $data = (new Record(self::$treeConf, 42, "foo"))->dump();
        $r = Record::createFromData(self::$treeConf, $data);

        $this->assertEquals($data, $r->getData());
        $this->assertEquals(false, $r->key);
        $this->assertEquals(false, $r->value);
        $this->assertEquals(false, $r->overflowPage);

        $_ = $r->getKey();
        $this->assertEquals(42, $r->key);
        $this->assertEquals("foo", $r->value);
        $this->assertEquals(null, $r->overflowPage);

        $r->setKey(27);
        $this->assertEquals(27, $r->key);
        $this->assertEquals(null, $r->data);

    }

    /**
     * @test
     */
    public function referenceIntSerialization()
    {
        $r1 = new Reference(self::$treeConf, 42, 1, 2);
        $data = $r1->dump();
        $r2 = new Reference(self::$treeConf, null, null, null, $data);
        $this->assertTrue($r1->equals($r2));
        $this->assertEquals($r1->getBefore(), $r2->getBefore());
        $this->assertEquals($r1->getAfter(), $r2->getAfter());

    }

    /**
     * @test
     */
    public function referenceStrSerialization()
    {
        $treeConf = new TreeConf(4096, 4, 40, 40, new StringSerializer());
        $r1 = new Reference($treeConf, "foo", 1,2);
        $data = $r1->dump();

        $r2 = new Reference($treeConf, null, null, null, $data);
        $this->assertTrue($r1->equals($r2));
        $this->assertEquals($r1->getBefore(), $r2->getBefore());
        $this->assertEquals($r1->getAfter(), $r2->getAfter());
    }

    /**
     * @test
     */
    public function referenceRepresents()
    {
        $r1 = new Reference(self::$treeConf, 42, 1 ,2 );
        $this->assertEquals("<Reference: key=42 before=1 after=2>", $r1->represents());

    }
    /**
     * @test
     */
    public function referenceLazyLoad()
    {
        $data = (new Reference(self::$treeConf, 42, 1, 2))->dump();
        $r = new Reference(self::$treeConf, null, null, null, $data);

        $this->assertEquals($data, $r->data);
        $this->assertEquals(false, $r->key);
        $this->assertEquals(false, $r->before);
        $this->assertEquals(false, $r->after);

        $_ = $r->getKey();
        $this->assertEquals(42, $r->key);
        $this->assertEquals(1, $r->before);
        $this->assertEquals(2, $r->after);

        $r->setKey(27);
        $this->assertEquals(27, $r->key);
        $this->assertEquals(null, $r->data);

    }

    /**
     * @test
     */
    public function opaqueData()
    {
        $data = "foo";
        $o = new OpaqueData(self::$treeConf, $data);
        $this->assertEquals($data, $o->data);
        $this->assertEquals($data, $o->dump());

        $o = new OpaqueData(self::$treeConf);
        $o->load($data);
        $this->assertEquals($data, $o->data);
        $this->assertEquals($data, $o->dump());
        $this->assertEquals("<OpaqueData: foo>", $o->represents());



    }
}