<?php


namespace Jinraynor1\BplusTree\Primitives;
use RuntimeException;

class Integer
{
    public static function fromBytes($value)
    {
        $size = strlen($value);

        if ($size == 1) {
            $ret = unpack("c", $value);
        } elseif ($size >=2 && $size <=3) {
            $ret = unpack("v", $value);

        } elseif ($size >= 4 && $size <=7 ) {
            $ret = unpack("V", $value);

        } elseif ($size >= 8 ) {

            list($lower, $higher ) = array_values(unpack('V2', $value));
            return (int) $higher << 32 | $lower;

        }else{
            throw new RuntimeException("Invalid size");
        }


        return (int) $ret[1];
    }

    public static function toBytes($value, $size)
    {
        $pad = 0;
        $format = null;
        if ($size == 1) {
            $format ="c";
        } elseif ($size >=2 && $size <=3 ) {
            $format = "v";
            $pad = $size -2;

        } elseif ($size >= 4 && $size <=7 ) {

            $format = "V";
            $pad = $size - 4  ;

        } elseif ($size >= 8 ) {
            $highMap = 0xffffffff00000000;
            $lowMap = 0x00000000ffffffff;
            $higher = ($value & $highMap) >>32;
            $lower = $value & $lowMap;
            return pack('VV', $lower, $higher );
        }


        return pack("{$format}a{$pad}",$value,"");




}

}