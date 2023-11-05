<?php


namespace Jinraynor1\BplusTree\Memory\RWLocks;


class ReaderLock extends AbstractLock
{

    public function acquire()
    {
        return flock($this->lockFilePointer, LOCK_SH);
    }



}