<?php

// +----------------------------------------------------------------------
// | 1. 数组查找
// +----------------------------------------------------------------------
// | 2. CI model调取父类方法
// +----------------------------------------------------------------------
// | 3. svn merge 和 分支发布
// +----------------------------------------------------------------------

require './personal/li_func.php';

// 1

// array_search(value,array,strict);   在数组中搜索某个键值，并返回对应的键名 strict 被指定为 true，则只有在数据类型和值都一致时才返回相应元素的键名。
$a=array("a"=>"red","b"=>"green","c"=>"blue");
echo array_search("red",$a);

// array_key_exists(key,array);	函数检查某个数组中是否存在指定的键名，如果键名存在则返回 true，如果键名不存在则返回 false。

$a=array("Volvo"=>"XC90","BMW"=>"X5");
dump(array_key_exists("Volvo",$a));
$a=array("Volvo","BMW");
dump(array_key_exists(0,$a));

// in_array(search,array,type);	函数搜索数组中是否存在指定的值,  type 参数被设置为 TRUE，则搜索区分大小写。

in_array(strtolower($value),array_map('strtolower',$array)); // 不区分大小写的匹配

// 2

$this->db = $this->load->database('lvpeng',true);

$new_data[$id_field] = $id; 
$this->load->model($model);
$this->{$model}->db->set('praise_num','praise_num+1',false);
$share_id && $this->{$model}->db->set('popularity','popularity+1',false);
$this->{$model}->save($new_data);
