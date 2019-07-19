<?php

// +----------------------------------------------------------------------
// | 1. Iterator
// +----------------------------------------------------------------------
// | 2. yield 大数据分段读取，不需要一次加载到内存
// +----------------------------------------------------------------------
// | 3. array_reduce 用回调函数迭代地将数组简化为单一的值
// +----------------------------------------------------------------------

    require './li_func.php';

// 1

/**
    Iterator extends Traversable {
        abstract public current ( void ) : mixed
        abstract public key ( void ) : scalar
        abstract public next ( void ) : void
        abstract public rewind ( void ) : void
        abstract public valid ( void ) : bool
    }
 */

 class MyIterator implements Iterator
 {

    public $position = 0;
    public $data = [];
    public $count;

    public function __construct(array $array)
    {
        $this->position = 0;
        $this->data = $array;
        $this->count = count($array);
    }

    /**
     * 当前值
     *
     * @return mix
     */
    public function current()
    {
        return $this->data[$this->position];
    }

    /**
     * 当前键
     *
     * @return void
     */
    public function key()
    {
        return $this->position;
    }

    /**
     * 下一个元素
     *
     * @return void
     */
    public function next()
    {
        $this->position++;
    }

    /**
     * 重置
     *
     * @return void
     */
    public function rewind()
    {
        $this->position = 0;
    }

    /**
     * 是否合法
     *
     * @return boolean
     */
    public function valid() : bool
    {
        return ($this->position < $this->count) ? true  : false ;
    }
 }

 $arr = new MyIterator([5, 3, 55]);
 foreach ($arr as $key => $value) {
     echo "key is {$key}, value is {$value}" . PHP_EOL;
 }


 // 2

function yieldReadFile($filename)
{
    if(!is_file($filename))
        die($filename . 'is not a file');

    $file = fopen($filename, 'r');

    if(flock($file, LOCK_SH))
    {
        while(feof($file) === false)
        {
            yield fgets($file) . PHP_EOL;
        }
        flock($file,LOCK_UN);
    }
    else 
    {
        die($filename . 'is Locked');
    }
}

foreach(yieldReadFile('./2018-05-29.php') as $v)
{
    echo $v;
}

// 3 

echo array_reduce([2, 5, 56], function ($a, $b)
{
    echo "\$a = $a, \$b = $b" . PHP_EOL;
    return $a + $b;
}, 'yes ! good');