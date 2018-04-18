<?php

// +----------------------------------------------------------------------
// | 1. 字符串比较 strcmp strncmp strcasecmp
// +----------------------------------------------------------------------
// | 2. PHP互换两个变量值的方法(不用第三变量)
// +----------------------------------------------------------------------
// | 3. php闭包实现函数的自调用，也就是实现递归
// +----------------------------------------------------------------------

    require './li_func.php';

// 1

// strcmp() 比较两个字符串。
// strncmp() 比较两个字符串（可指定比较长度）。
// strcasecmp() 比较两个字符串(不区分大小写)。
//    0  - 如果两个字符串相等
//    <0 - 如果 string1 小于 string2
//    >0 - 如果 string1 大于 string2
    echo strcmp ("Hello", "HellO)"); // 1
    echo "<br>";
    echo strcasecmp ("Hello", "HellO"); // 0
    echo "<br>";
    echo strncmp ("Hello", "HellO", 4); // 0

// 2

    /**
     * 双方变量为字符串时，可用交换方法一
     * 使用substr()结合strlen（）两个方法达到交换变量值得目的
     */
    $a = "This is A"; // a变量原始值
    $b = "This is B"; // b变量原始值
    echo '交换之前 $a 的值：' . $a . ', $b 的值：' . $b, '<br>'; // 输出原始值
    /**
     * $b得到$a值详解：
     *  先通过strlen()分别计算出$a和$b中字符串的长度【此时$a是原始$a和$b的合值】
     *  通过strlen($a)-strlen($b)即可得出原始$a的值长度
     *  在通过substr()方法在合并后的$a中从0开始截取到$a的长度，那么即可得到原始$a的值
     * $a得到$b值详解：
     *  由于此刻$b已经是$a的原始值了，而$a合并后的值为原始$a+原始$b的值，故用substr()在$a中从$b(原始$a)长度位置截取，则去的内容则为原始$b，则将$b值付给$a成功
     */
    $a .= $b; // 将$b的值追加到$a中
    $b = substr ($a, 0, (strlen ($a) - strlen ($b)));
    $a = substr ($a, strlen ($b));
    echo '交换之后 $a 的值：' . $a . ', $b 的值：' . $b, '<br>'; // 输出结果值


    $a .= $b; // 将$b的值追加到$a中
    $b = str_replace ($b, "", $a); // 在$a(原始$a+$b)中，将$b替换为空，则余下的返回值为$a
    $a = str_replace ($b, "", $a); // 此时，$b为原始$a值，则在$a(原始$a+$b)中将$b(原始$a)替换为空，则余下的返回值则为原始$b,交换成功
    echo '交换之后 $a 的值：' . $a . ', $b 的值：' . $b, '<br>'; // 输出结果值


    list($b, $a) = array ($a, $b); // list() 函数用数组中的元素为一组变量赋值。了解这个，相信其他的不用我多说了吧
    echo '交换之后 $a 的值：' . $a . ', $b 的值：' . $b, '<br>'; // 输出结果值

    $a = $a ^ $b; // 此刻$a:000000000000000000000000000000000000000000000000000000000000000000000011
    $b = $b ^ $a; // 此刻$b:010101000110100001101001011100110010000001101001011100110010000001000001
    $a = $a ^ $b; // 此刻$a:010101000110100001101001011100110010000001101001011100110010000001000010
    echo '交换之后 $a 的值：' . $a . ', $b 的值：' . $b, '<br>'; // 输出结果值


    $a = $a + $b; // $a $b和值
    $b = $a - $b; // 不解释..
    $a = $a - $b; // 不解释..
    echo '交换之后 $a 的值：' . $a . ', $b 的值：' . $b, '<br>'; // 输出结果值

// 3

//php闭包实现函数的自调用，也就是实现递归
    function closure ($n, $counter, $max)
    {
        //匿名函数，这里函数的参数加&符号是，引址调用参数自己
        $fn = function (&$n, &$counter, &$max = 1) use (&$fn) {//use参数传递的是函数闭包函数自身
            $n++;
            if ($n < $max) {//递归点，也就是递归的条件
                $counter .= $n . '<br />';
                //递归调用自己
                $fn($n, $counter, $max);
            }
            return $counter;
        };//记得这里必须加``;``分号，不加分号php会报错，闭包函数
        /*
        *这里函数closure的返回值就是调用闭包的匿名函数
        *而闭包函数，引用closure函数传进来的参数
        */
        return $fn($n, $counter, $max);

    }

    echo (closure (0, '', 10));

    // 匿名调用父类私有属性
    class Outer
    {
        protected $prop2 = 2;
        private $prop = 1;

        public function func2 ()
        {
            return new class($this->prop) extends Outer
            {
                private $prop3;

                public function __construct ($prop)
                {
                    $this->prop3 = $prop;
                }

                public function func3 ()
                {
                    return $this->prop2 + $this->prop3 + $this->func1 ();
                }
            };
        }

        protected function func1 ()
        {
            return 3;
        }
    }

    echo (new Outer)->func2 ()->func3 ();//6