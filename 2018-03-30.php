<?php

// +----------------------------------------------------------------------
// | 1. new static()与new self()的区别异同分析
// +----------------------------------------------------------------------
// | 2. CI model调取父类方法
// +----------------------------------------------------------------------

    require './li_func.php';

// 1

    class A
    {
        public static function get_self ()
        {
            return new self();
        }

        public static function get_static ()
        {
            return new static();
        }
    }

    class B extends A
    {
    }

    echo get_class (B::get_self ()); // A
    echo get_class (B::get_static ()); // B
    echo get_class (A::get_static ()); // A

// array_key_exists(key,array);	函数检查某个数组中是否存在指定的键名，如果键名存在则返回 true，如果键名不存在则返回 false。

    $a = array ("Volvo" => "XC90", "BMW" => "X5");
    dump (array_key_exists ("Volvo", $a));
    $a = array ("Volvo", "BMW");
    dump (array_key_exists (0, $a));

// in_array(search,array,type);	函数搜索数组中是否存在指定的值,  type 参数被设置为 TRUE，则搜索区分大小写。

    in_array (strtolower ($value), array_map ('strtolower', $array)); // 不区分大小写的匹配

// 2

    $this->db = $this->load->database ('lvpeng', true);

    $new_data[$id_field] = $id;
    $this->load->model ($model);
    $this->{$model}->db->set ('praise_num', 'praise_num+1', false);
    $share_id && $this->{$model}->db->set ('popularity', 'popularity+1', false);
    $this->{$model}->save ($new_data);
