<?php


namespace Jinraynor1\BplusTree\Memory\RWLocks;


interface LockInterface
{
    public function acquire();
    public function release();
}