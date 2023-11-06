<?php


namespace Jinraynor1\BplusTree\Helpers;

use Hough\Generators\AbstractGenerator;

class SliceIterator extends AbstractGenerator

{
    private $value;
    private $step;
    /**
     * @var false|float
     */
    private $parts;

    public function __construct($value, $step)
    {

        $this->value = $value;
        $this->step = $step;
        $this->parts = ceil(strlen($value)/$step);
    }


    protected function resume($position)
    {

        if($position <  $this->parts){
            $value = substr($this->value,$position*$this->step,$this->step);
            $isLast = $position+1 == $this->parts;
            return array($value,$isLast);
        }

        return null;
    }
}