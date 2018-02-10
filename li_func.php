<?php

// +----------------------------------------------------------------------
// | dump($data,[0|1]); 友好输出，参数2为1时会终止执行
// +----------------------------------------------------------------------
if( ! function_exists('dump') )
{
    function dump($var, $die = 0, $label = null, $flags = 8)
    {
        defined('IS_CLI') or define('IS_CLI', PHP_SAPI == 'cli' ? true : false);
        $label = (null === $label) ? '' : rtrim($label) . ':';
        ob_start();
        var_dump($var);
        $output = ob_get_clean();
        $output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', $output);
        if (IS_CLI) {
            $output = PHP_EOL . $label . $output . PHP_EOL;
        } else {
            if (!extension_loaded('xdebug')) {
                $output = htmlspecialchars($output, $flags);
            }
            $output = '<pre>' . $label . $output . '</pre>';
        }
        if ($die) {
            echo($output);
            die;
        } else {
            echo($output);
        }
    }
}

 
// +----------------------------------------------------------------------
// | console_log($data); 友好输出，控制台输出（数组会转json输出）
// +----------------------------------------------------------------------
if( ! function_exists('console_log') )
{
    function console_log($data)  
    {  
        if (is_array($data) || is_object($data))  
        {  
            echo("<script>console.log('".json_encode($data,JSON_UNESCAPED_UNICODE)."');</script>");  
        }  
        else  
        {  
            echo("<script>console.log('".$data."');</script>");  
        }  
    }
}


// +----------------------------------------------------------------------
// | getClassMethodInfo($class,$method='') 获取对象或里面方法的信息
// +----------------------------------------------------------------------
if( ! function_exists('getClassMethodInfo') )
{
    /*
     * $method->invoke($module); // $module 为实例化的类 $method为实例化的反射方法
     * 获取对象或里面方法的信息
     * @param $class 需带命名空间 $class = __NAMESPACE__?'\\'.__NAMESPACE__.'\\'.$class : $class 
     */
    function getClassMethodInfo($class,$method='') 
    {
        debug_print_backtrace();
        $info = [];
        if($method){
            $func = new \ReflectionMethod($class,$method);
        }else{
            $func = new \ReflectionClass($class);
            $info['methods'] = get_class_methods($class);
            $info['vars'] = get_class_vars($class);
        }
        $info['fileName'] = $func->getFileName(); // 文件路径
        $info['getStartLine'] = $func->getStartLine(); //开始行
        $info['getEndLine'] = $func->getEndLine(); //结束行
        return $info;
    }
}


// +----------------------------------------------------------------------
// | G('begin'); // 记录开始标记位
// +----------------------------------------------------------------------
// | ... 区间运行代码
// +----------------------------------------------------------------------
// | G('end'); // 记录结束标签位
// +----------------------------------------------------------------------
// | echo G('begin','end',6); // 统计区间运行时间 精确到小数后6位 默认为4位
// +----------------------------------------------------------------------
// | echo G('begin','end','m'); // 统计区间内存使用情况
// +----------------------------------------------------------------------
if( ! function_exists('G') )
{
    function G($start,$end='',$dec=4) {
        static $_info       =   array();
        static $_mem        =   array();
        if(is_float($end)) { // 记录时间
            $_info[$start]  =   $end;
        }elseif(!empty($end)){ // 统计时间和内存使用
            if(!isset($_info[$end])) $_info[$end]       =  microtime(TRUE);
            if(MEMORY_LIMIT_ON && $dec=='m'){
                if(!isset($_mem[$end])) $_mem[$end]     =  memory_get_usage();
                return number_format(($_mem[$end]-$_mem[$start])/1024,4).'M';
            }else{
                return number_format(($_info[$end]-$_info[$start]),$dec);
            }

        }else{ // 记录时间和内存使用
            $_info[$start]  =  microtime(TRUE);
            if(MEMORY_LIMIT_ON) $_mem[$start]           =  memory_get_usage();
        }
        return null;
    }
}


