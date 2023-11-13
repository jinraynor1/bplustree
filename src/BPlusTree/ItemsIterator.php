<?php


namespace Jinraynor1\BplusTree\BPlusTree;


use Hough\Generators\AbstractGenerator;
use Jinraynor1\BplusTree\BPlusTree;
use Jinraynor1\BplusTree\Exceptions\ValueError;
use Jinraynor1\BplusTree\Helpers\Slice;
use Jinraynor1\BplusTree\Nodes\Node;

class ItemsIterator extends AbstractGenerator
{
    /**
     * @var BPlusTree
     */
    protected $tree;
    /**
     * @var Slice
     */
    protected $slice;
    /**
     * @var Node
     */
    protected $node;
    /**
     * @var EntryIterator
     */
    protected $iterator;

    public function __construct(BPlusTree $tree, Slice $slice)
    {

        $this->tree = $tree;
        $this->slice = $slice;
        $this->tree->mem->lock->getReaderLock()->acquire();

        $this->iterator = $this->tree->iterSlice($slice);


    }
    protected function resume($position)
    {
        $entry = $this->iterator->current();

        if(!$entry){
            $this->tree->mem->lock->getReaderLock()->release();
            return null;
        }else{

            $this->iterator->next();
            return array($entry->getKey(),$this->tree->getValueFromRecord($entry));
        }

    }


}