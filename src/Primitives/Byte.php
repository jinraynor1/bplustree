<?php


namespace Jinraynjor1\BplusTree\Primitives;


class Byte
{
    public static function nullPadding($length,$str = "")
    {
        return pack("a$length", $str) ;
    }

}