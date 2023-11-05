<?php


namespace Jinraynor1\BplusTree\Memory\RWLocks;


class WriterLock extends AbstractLock
{
    public function acquire()
    {
        return flock($this->lockFilePointer, LOCK_EX);
    }
}