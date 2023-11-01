<?php


namespace Jinraynjor1\BplusTree\Primitives;


class Integer
{
    public static function fromBytes($value,$endianness, $unsigned = false)
    {
        $size = strlen($value);

        if ($size == 1) {
            $ret = pack("c", $value);
        } elseif ($size ==2 ) {

            if (!$unsigned && $endianness === true) {  // big-endian
                $ret = unpack("n", $value);
            } else if (!$unsigned && $endianness === false) {  // little-endian
                $ret = unpack("v", $value);
            } else if (!$unsigned && $endianness === null) {  // machine byte order
                $ret = unpack("S", $value);
            }else{ //assume signed machine byte order
                $ret = unpack("s", $value);
            }

        } elseif ($size >= 3 && $size <=7 ) {

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
        if (!is_int($size)) {
            throw new \InvalidArgumentException("Invalid size: '$size'");
        }

        $ret = null;
        $pad = 0;

        if ($size == 1) {
            $ret = pack("c", $value);
        } elseif ($size ==2 ) {

                if (!$unsigned && $endianness === true) {  // big-endian
                    $ret = pack("n", $value);
                } else if (!$unsigned && $endianness === false) {  // little-endian
                    $ret = pack("v", $value);
                } else if (!$unsigned && $endianness === null) {  // machine byte order
                    $ret = pack("S", $value);
                }else{ //assume signed machine byte order
                    $ret = pack("s", $value);
                }

        } elseif ($size >= 3 && $size <=7 ) {

            if (!$unsigned && $endianness === true) {  // big-endian
                $ret = pack("N", $value);
            } else if (!$unsigned && $endianness === false) {  // little-endian
                $ret = pack("V", $value);
            } else if (!$unsigned && $endianness === null) {  // machine byte order
                $ret = pack("L", $value);
            }else { //assume signed machine byte order
                $ret = pack("l", $value);
            }

            $pad = abs(4 -$size) ;

        } elseif ($size >= 8 ) {
            if (!$unsigned && $endianness === true) {  // big-endian
                $ret = pack("J", $value);
            } else if (!$unsigned && $endianness === false) {  // little-endian
                $ret = pack("P", $value);
            } else if (!$unsigned && $endianness === null) {  // machine byte order
                $ret = pack("Q", $value);
            }else { //assume signed machine byte order
                $ret = pack("q", $value);
            }
            $pad = abs(8-$size);
        }

        if($pad)
            $ret.=pack("a$pad","");

        return $ret;
    }

}