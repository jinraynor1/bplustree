<?php


use Jinraynor1\BplusTree\Exceptions\ReachedEndOfFile;
use Jinraynor1\BplusTree\Exceptions\ValueError;
use Jinraynor1\BplusTree\Helpers\System;
use Jinraynor1\BplusTree\Memory\File;
use Jinraynor1\BplusTree\Memory\FileMemory;
use Jinraynor1\BplusTree\Memory\RWLock;
use Jinraynor1\BplusTree\Memory\RWLocks\ReaderLock;
use Jinraynor1\BplusTree\Memory\RWLocks\WriterLock;
use Jinraynor1\BplusTree\Memory\WAL;
use Jinraynor1\BplusTree\Nodes\FreeListNode;
use Jinraynor1\BplusTree\Nodes\LeafNode;
use Jinraynor1\BplusTree\Serializer\IntegerSerializer;
use Jinraynor1\BplusTree\TreeConf;
use malkusch\phpmock\MockBuilder;

class MemoryTest extends \PHPUnit\Framework\TestCase
{

    /**
     * @var TreeConf
     */
    private static $treeConf;
    /**
     * @var LeafNode
     */
    private static $node;

    public function setUp()
    {
        self::$treeConf = new TreeConf(4096, 4, 16, 16, new IntegerSerializer());
        self::$node = new LeafNode(self::$treeConf, null, 3);

        if (!defined('FILENAME'))
            define('FILENAME', '/tmp/bplustree-testfile.index');

        if (file_exists(FILENAME))
            unlink(FILENAME);

        if (file_exists(FILENAME . '-wal'))
            unlink(FILENAME . '-wal');

    }


    public function tearDown()
    {
        if (file_exists(FILENAME))
            unlink(FILENAME);

        if (file_exists(FILENAME . '-wal'))
            unlink(FILENAME . '-wal');
    }

    /**
     * @test
     */
    public function fileMemoryNode()
    {

        $mem = new FileMemory(FILENAME, self::$treeConf);

        try {
            $mem->getNode(3);
            $this->assertFalse(true);
        } catch (ReachedEndOfFile $e) {
            $this->assertTrue(true);
        }

        $mem->setNode(self::$node);
        assert(self::$node === $mem->getNode(3));

        $mem->close();
    }

    /**
     * @test
     */
    public function fileMemoryMetadata()
    {
        $mem = new FileMemory(FILENAME, self::$treeConf);
        try {
            $mem->getMetadata();
            $this->assertFalse(true);
        } catch (ValueError $e) {
            $this->assertTrue(true);
        }


        $mem->setMetadata(6, self::$treeConf);
        $this->assertTrue($mem->getMetadata() == array(6, self::$treeConf));
    }

    /**
     * @test
     */
    public function fileMemoryNextAvailablePage()
    {
        $mem = new FileMemory(FILENAME, self::$treeConf);
        foreach (range(1, 100) as $i) {
            $this->assertTrue($mem->nextAvailablePage() == $i);
        }
    }

    /**
     * @test
     */
    public function fileMemoryFreelist()
    {
        $mem = new FileMemory(FILENAME, self::$treeConf);
        $this->assertTrue($mem->nextAvailablePage() == 1);
        $this->assertTrue($mem->traverseFreeList() == array(null, null));

        $mem->delPage(1);
        $this->assertTrue($mem->traverseFreeList() == array(
                null, new FreelistNode(self::$treeConf, null, 1, null)
            ));
        $this->assertTrue($mem->nextAvailablePage() == 1);
        $this->assertTrue($mem->traverseFreeList() == array(null, null));

        $mem->delPage(1);
        $mem->delPage(2);
        $this->assertTrue($mem->traverseFreeList() == array(
                new FreelistNode(self::$treeConf, null, 1, 2),
                new FreelistNode(self::$treeConf, null, 2, null)
            ));
        $mem->delPage(3);
        $this->assertTrue($mem->traverseFreeList() == array(
                new FreelistNode(self::$treeConf, null, 2, 3),
                new FreelistNode(self::$treeConf, null, 3, null)
            ));

        $this->assertTrue($mem->popFromFreelist() == 3);
        $this->assertTrue($mem->popFromFreelist() == 2);
        $this->assertTrue($mem->popFromFreelist() == 1);
        $this->assertTrue(is_null($mem->popFromFreelist()));
    }

    /**
     * @test
     */
    public function openFileInDir()
    {

        try {
            File::open_file_in_dir('/foo/bar/does/not/exist');
            $this->assertTrue(false);
        } catch (ValueError $e) {
            $this->assertTrue(true);
        }

        # Create file and re-open
        foreach (range(1, 2) as $_) {
            list($file_fd, $dir_fd) = File::open_file_in_dir(FILENAME);

            $this->assertTrue(is_resource($file_fd));
            fclose($file_fd);


            if (System::isWindows()) {
                $this->assertNull($dir_fd);
            } else {
                $this->assertNull($dir_fd);
                # No native support to open dir in PHP
                #$this->assertTrue(is_resource($dir_fd));
                #fclose($dir_fd);
            }

        }
    }

    /**
     * @test
     */
    public function writeToFileMultiTimes()
    {
        $builder = new MockBuilder();
        $builder->setNamespace("Jinraynor1\\BplusTree\\Memory")
            ->setName("fwrite")
            ->setFunction(
                function ($fd, $data, $length = null) {
                    if (strlen($data) > 5)
                        return 5;
                    else
                        return strlen($data);
                }
            );
        $mock = $builder->build();
        $mock->enable();
        $fd = fopen(FILENAME, 'w');
        File::write_to_file($fd, null, 'abcdefg');
        $mock->disable();
    }

