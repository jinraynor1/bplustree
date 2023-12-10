<?php

use Jinraynjor1\BplusTree\Helpers\Bisect;
use Jinraynjor1\BplusTree\Memory\File;
use Jinraynor1\BplusTree\BPlusTree;
use Jinraynor1\BplusTree\Helpers\Slice;
use Jinraynor1\BplusTree\Serializer\IntegerSerializer;
use Jinraynor1\BplusTree\Serializer\StringSerializer;

require_once 'vendor/autoload.php';

$filename= "/tmp/test.tree";

$b =  new BPlusTree($filename,  $page_size=4096, $order=10,
    $key_size=8, $value_size=200, $cache_size = 64,$serializer=new StringSerializer());

#$filename2= "/tmp/test.tree2";

#$b2 =  new BPlusTree($filename,  $page_size=4096, $order=10,
 #   $key_size=40, $value_size=40, $cache_size = 64,$serializer=new StringSerializer());

$start = microtime(true);

#var_dump($b->get(0));die;
#$lastI= null;
foreach ($b->items() as $k=>$i){

    $val = $b->get($k);
    if(!trim($val)){
        echo("invalid value at $k is $val");
        die;
    }
    echo "$k => $val\n";
  #  $lastI++;
  #  if($lastI-$k !=1){
   #     die("invalid $lastI , $k\n");
   # }

   #echo "$k = $i\n";
#    $lastI = $i;
}
#var_dump($lastI);
#var_dump($lastI);
#$a = $b->items();
#var_dump(($b->items2()));
#var_dump(iterator_to_array($b->iterSlice2(Slice::createEmpty())));
#foreach ($b->iterSlice2(Slice::createEmpty()) as $i){
   # var_dump($i->value);
#}
#$b->iterSlice(new Slice(10, 100), function ($entry) use (&$data) {
#    $data[$entry->key] = $entry->value;
#});
$end  = microtime(true);
var_dump($end -$start);

var_dump(memory_get_peak_usage());
