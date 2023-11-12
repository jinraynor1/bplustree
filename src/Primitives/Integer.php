<?php


namespace Jinraynor1\BplusTree\Primitives;


class Integer
{
    public static function fromBytes($value,$endianness, $unsigned = false)
    {
        $size = strlen($value);

        if ($size == 1) {
            $ret = unpack("c", $value);
        } elseif ($size >=2 && $size <=3) {

            if (!$unsigned && $endianness === true) {  // big-endian
                $ret = unpack("n", $value);
            } else if (!$unsigned && $endianness === false) {  // little-endian
                $ret = unpack("v", $value);
            } else if (!$unsigned && $endianness === null) {  // machine byte order
                $ret = unpack("S", $value);
            }else{ //assume signed machine byte order
                $ret = unpack("s", $value);
            }

        } elseif ($size >= 4 && $size <=7 ) {

            if (!$unsigned && $endianness === true) {  // big-endian
                $ret = unpack("N", $value);
            } else if (!$unsigned && $endianness === false) {  // little-endian
                $ret = unpack("V", $value);
            } else if (!$unsigned && $endianness === null) {  // machine byte order
                $ret = unpack("L", $value);
            }else { //assume signed machine byte order
                $ret = unpack("l", $value);
            }

        } elseif ($size >= 8 ) {
            if (!$unsigned && $endianness === true) {  // big-endian
                $ret = unpack("J", $value);
            } else if (!$unsigned && $endianness === false) {  // little-endian
                $ret = unpack("P", $value);
            } else if (!$unsigned && $endianness === null) {  // machine byte order
                $ret = unpack("Q", $value);
            }else { //assume signed machine byte order
                $ret = unpack("q", $value);
            }
        }else{
            throw new \RuntimeException("Invalid size");
        }


        return (int) $ret[1];
    }

    public static function toBytes($value, $size, $endianness, $unsigned = false)
    {
        $pad = 0;

        if ($size == 1) {
            $format ="c";
        } elseif ($size >=2 && $size <=3 ) {

            if (!$unsigned && $endianness === true) {  // big-endian
                $format = "n";
            } else if (!$unsigned && $endianness === false) {  // little-endian
                $format = "v";
            } else if (!$unsigned && $endianness === null) {  // machine byte order
                $format = "S";
            }else{ //assume signed machine byte order
                $format = "s";
            }
            $pad = $size -2;


        } elseif ($size >= 4 && $size <=7 ) {

            if (!$unsigned && $endianness === true) {  // big-endian
                $format = "N";
            } else if (!$unsigned && $endianness === false) {  // little-endian
                $format = "V";
            } else if (!$unsigned && $endianness === null) {  // machine byte order
                $format = "L";
            }else { //assume signed machine byte order
                $format = "l";
            }

            $pad = $size - 4  ;

        } elseif ($size >= 8 ) {
            if (!$unsigned && $endianness === true) {  // big-endian
                $format = "J";
            } else if (!$unsigned && $endianness === false) {  // little-endian
                $format = "P";
            } else if (!$unsigned && $endianness === null) {  // machine byte order
                $format = "Q";
            }else { //assume signed machine byte order
                $format = "q";
            }
            $pad = $size -8;
        }


        return pack("{$format}a{$pad}",$value,"");




}

}