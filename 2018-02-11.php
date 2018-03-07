<?php

// +----------------------------------------------------------------------
// | 1. sscanf()
// +----------------------------------------------------------------------
// | 1. call_user_func_array($array,$array)  
// +----------------------------------------------------------------------
// | 3. 函数引用传值及静态变量
// +----------------------------------------------------------------------

require './personal/li_func.php';

// 1 
$str = "If you divide 4.45 by 2 you'll get 2";
$format = sscanf($str,"%s %s %s %6f %s %d %s %s %c",$a,$b,$c,$d,$e,$f,$g,$h,$i);// 带3个或以上参数时，逐级分配
dump($format);
var_dump($a,$b,$c,$d,$e,$f,$g,$h,$i);

$format = sscanf($str,"%s %s %s %6f %s %d %s %s %c"); // 2个参数是返回数组
dump($format);

// CI框架里面应用
if(sscanf($RTR->routes['404_override'], '%[^/]/%s', $error_class, $error_method) !== 2)



// call_user_func_array

//  如果传递一个数组给 call_user_func_array()，数组的每个元素的值都会当做一个参数传递给回调函数，数组的 key 回调掉。
//  如果传递一个数组给 call_user_func()，整个数组会当做一个参数传递给回调函数，数字的 key 还会保留住。


function test_callback(){
  $args = func_get_args();
  $num  = func_num_args();
  echo $num."个参数：";
  echo "<pre>";
  dump($args);
  echo "</pre>";
}

$args = array (
  'foo' => 'bar',
  'hello' => 'world',
   0 => 123
);

call_user_func('test_callback', $args);
call_user_func_array('test_callback', $args);