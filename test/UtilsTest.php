<?php


use Jinraynor1\BplusTree\Helpers\PairWiseIterator;
use PHPUnit\Framework\TestCase;
use Jinraynor1\BplusTree\Helpers\SliceIterator;
class UtilsTest extends TestCase
{

    /**
     * @test
     */
    public function pairwise()
    {
        $i = new PairWiseIterator(array(0, 1, 2, 3, 4));
        $data = iterator_to_array($i);

        $this->assertTrue(current($data) == array(0, 1));
        $this->assertTrue(next($data) == array(1, 2));
        $this->assertTrue(next($data) == array(2, 3));
        $this->assertTrue(next($data) == array(3, 4));

    }
    /**
     * @test
     */
    public function iterSlice()
    {
        $iterator = new SliceIterator("12345678", 3);
        $data = iterator_to_array($iterator,true);


        $this->assertTrue($data["123"] == false);
        $this->assertTrue($data["456"] == false);
        $this->assertTrue($data["78"] == true);

        $this->assertCount(3,$data);

        $iterator = new SliceIterator("123456", 3);
        $data = iterator_to_array($iterator,true);

        $this->assertTrue($data["123"] == false);
        $this->assertTrue($data["456"] == true);

        $this->assertCount(2,$data);


    }
}