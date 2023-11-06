<?php

use Jinraynjor1\BplusTree\Helpers\Bisect;
use Jinraynjor1\BplusTree\Memory\File;
use Jinraynor1\BplusTree\BPlusTree;
use Jinraynor1\BplusTree\Helpers\Slice;
use Jinraynor1\BplusTree\Serializer\IntegerSerializer;
use Jinraynor1\BplusTree\Serializer\StringSerializer;

require_once 'vendor/autoload.php';

$filename= "/tmp/test.tree";

$b =  new BPlusTree($filename,  $page_size=4096, $order=4,
    $key_size=40, $value_size=40, $cache_size = 64,$serializer=new IntegerSerializer());


$data = array();
$b->iterSlice(new Slice(null, 10), function ($entry) use (&$data) {
    $data[$entry->key] = $entry->value;
});

var_dump($data);