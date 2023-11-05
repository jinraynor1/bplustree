<?php


namespace Jinraynor1\BplusTree\Memory\RWLocks;


abstract class AbstractLock implements LockInterface
{
    protected $lockFilePointer;

    public function __construct($lockFilePointer)
    {
        $this->lockFilePointer = $lockFilePointer;
    }

    public function release()
    {
        return flock($this->lockFilePointer, LOCK_UN);
    }
}