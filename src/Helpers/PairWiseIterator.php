<?php


namespace Jinraynor1\BplusTree\Helpers;


use Hough\Generators\AbstractGenerator;

class PairWiseIterator extends AbstractGenerator
{
    private $list;

    public function __construct($list)
    {

        $this->list = $list;


    }

    protected function resume($position)
    {
        if($position+1 == count($this->list)){
            return null;
        }

        return array($position, array_slice($this->list,$position,2));
    }
}