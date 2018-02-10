<?php

// +----------------------------------------------------------------------
// | 1. __sleep , __wakeup , 分别在对象序列化及反序列化时调用
// +----------------------------------------------------------------------
// | 2. php session序列化漏洞
// +----------------------------------------------------------------------
// | 3. php 匿名函数及匿名类的使用
// +----------------------------------------------------------------------
// | 4. php7 新特性 http://php.net/manual/zh/migration71.new-features.php
// +----------------------------------------------------------------------
// | 5. Closure 匿名函数绑定 https://www.cnblogs.com/yjf512/p/4421289.html
// +----------------------------------------------------------------------

require './personal/li_func.php';

class Connection {
    protected $link;
    private $server, $username, $password, $db;
     
    public function __construct($server, $username, $password, $db)
    {
        $this->server = $server;
        $this->username = $username;
        $this->password = $password;
        $this->db = $db;
        $this->connect();
        echo __METHOD__;
    }
     
    private function connect()
    {
        $this->link = mysqli_connect($this->server, $this->username, $this->password,$this->db);
    }
     
    public function __sleep()
    {
        echo __METHOD__;
        return array('server', 'username', 'password', 'db' );
    }

     public function query()
    {
        echo __METHOD__;
    }
     
    public function __wakeup()
    {
        $this->db = 'test1'; // 更改数据库
        $this->connect();
        echo __METHOD__;
    }
}

// 1

$a = serialize(new Connection('localhost','root','root','test'));
dump($a);
$b = unserialize($a);
dump($b);
$b->query();

// 2 

// session.save_path="D:\xampp\tmp"    表明所有的session文件都是存储在xampp/tmp下
// session.save_handler=files          表明session是以文件的方式来进行存储的
// session.auto_start=0                表明默认不启动session
// session.serialize_handler=php       表明session的默认序列话引擎使用的是php序列话引擎

// php_binary:存储方式是，键名的长度对应的ASCII字符+键名+经过serialize()函数序列化处理的值
// php:存储方式是，键名+竖线+经过serialize()函数序列处理的值
// php_serialize(php>5.5.4):存储方式是，经过serialize()函数序列化处理的值

// PHP在反序列化存储的$_SESSION数据时使用的引擎和序列化使用的引擎不一样，会导致数据无法正确第反序列化

// 3 

$func = function($a){
    echo $a;
};

$inner = function($a){
    echo $a;
    return $a;
};
$func($inner('lizhi'));

interface Logger { 
   public function log(string $msg); 
} 
 
class Application { 
   private $logger; 
   public function getLogger(): Logger { 　　// php7 可用
      return $this->logger; 
   } 
 
   public function setLogger(Logger $logger) { 
      $this->logger = $logger; 
   }   
} 
 
$app = new Application; 
 
// 使用 new class 创建匿名类 
$app->setLogger(new class implements Logger { 
   public function log(string $msg) { 
      print($msg); 
   } 
}); 
 
$app->getLogger()->log("我的第一条日志"); 

// 4 

class A {

    public $base = 100;
}

class B {
    private $base = 1000;
}

class C {
    private static $base = 10000;
}

$f = function () {
    return $this->base + 3;
};

$sf = static function() {
    return self::$base + 3;
};


$a = Closure::bind($f, new A);
print_r($a());

echo PHP_EOL;

$b = Closure::bind($f, new B , 'B');
print_r($b());

echo PHP_EOL;

$c = $sf->bindTo(null, 'C');
print_r($c());

echo PHP_EOL;