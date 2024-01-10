<?php


namespace Jinraynor1\BplusTree\BPlusTree;


use Hough\Generators\AbstractGenerator;
use Jinraynor1\BplusTree\BPlusTree;
use Jinraynor1\BplusTree\Exceptions\ValueError;
use Jinraynor1\BplusTree\Helpers\Slice;
use Jinraynor1\BplusTree\Nodes\Node;

class EntryIterator extends AbstractGenerator
{
    /**
     * @var BPlusTree
     */
    private $tree;
    /**
     * @var Slice
     */
    private $slice;
    /**
     * @var Node
     */
    private $node;

    public function __construct(BPlusTree $tree, Slice $slice)
    {

        $this->tree = $tree;
        $this->slice = $slice;


        if (is_null($slice->step())) {
            throw new ValueError('Cannot iterate with a custom step');
        }
        if (!is_null($slice->start()) && !is_null($slice->stop()) and
            strnatcmp($slice->start() , $slice->stop())>=0 ) {
            throw new ValueError('Cannot iterate backwards');
        }

        if (is_null($slice->start())) {
            $this->node = $tree->leftRecordNode();
        } else {
            $this->node = $tree->searchInTree($slice->start(), $tree->rootNode());
        }
        reset($this->node->entries);
    }

    protected function resume($position)
    {
        $entry = current($this->node->entries);

        if ($entry) {
            if (!is_null($this->slice->start()) && strnatcmp($entry->key , $this->slice->start()) < 0) {
                do{
                    next($this->node->entries);
                    $entry = current($this->node->entries);

                }while($entry && strnatcmp($entry->key , $this->slice->start()) < 0);
            }

            if (!is_null($this->slice->stop()) && $entry && strnatcmp($entry->key , $this->slice->stop())>= 0) {
                return null;
            }
        }


        if (!next($this->node->entries)) {
            if ($this->node->nextPage) {
                $this->node = $this->tree->mem->getNode($this->node->nextPage);
                if(!$entry){
                    $entry = reset($this->node->entries);
                    next($this->node->entries);
                }else{
                    reset($this->node->entries);
                }


            } else {
                if (!$entry) {
                    return null;
                } else {
                    return array($position, $entry);
                }
            }

        }
        return array($position, $entry);

    }


}