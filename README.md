Bplustree
=========

Port of Nicolas Le Manchet [btree library](https://github.com/NicolasLM/bplustree) (version from Mar 18, 2019) to PHP

An on-disk B+tree for PHP 5.4.

It feels like a dict, but stored on disk. When to use it?

- When the data to store does not fit in memory
- When the data needs to be persisted
- When keeping the keys in order is important

This project is under development: the format of the file may change between versions. Do not use as your primary source
of data.

Quickstart
----------

Install Bplustree with composer::

composer install jinraynor1/bplustree

Create a B+tree index stored on a file and use it with:

```php
    use Jinraynor1\BplusTree\BPlusTree;
    $tree = BPlusTree::createFromArgs(array('filename' => '/tmp/bplustree.db', 'order' => 50));
    $tree->insert(1,'foo');
    $tree->insert(2,'bar');
    var_dump($tree->get(1));
    //string(3) "foo"
    $tree->get(3);
    var_dump($tree->get(3));
    //NULL
    $tree->close();
```

Keys and values
---------------

Keys must have a natural order. Default serializers for string and integer types are provided. For example to index by
string:

```php
    
use Jinraynor1\BplusTree\BPlusTree;
use Jinraynor1\BplusTree\Serializer\StringSerializer;

$tree = BPlusTree::createFromArgs(array(
                                        'filename' => '/tmp/bplustree.db', 
                                        'key_size' => 16,
                                        'serializer' => new StringSerializer()
                                  ));
$tree->insert('foo', b'bar');
$data = iterator_to_array($tree->items());

# $data is:
#
# Array
# (
#     [foo] => bar
# )



```

Values on the other hand are always strings. They can be of arbitrary length, the parameter ``$value_size=128`` defines
the upper bound of value sizes that can be stored in the tree itself. Values exceeding this limit are stored in overflow
pages. Each overflowing value occupies at least a full page.

Iterating
---------

Since keys are kept in order, it is very efficient to retrieve elements in order:

```php
   foreach( $tree->items() as $key=>$value){
        echo sprintf("%d %s\n", $key, $value);
    }
    # prints     
    # 1 'foo'
    # 2 'bar'
```    

It is also possible to iterate over a subset of the tree by giving a slice:

```php
    use Jinraynor1\BplusTree\Helpers\Slice;
    foreach ($tree->items(new Slice($start=0, $stop=10) as $key =>$value ){
        echo sprintf("%d %s\n", $key, $value);
    }
```

Both methods use a generator so they don't require loading the whole content in memory, but copying a slice of the tree
into an array is also possible:

```php
    use Jinraynor1\BplusTree\Helpers\Slice;
    $data = iterator_to_array($tree->items(new Slice(0, 10)));
    print_r($data);
    # prints
    # [[ 1 => 'foo'], [ 2=> 'bar']]
```

Concurrency
-----------

The tree is thread-safe, it follows the multiple readers/single writer pattern.

It is safe to:

- Share an instance of a ``BPlusTree`` between multiple threads

It is NOT safe to:

- Share an instance of a ``BPlusTree`` between multiple processes
- Create multiple instances of ``BPlusTree`` pointing to the same file

Durability
----------

A write-ahead log (WAL) is used to ensure that the data is safe. All changes made to the tree are appended to the WAL
and only merged into the tree in an operation called a checkpoint, usually when the tree is closed. This approach is
heavily inspired by other databases like SQLite.

If tree doesn't get closed properly (power outage, process killed...) the WAL file is merged the next time the tree is
opened.

Performances
------------

Like any database, there are many knobs to finely tune the engine and get the best performance out of it:

- ``order``, or branching factor, defines how many entries each node will hold
- ``page_size`` is the amount of bytes allocated to a node and the length of read and write operations. It is best to
  keep it close to the block size of the disk
- ``cache_size`` to keep frequently used nodes at hand. Big caches prevent the expensive operation of creating Python
  objects from raw pages but use more memory

Some advices to efficiently use the tree:

- Insert elements in ascending order if possible, prefer UUID v1 to UUID v4
- Insert in batch with ``$tree->batchInsert($iterator)`` instead of using
  ``$tree->insert()`` in a loop
- Let the tree iterate for you instead of using ``$tree->get()`` in a loop
- Use ``$tree->checkpoint()`` from time to time if you insert a lot, this will prevent the WAL from growing unbounded
- Use small keys and values, set their limit and overflow values accordingly
- Store the file and WAL on a fast disk

License
-------

MIT