    /**
     * @test
     */
    public function openFileInDirWindows()
    {
        $builder = new MockBuilder();
        $builder->setNamespace("Jinraynor1\\BplusTree\\Helpers")
            ->setName("is_windows")
            ->setFunction(function () {
                return true;
            });
        $mock = $builder->build();
        $mock->enable();
        list($file_fd, $dir_fd) = File::open_file_in_dir(FILENAME);
        $mock->disable();
        $this->assertTrue(is_resource($file_fd));
        fclose($file_fd);
        $this->assertNull($dir_fd);
    }

    private function provideFileMemoryWriteTransaction($mem = null)
    {
        if (!$mem)
            $mem = new FileMemory(FILENAME, self::$treeConf);

        $mem->lock = $this->getMockBuilder(RWLock::class)->disableOriginalConstructor()->getMock();
        $mockWriter = $this->getMockBuilder(WriterLock::class)->disableOriginalConstructor()->getMock();
        $mockReader = $this->getMockBuilder(ReaderLock::class)->disableOriginalConstructor()->getMock();
        $mem->lock->method('getWriterLock')->willReturn($mockWriter);
        $mem->lock->method('getReaderLock')->willReturn($mockReader);

        return array($mem, $mockWriter, $mockReader);

    }

    /**
     * @test
     */
    public function fileMemoryWriteTransaction()
    {
        /**
         * @var $mem FileMemory
         */
        list($mem, $mockWriter, $mockReader) = $this->provideFileMemoryWriteTransaction();
        $this->assertTrue($mem->wal->notCommitedPages == array());
        $this->assertTrue($mem->wal->committedPages == array());

        $mockWriter->expects($this->exactly(1))->method('acquire');
        $mockWriter->expects($this->exactly(1))->method('release');
        $mockReader->expects($this->never())->method('acquire');

        $that = $this;
        $mem->writeTransaction(function () use ($mem, $that) {
            $mem->setNode(self::$node);
            $that->assertTrue($mem->wal->notCommitedPages == array(3 => 9));
            $that->assertTrue($mem->wal->committedPages == array());

        });

        $that->assertTrue($mem->wal->notCommitedPages == array());
        $that->assertTrue($mem->wal->committedPages == array(3 => 9));


        list($mem, $mockWriter, $mockReader) = $this->provideFileMemoryWriteTransaction($mem);

        $mockReader->expects($this->exactly(1))->method('acquire');
        $mockWriter->expects($this->never())->method('acquire');
        $that = $this;
        $mem->readTransaction(function () use ($mem, $that) {
            $that->assertEquals(self::$node, $mem->getNode(3));
        });

    }

    /**
     * @test
     */
    public function fileMemoryWriteTransactionError()
    {
        /**
         * @var $mem FileMemory
         */
        list($mem, $mockWriter, $mockReader) = $this->provideFileMemoryWriteTransaction();
        $mem->cache->put(424242, self::$node);

        $that = $this;

        $mockWriter->expects($this->exactly(1))->method('acquire');
        $mockWriter->expects($this->exactly(1))->method('release');

        $mem->writeTransaction(function () use ($mem, $that) {
            $mem->setNode(self::$node);

            $that->assertTrue($mem->wal->notCommitedPages == array(3 => 9));
            $that->assertTrue($mem->wal->committedPages == array());
            throw new ErrorException("Foo");
        });

        $that->assertTrue($mem->wal->notCommitedPages == array());
        $that->assertTrue($mem->wal->committedPages == array());

        $that->assertNull($mem->cache->get(424242));


    }

    /**
     * @test
     */
    public function fileMemoryRepr()
    {
        $mem = new FileMemory(FILENAME, self::$treeConf);
        $this->assertTrue($mem->represents() == sprintf('<FileMemory: %s>', FILENAME));
        $mem->close();
    }


    /**
     * @test
     */
    public function walCreateReopenUncommitted()
    {
        $wal = new WAL(FILENAME, 64);
        $wal->setPage(1, str_repeat("1", 64));
        $wal->commit();
        $wal->setPage(2, str_repeat("2", 64));
        $this->assertTrue($wal->getPage(1) == str_repeat("1", 64));
        $this->assertTrue($wal->getPage(2) == str_repeat("2", 64));

        $wal = new WAL(FILENAME, 64);
        $this->assertTrue($wal->getPage(1) == str_repeat("1", 64));
        $this->assertNull($wal->getPage(2));

    }

    /**
     * @test
     */
    public function walRollback()
    {
        $wal = new WAL(FILENAME, 64);
        $wal->setPage(1, str_repeat("1", 64));
        $wal->commit();
        $wal->setPage(2, str_repeat("2", 64));
        $this->assertTrue($wal->getPage(1) == str_repeat("1", 64));
        $this->assertTrue($wal->getPage(2) == str_repeat("2", 64));

        $wal->rollback();
        $this->assertTrue($wal->getPage(1) == str_repeat("1", 64));
        $this->assertNull($wal->getPage(2));
    }

    /**
     * @test
     */
    public function walCheckpoint()
    {
        $wal = new WAL(FILENAME, 64);
        $wal->setPage(1, str_repeat("1", 64));
        $wal->commit();
        $wal->setPage(2, str_repeat("2", 64));

        $that = $this;
        $wal->checkpoint(function ($page, $pageData) use ($that) {
            $that->assertTrue($page == 1);
            $that->assertTrue($pageData == str_repeat("1", 64));
        });


        #with pytest.raises(ValueError):
        try {
            $wal->setPage(3, str_repeat("3", 64));
            $this->assertTrue(false);
        } catch (ValueError $e) {
            $this->assertTrue(true);
        }

        $this->assertTrue(!file_exists(FILENAME . '-wal'));
    }
}