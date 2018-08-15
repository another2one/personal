<?php

    class iplimit
    {
        public $path = "ipdata.php";
        public $iptable = array();

        function __construct()
        {
            header('content-type:text/html;charset=utf-8;');
            require_once($this->path);

            $this->iptable = $ipdata;
        }

        function setup($ip = '')
        {
            $content = file_get_contents($this->path);
            if(empty($content)) {
                $this->show('1');
                exit('IP数据库破损');
            }

            eval("\$this->iptable = $content;");
            return $this->CheckIp($ip);
        }

        function show($code)
        {
            $msg = array(
                1     => "IP数据库文件破损",
                2     => "取不到IP地址 可能使用了代理",
                4     => "在局域网内",
                'out' => "IP地址在国外",
                'in'  => "IP地址在国内",
            );

            $this->code = $code;
            $this->msg  = $msg[$code];
        }

        function CheckIp($ip = '')
        {
            !$ip && $ip = $this->GetIp();
            $ip2 = sprintf('%u', ip2long($ip));
            $tag = reset(explode('.', $ip));

            if(!$ip) {
                $this->show(2);
                return true;
            }

            if('192' == $tag || '127' == $tag) {
                $this->show(4);
                return true;
            }

            if(!isset($this->iptable[$tag])) {
                $this->show(3);
                return false;
            }

            foreach($this->iptable[$tag] as $k => $v) {
                if($v[0] <= $ip2 && $v[1] >= $ip2) {
                    $this->show('in');
                    return true;
                }
            }

            $this->show('out');
            return false;
        }

        function GetIP()
        {
            if($ip = getenv('HTTP_CLIENT_IP')) ;
            elseif($ip = getenv('HTTP_X_FORWARDED_FOR')) ;
            elseif($ip = getenv('HTTP_X_FORWARDED')) ;
            elseif($ip = getenv('HTTP_FORWARDED_FOR')) ;
            elseif($ip = getenv('HTTP_FORWARDED')) ;
            else    $ip = $_SERVER['REMOTE_ADDR'];
            return $ip;
        }

        /**
         * 是否来自国内
         *
         * @return [type] [description]
         */
        function fromChina($ip)
        {
            if(empty($this->iptable)) {
                return false;
            }

            if(!$ip) {
                return false;
            }

            $ip2 = sprintf('%u', ip2long($ip));
            $tag = explode('.', $ip)[0];

            if('192' == $tag || '127' == $tag) {
                return false;
            }

            if(!isset($this->iptable[$tag])) {
                return false;
            }

            foreach($this->iptable[$tag] as $k => $v) {
                if($v[0] <= $ip2 && $v[1] >= $ip2) {
                    return true;
                }
            }

            return false;
        }

        function __destruct()
        {
            unset($this->iptable);
        }
    }

?>