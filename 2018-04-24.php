<?php

// +----------------------------------------------------------------------
// | 1. 策略模式
// +----------------------------------------------------------------------
// | 2. 工厂模式
// +----------------------------------------------------------------------

    require './li_func.php';

// 1
    echo "<p><strong>策略模式的。例如：
如果我需要在早晨从家里出发去上班，我可以有几个策略考虑：我可以乘坐地铁，乘坐公交车，走路或其它的途径。每个策略可以得到相同的结果，但是使用了不同的资源。</strong></p>";

    interface parse
    {

    }

    abstract class agent
    { //抽象策略类
        protected $name = '浏览器';
        protected $homepage = __CLASS__ . '主页';

        abstract function PrintPage ($url = '');

        abstract protected function cssParse ();

        abstract protected function jsParse ();
    }

    //用于客户端是IE时调用的类（环境角色）

    class motherAgent extends agent implements parse
    {
        static $rule = 'cso标准';
        public $name;
        public $homepage;

        public function __construct ($name = '')
        {
            $this->name     = $name ?: 'agent';
            $this->homepage = $this->homepage ?: $name . '主页';
        }

        public static function showRule ()
        {
            echo static::$rule;
        }

        function PrintPage ($url = '')
        {
            echo 'get_called_class():显示' . get_called_class () . '<br/>';
            $url or $url = $this->homepage;
            $this->page = $url . '源码';
            $this->cssParse ();
            $this->jsParse ();
            return $this->newpage;
        }

        protected function cssParse ()
        {
            $this->newpage = $this->name . '解析' . $this->page . "css<br/>";
        }

        protected function jsParse ()
        {
            $this->newpage .= $this->name . '解析' . $this->page . "js<br/>";
        }
    }

    //用于google客户端时调用的类（环境角色）

    class ieAgent extends motherAgent
    {
        public $name = 'IE浏览器';
        public $homepage = 'ie主页';

        function PrintPage ($url = 'ie主页')
        {
            return $this->name . '打开' . $this->homepage;
        }
    }

    class googleAgent extends motherAgent
    {
        public $name = 'google浏览器';

        function PrintPage ($url = '')
        {
            return $this->name . '打开' . $url;
        }
    }

    $bro = new Browser ();
    echo $bro->show (new ieAgent(), 'bing.com');
    echo $bro->show (new googleAgent(), '');

// 2
    echo "<p><strong>工厂模式
工厂模式是我们最常用的实例化对象模式，是用工厂方法代替new操作的一种模式。
使用工厂模式的好处是，如果你想要更改所实例化的类名等，则只需更改该工厂方法内容即可，不需逐一寻找代码中具体实例化的地方（new处）修改了。为系统结构提供灵活的动态扩展机制，减少了耦合。</strong></p>";

    class Browser
    { //具体策略角色
        public function show (agent $object, $url = '')
        {
            return $object->PrintPage ($url) . "<br/>";
        }
    }

    /**
     * Class SimpleFactoty 工厂类
     */
    class SimpleFactoty
    {
        // 简单工厂里的静态方法-用于创建对象
        static function create ($agentName)
        {
            return class_exists ($agentName) ? new $agentName() : new class($agentName) extends motherAgent
            {
            };
        }
    }

    echo (new SimpleFactoty())->create ('firefox')->PrintPage ();
    $firefox = (new SimpleFactoty())->create ('firefox');
    $firefox->PrintPage ();