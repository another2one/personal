<?php

// +----------------------------------------------------------------------
// | 1. ReflectionClass类 和 ReflectionMethod类
// +----------------------------------------------------------------------
// | 2. 函数参数
// +----------------------------------------------------------------------

    require './li_func.php';

// 1
    echo "<h1>******  1</h1>";

    class Person
    {
        /**
         * type=primary_autoincrement
         */
        protected $id = 0;
        /**
         * type=varchar length=255 null
         */
        protected $name;
        /**
         * type=text null
         */
        protected $biography;
        /**
         * For the sake of demonstration, we"re setting this private
         */
        private $_allowDynamicAttributes = false;

        public function getId ()
        {
            return $this->id;
        }

        public function setId ($v)
        {
            $this->id = $v;
        }

        public function getName ()
        {
            return $this->name;
        }

        public function setName ($v)
        {
            $this->name = $v;
        }

        public function getBiography ()
        {
            return $this->biography;
        }

        public function setBiography ($v)
        {
            $this->biography = $v;
        }
    }

    $class    = new ReflectionClass('Person'); // 建立 Person这个类的反射类
    $instance = $class->newInstanceArgs (); // 相当于实例化Person 类
    //  参数[ReflectionProperty::IS_STATIC  ReflectionProperty::IS_PUBLIC ReflectionProperty::IS_PROTECTED ReflectionProperty::IS_PRIVATE]
    $properties = $class->getProperties ();
    dump ($properties); // 获取属性所有 ReflectionProperty::IS_STATIC static属性
    foreach ($properties as $property) {
        if ($property->isProtected ()) {
            echo $property->getDocComment () . "<br/>";
        }
    }
    dump ($class->getMethods (ReflectionMethod::IS_PUBLIC));       //来获取到类的所有methods。 ReflectionMethod::IS_PUBLIC 参数参考getProperties
    dump ($class->hasMethod ('setId'));  //是否存在test方法

    // 执行类的方法：
    $instance->getName (); // 执行Person 里的方法getName
// 或者：
    $method = $class->getmethod ('getName'); // 获取Person 类中的getName方法
    $method->invoke ($instance);    // 执行getName 方法
// 或者：
    $method = $class->getmethod ('setName'); // 获取Person 类中的setName方法
    $method->invokeArgs ($instance, array ('snsgou.com'));

    //ReflectionMethod
    $method = new ReflectionMethod('Person', 'setBiography');
    if ($method->isPublic () && !$method->isStatic ()) {
        echo 'Action is right';
    }
    echo $method->getNumberOfParameters (); // 参数个数
    dump ($method->getParameters ()); // 参数对象数组

// 2
    echo "<h1>******  2</h1>";

    function add ($a, $b)
    {
        return $a + $b;
    }

    echo add (...[1, 2]) . "<br/>";

    $a = [1, 2, 3];
    echo add (...$a);

    function sum (...$numbers)
    {
        $acc = 0;
        foreach ($numbers as $n) {
            $acc += $n;
        }
        return $acc;
    }

    echo sum (1, 2, 3, 4);

    function noparam ($a)
    {
        dump (func_get_args ());
    }

    noparam (1, 3, 5);

    $a = function () {
        dump (func_get_args ());
    };

    $a(range (2, 19));

    function total_intervals ($unit, DateInterval ...$intervals)
    {
        $time = 0;
        foreach ($intervals as $interval) {
            $time += $interval->$unit;
        }
        return $time;
    }

    $a = new DateInterval('P1D');
    $b = new DateInterval('P5D');
    echo total_intervals ('d', $a, $b) . ' days';

    // But you can do this (PHP 7.1+):
    //    function foo(?string $bar) {
    //        //...
    //    }
    //
    //    foo(); // Fatal error
    //    foo(null); // Okay
    //    foo('Hello world'); // Okay