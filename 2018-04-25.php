<?php

// +----------------------------------------------------------------------
// | 1. php 代码执行  https://www.2cto.com/kf/201404/290863.html
// +----------------------------------------------------------------------
// | 2. xdebug
// +----------------------------------------------------------------------

    require './li_func.php';

// 1
//      1.Scanning(Lexing) ,将PHP代码转换为语言片段(Tokens)
//      2.Parsing, 将Tokens转换成简单而有意义的表达式
//      3.Compilation, 将表达式编译成Opocdes
//      4.Execution, 顺次执行Opcodes，每次一条，从而实现PHP脚本的功能。
//
//
//  注1：Opcode是一种PHP脚本编译后的中间语言，就像Java的ByteCode,或者.NET的MSL
//  注2：现在有的Cache比如APC,可以使得PHP缓存住Opcodes，这样，每次有请求来临的时候，就不需要重复执行前面3步，从而能大幅的提高PHP的执行速度。

    /**********************    1.Scanning(Lexing) ,将PHP代码转换为语言片段(Tokens)    ***************************/
    $phpcode = <<<PHPCODE
<?php
    $arr = [1,'lizhi',3]; // 注释
    $str = '123';
    $int = 44;
    echo $int;
?>
PHPCODE;
// $tokens = token_get_all($phpcontent);
// print_r($tokens);
    $tokens = token_get_all ($phpcode); // 将一段PHP代码 Scanning成Tokens
    foreach ($tokens as $key => $token) {
        $tokens[$key][0] = token_name ($token[0]); // token_name函数将解析器代号修改成了符号名称说明
    }
    dump ($tokens); // 1、Token ID解释器代号 (也就是在Zend内部的改Token的对应码，比如,T_ECHO,T_STRING)    2、源码中的原来的内容     3、该词在源码中是第几行。

    /**********************    2.Parsing, 将Tokens转换成简单而有意义的表达式    ***************************/

//    然后将剩余的Tokens转换成一个一个的简单的表达式
//
//      1.echo a constant string
//      2.add two numbers together
//      3.store the result of the prior expression to a variable
//      4.echo a variable

    /**********************    3. Compilation, 将表达式编译成Opocdes    ***************************/

    //PHP有三种方式来进行opcode的处理:CALL，SWITCH和GOTO。
    //
    //PHP默认使用CALL的方式，也就是函数调用的方式， 由于opcode执行是每个PHP程序频繁需要进行的操作，
    //
    //可以使用SWITCH或者GOTO的方式来分发， 通常GOTO的效率相对会高一些，
    //
    //不过效率是否提高依赖于不同的CPU。
    //
    //在我们上面的例子中，我们的PHP代码会被Parsing成:
    //
    //* ZEND_ECHO     'Hello World%21'
    //* ZEND_ADD       ~0 1 1
    //* ZEND_ASSIGN  !0 ~0
    //* ZEND_ECHO     !0
    //* ZEND_RETURN  1

// 2

    xdebug_stop_trace ();
    xdebug_start_trace ('./txt', 2);
    xdebug_stop_trace ();
    /*
     * xdebug.profiler_output_dir="D:\phpStudy\tmp\xdebug"
     * xdebug.trace_output_dir="D:\phpStudy\tmp\xdebug"
     * zend_extension="D:\phpStudy\php\php-7.0.12-nts\ext\php_xdebug.dll"
     * xdebug.auto_trace = on
     * xdebug.auto_profile = on
     * xdebug.collect_params = on
     * xdebug.collect_return = on
     * xdebug.profiler_enable = on
    */