<?php


namespace Jinraynor1\BplusTree\Helpers;


class Slice
{
    private $start;
    private $stop;
    private $step;

    public function __construct($start, $stop, $step = 1)
    {

        $this->start = $start;
        $this->stop = $stop;
        $this->step = $step;
    }

    public static function createEmpty()
    {
        return new self(null,null);
    }

    /**
     * @return mixed
     */
    public function start()
    {
        return $this->start;
    }

    /**
     * @return mixed
     */
    public function stop()
    {
        return $this->stop;
    }

    /**
     * @return mixed
     */
    public function step()
    {
        return $this->step;
    }



}