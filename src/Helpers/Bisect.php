<?php


namespace Jinraynor1\BplusTree\Helpers;
use Exception;
use Jinraynor1\BplusTree\Entries\ComparableEntry;

class Bisect
{
    /**
     * @param array $sortedArray
     * @param $value
     * @param int $leftKey
     * @param int|null $rightKey
     * @return int
     * @throws Exception
     */
    public static function left(array $sortedArray, ComparableEntry $value,  $leftKey = 0,  $rightKey = null)
    {
        if(empty($sortedArray))
            return 0;

        self::checkArrayKeys($sortedArray);


        $arraySize = \count($sortedArray);
        $rightKey = $rightKey ?$rightKey: $arraySize - 1;

        if ($value->less( $sortedArray[$leftKey])) {
            return 0;
        }

        if ($value->equals( $sortedArray[$rightKey])) {
            return $arraySize-1;
        }

        if ($value->greater( $sortedArray[$rightKey])) {
            return $arraySize;
        }

        while ($leftKey < $rightKey) {
            $middle = (int) (($leftKey + $rightKey) / 2);

            if ($value->greater($sortedArray[$middle])) {
                $leftKey = $middle + 1;
            } else {
                $rightKey = $middle;
            }
        }

        return $rightKey;
    }

    /**
     * @param array $sortedArray
     * @param $value
     * @param int $leftKey
     * @param int|null $rightKey
     * @return int
     * @throws Exception
     */
    public static function right(array $sortedArray, ComparableEntry $value,  $leftKey = 0,  $rightKey = null)
    {
        if(empty($sortedArray))
            return 0;

        self::checkArrayKeys($sortedArray);

        $arraySize = count($sortedArray);

        $rightKey = $rightKey ?$rightKey: $arraySize - 1;

        if ($value->less($sortedArray[$leftKey])) {
            return 0;
        }

        if ($value->equals( $sortedArray[$rightKey])) {
            return $arraySize-1;
        }

        if ($value->greater(  $sortedArray[$rightKey])) {
            return $arraySize;
        }

        while ($leftKey < $rightKey) {
            $middle = (int) (($leftKey + $rightKey) / 2);

            if ($value->less( $sortedArray[$middle])) {
                $rightKey = $middle;
            } else {
                $leftKey = $middle + 1;
            }
        }

        return $rightKey;
    }

    /**
     * @param array $sortedArray
     * @throws Exception
     */
    private static function checkArrayKeys(array $sortedArray)
    {
        if ($sortedArray !== array_values($sortedArray)) {
            throw new Exception('Array keys must be sorted numerically.');
        }
    }
}