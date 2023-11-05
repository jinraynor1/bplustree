<?php


namespace Jinraynor1\BplusTree\Memory;


use Jinraynor1\BplusTree\Memory\RWLocks\ReaderLock;
use Jinraynor1\BplusTree\Memory\RWLocks\WriterLock;

class RWLock
{
    private $filename;
    /**
     * @var ReaderLock
     */
    private $readerLock;
    /**
     * @var WriterLock
     */
    private $writerLock;

    public function __construct($filename)
    {

        $this->filename = $filename;
        $lockFilePointer = fopen($filename, 'c');

        $this->readerLock = new ReaderLock($lockFilePointer);
        $this->writerLock = new WriterLock($lockFilePointer);
    }

    /**
     * @return ReaderLock
     */
    public function getReaderLock()
    {
        return $this->readerLock;
    }

    /**
     * @return WriterLock
     */
    public function getWriterLock()
    {
        return $this->writerLock;
    }

    
}