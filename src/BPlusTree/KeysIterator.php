<?php


namespace Jinraynor1\BplusTree\BPlusTree;


use Hough\Generators\AbstractGenerator;
use Jinraynor1\BplusTree\BPlusTree;
use Jinraynor1\BplusTree\Exceptions\ValueError;
use Jinraynor1\BplusTree\Helpers\Slice;
use Jinraynor1\BplusTree\Nodes\Node;

class KeysIterator extends ItemsIterator
{



    protected function resume($position)
    {
        $entry = $this->iterator->current();

        if(!$entry){
            $this->tree->mem->lock->getReaderLock()->release();
            return null;
        }else{

            $this->iterator->next();
            return array($position,$entry->getKey());
        }

    }


}