<?php
    /**
     * 支付宝接口类
     */
    require_once SRCPATH . "sdk/Wechatpay/lib/Log.php";

    class Alipay
    {
        /**
         * appid
         *
         * @var string
         */
        protected $app_id = '';

        /**
         * 商户号
         *
         * @var string
         */
        protected $partner = '';

        /**
         * // MD5密钥，安全检验码
         *
         * @var string
         */
        protected $key = '';

        /**
         * 支付网关
         *
         * @var string
         */
        protected $gateway = 'https://mapi.alipay.com/gateway.do?';

        /**
         * 接口名称
         *
         * @var string
         */
        protected $service = 'create_direct_pay_by_user';

        /**
         * 卖家支付宝账号，一般情况下收款账号就是签约账号
         *
         * @var string
         */
        protected $seller_id = '';

        /**
         * 服务器异步通知页面URL，不能加参数
         *
         * @var string
         */
        protected $notify_url = NULL;

        /**
         * 页面跳转同步通知页面URL，不能加参数
         *
         * @var string
         */
        protected $return_url = NULL; // 页面跳转同步通知页面URL，不能加参数

        /**
         * 签名方式
         *
         * @var string
         */
        protected $sign_type = 'MD5';

        /**
         * 字符编码格式 目前支持 gbk 或 utf-8
         *
         * @var string
         */
        protected $input_charset = 'utf-8';

        /**
         *  // 支付类型（购买商品） ，无需修改
         *
         * @var integer
         */
        protected $payment_type = 1;

        /**
         * 防钓鱼时间戳
         *
         * @var string
         */
        protected $anti_phishing_key;

        /**
         * 客户端的IP地址 非局域网的外网IP地址
         *
         * @var string
         */
        protected $exter_invoke_ip;

        /**
         * 请保证cacert.pem文件在当前文件夹目录中
         *
         * @var string
         */
        protected $cacert;

        /**
         * rsa私钥
         *
         * @var string
         */
        protected $rsa_private_key = '';

        /**
         * 支付宝rsa公钥
         *
         * @var [type]
         */
        protected $alipay_rsa_public_key = '';

        /**
         * HTTPS形式消息验证地址
         *
         * @var string
         */
        protected $https_verify_url = 'https://mapi.alipay.com/gateway.do?service=notify_verify&';

        /**
         * HTTPS形式消息验证地址
         *
         * @var string
         */
        protected $http_verify_url = 'http://notify.alipay.com/trade/notify_query.do?';

        protected $CI;

        public function __construct()
        {
            $this->cacert = dirname(__FILE__) . '/cacert.pem';

            $CI               = &get_instance();
            $this->notify_url = $CI->config->item('domain_web') . 'pay/notify/alipay';
            $this->return_url = $CI->config->item('domain_web') . 'pay/pay_return/alipay';
            $this->CI         = $CI;
        }

        /**
         * 创建支付表单
         *
         * @param $para_temp   请求参数数组
         * @param $method      提交方式。两个值可选：post、get
         * @param $button_name 确认按钮显示文字
         * @return 提交表单HTML文本
         */
        public function buildRequestForm($para_temp, $method = 'get', $button_name = 'Submit')
        {
            // 公用参数
            $para_temp['service']           = $this->service;
            $para_temp['partner']           = $this->partner;
            $para_temp['seller_id']         = $this->seller_id;
            $para_temp['payment_type']      = $this->payment_type;
            $para_temp['notify_url']        = $this->notify_url;
            $para_temp['return_url']        = $this->return_url;
            $para_temp['anti_phishing_key'] = $this->anti_phishing_key;
            $para_temp['exter_invoke_ip']   = $this->exter_invoke_ip;
            $para_temp['_input_charset']    = $this->input_charset;

            // 待请求参数数组
            $para = $this->buildRequestPara($para_temp);

            $sHtml = "<form id='alipaysubmit' style='display:none;' name='alipaysubmit' action='" . $this->gateway . "_input_charset=" . trim(strtolower($this->input_charset)) . "' method='" . $method . "'>";
            while(list ($key, $val) = each($para)) {
                $sHtml .= "<input type='hidden' name='" . $key . "' value='" . $val . "'/>";
            }

            //submit按钮控件请不要含有name属性
            $sHtml = $sHtml . "<input type='submit' value='" . $button_name . "'></form>";

            $sHtml = $sHtml . "<script>document.forms['alipaysubmit'].submit();</script>";

            return $sHtml;
        }

        /**
         * 生成要请求给支付宝的参数数组
         *
         * @param $para_temp 请求前的参数数组
         * @return 要请求的参数数组
         */
        public function buildRequestPara($para_temp)
        {
            //除去待签名参数数组中的空值和签名参数
            $para_filter = $this->paraFilter($para_temp);

            //对待签名参数数组排序
            $para_sort = $this->argSort($para_filter);

            //生成签名结果
            $mysign = $this->buildRequestMysign($para_sort);

            //签名结果与签名方式加入请求提交参数组中
            $para_sort['sign']      = $mysign;
            $para_sort['sign_type'] = strtoupper(trim($this->sign_type));

            return $para_sort;
        }

        /**
         * 除去数组中的空值和签名参数
         *
         * @param $para 签名参数组
         *              return 去掉空值与签名参数后的新签名参数组
         */
        public function paraFilter($para)
        {
            $para_filter = array();
            while(list ($key, $val) = each($para)) {
                if($key == "sign" || $key == "sign_type" || $val == "") continue;
                else    $para_filter[$key] = $para[$key];
            }
            return $para_filter;
        }

        /**
         * 对数组排序
         *
         * @param $para 排序前的数组
         *              return 排序后的数组
         */
        public function argSort($para)
        {
            ksort($para);
            reset($para);
            return $para;
        }

        /**
         * 生成签名结果
         *
         * @param $para_sort 已排序要签名的数组
         *                   return 签名结果字符串
         */
        public function buildRequestMysign($para_sort)
        {
            //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
            $prestr = $this->createLinkstring($para_sort);

            $mysign = "";
            switch(strtoupper(trim($this->sign_type))) {
                case "MD5" :
                    $mysign = $this->md5Sign($prestr, $this->key);
                    break;

                case 'RSA2':
                    // $prestr = $this->createLinkstringUrlencode($para_sort);
                    $mysign = $this->sha256Sign($prestr);
                    break;
                default :
                    $mysign = "";
            }

            return $mysign;
        }

        /**
         * 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
         *
         * @param $para 需要拼接的数组
         *              return 拼接完成以后的字符串
         */
        public function createLinkstring($para)
        {
            $arg = "";
            while(list ($key, $val) = each($para)) {
                $val = is_array($val) ? json_encode($val) : $val;
                $arg .= $key . "=" . $val . "&";
            }
            //去掉最后一个&字符
            $arg = substr($arg, 0, count($arg) - 2);

            //如果存在转义字符，那么去掉转义
            if(get_magic_quotes_gpc()) {
                $arg = stripslashes($arg);
            }

            return $arg;
        }

        /**
         * 签名字符串
         *
         * @param $prestr 需要签名的字符串
         * @param $key    私钥
         *                return 签名结果
         */
        public function md5Sign($prestr, $key)
        {
            $prestr = $prestr . $key;
            return md5($prestr);
        }

        public function sha256Sign($prestr)
        {
            // echo $prestr;
            // $private_key = 'MIICdQIBADANBgkqhkiG9w0BAQEFAASCAl8wggJbAgEAAoGBAJ2oX60+xYcg1J7PdpsxEJ7S5V4Bc9u9W1WZB3J8dlZcP1mbBkARvUQovlZjdl+hhW6wml1qTjKWJJaiGB75T8LrEW/4HWorhRU6PCRA2yc+1eKw74bV0Ey+RD2GyDaKD3c7lhPwMRhy3zcdo508sxaQfQjl/wQjDzpx2Y0ukGupAgMBAAECgYBWHeCVK1KOKzq4vK4Wu0hO2Pf8z2JPxzEaoopU2PNy3NSlx240lPwDPRYq7g180yelfMX0/NpV+3lk5omycZBFFrh3QWOE79c+XtW3QiL7Dn7Ctr+1fnKtPRmf4kAmHzWM2f0B4cDfyPMKLXkY75M0Xv+BZWQgJ88YaeS5qFUyAQJBAM9MzxvPFSUsJk8HjDfoofjkH4gS2VOblUe88X9rX2esH5R3+TFsWdPk8cqBRspaDAiKmPeiBunVAQiAPqL4FmECQQDCsgkKdFwuPFdThu680+7xk0zWuEpghbplh+799zf3d/r9iL6hvk/SLLF59MuxjKi+WsG0zJg4TZo/GwIpmkpJAkAimRUv9P34eEfkhMP4SNFPsvM4SL0Q4TSnBnff5lHEAcw7gVKL1yOe4+UfATiJaUH84vToz5gLyssjWhQaKwHBAkAH9vKJu/LdbViBMT7o+J6IwWbeTdG1GyNh7eqn9woSFJVu874grcFkLrHf9FS04bUxfFL6S3harUoHFNrEyuwJAkANyNaVIAPuI+q2g1OpgiAygrdx1aUksA6t/WMeAMAgIA5m40GFQBSCsGl1Qe3GCSxCsuAgcEqfqGT5wGyD0t0Z';
            $private_key = $this->rsa_private_key;
            $private_key = str_replace("-----BEGIN RSA PRIVATE KEY-----", "", $private_key);
            $private_key = str_replace("-----END RSA PRIVATE KEY-----", "", $private_key);
            $private_key = str_replace("\n", "", $private_key);
            $private_key = "-----BEGIN RSA PRIVATE KEY-----" . PHP_EOL . wordwrap($private_key, 64, "\n", true) . PHP_EOL . "-----END RSA PRIVATE KEY-----";

            $res = openssl_get_privatekey($private_key);

            openssl_sign($prestr, $sign, $res);
            return base64_encode($sign);
        }

        /**
         * @param $param ['order_sn'] 订单编号
         * @param $param ['trade_sn'] 支付宝交易号
         * @param $param ['amount'] 退款金额，不能大于订单金额
         * @param $param ['reason'] 退款理由，长度不能大于256字节，不能含有“^”、“|”、“$”、“#”等影响detail_data格式的特殊字符
         * @return [type]        [description]
         */
        public function refund($param)
        {
            if(empty($param['order_sn']) || empty($param['trade_sn']) || empty($param['amount'])) {
                return false;
            }

            try {
                $biz_content['out_trade_no']  = $param['order_sn'];
                $biz_content['trade_no']      = $param['trade_sn'];
                $biz_content['refund_amount'] = $param['amount'];

                require_once(SRCPATH . 'sdk/Alipay/AopSdk.php');
                $aop                     = new AopClient ();
                $aop->gatewayUrl         = 'https://openapi.alipay.com/gateway.do';
                $aop->appId              = $this->app_id;
                $aop->rsaPrivateKey      = $this->rsa_private_key;
                $aop->alipayrsaPublicKey = $this->alipay_rsa_public_key;
                $aop->apiVersion         = '1.0';
                $aop->signType           = 'RSA2';
                $aop->postCharset        = 'utf-8';
                $aop->format             = 'json';
                $request                 = new AlipayTradeRefundRequest();
                $request->setBizContent(json_encode($biz_content));
                $result = $aop->execute($request);

                $responseNode = str_replace(".", "_", $request->getApiMethodName()) . "_response";
                $resultCode   = $result->$responseNode->code;
                if(!empty($resultCode) && $resultCode == 10000) {
                    return true;
                }

                return false;
            } catch(Exception $e) {
                echo $e->getMessage();
                return false;
            }
        }

        /**
         * 生成要请求给支付宝的参数数组
         *
         * @param $para_temp 请求前的参数数组
         * @return 要请求的参数数组字符串
         */
        public function buildRequestParaToString($para_temp)
        {
            //待请求参数数组
            $para = $this->buildRequestPara($para_temp);

            //把参数组中所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串，并对字符串做urlencode编码
            $request_data = $this->createLinkstringUrlencode($para);

            return $request_data;
        }

        /**
         * 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串，并对字符串做urlencode编码
         *
         * @param $para 需要拼接的数组
         *              return 拼接完成以后的字符串
         */
        public function createLinkstringUrlencode($para)
        {
            $arg = "";
            while(list ($key, $val) = each($para)) {
                $val = is_array($val) ? json_encode($val) : $val;
                $arg .= $key . "=" . urlencode($val) . "&";
            }
            //去掉最后一个&字符
            $arg = substr($arg, 0, count($arg) - 2);

            //如果存在转义字符，那么去掉转义
            if(get_magic_quotes_gpc()) {
                $arg = stripslashes($arg);
            }

            return $arg;
        }

        /**
         * 建立请求，以模拟远程HTTP的POST请求方式构造并获取支付宝的处理结果
         *
         * @param $para_temp 请求参数数组
         * @return 支付宝处理结果
         */
        public function buildRequestHttp($para_temp)
        {
            $sResult = '';

            //待请求参数数组字符串
            $request_data = $this->buildRequestPara($para_temp);

            //远程获取数据
            $sResult = $this->getHttpResponsePOST($this->gateway, $this->cacert, $request_data, trim(strtolower($this->input_charset)));

            return $sResult;
        }

        /**
         * 远程获取数据，POST模式
         * 注意：
         * 1.使用Crul需要修改服务器中php.ini文件的设置，找到php_curl.dll去掉前面的";"就行了
         * 2.文件夹中cacert.pem是SSL证书请保证其路径有效，目前默认路径是：getcwd().'\\cacert.pem'
         *
         * @param $url           指定URL完整路径地址
         * @param $cacert_url    指定当前工作目录绝对路径
         * @param $para          请求的数据
         * @param $input_charset 编码格式。默认值：空值
         *                       return 远程输出的数据
         */
        public function getHttpResponsePOST($url, $cacert_url, $para, $input_charset = '')
        {

            // echo $url.'<br/>';
            // echo '<pre>';
            // print_r($para);
            // echo '</pre>';
            if(trim($input_charset) != '') {
                $url = $url . "_input_charset=" . $input_charset;
            }
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);//SSL证书认证
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);//严格认证
            curl_setopt($curl, CURLOPT_CAINFO, $cacert_url);//证书地址
            curl_setopt($curl, CURLOPT_HEADER, 0); // 过滤HTTP头
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);// 显示输出结果
            curl_setopt($curl, CURLOPT_POST, true); // post传输数据
            curl_setopt($curl, CURLOPT_POSTFIELDS, $para);// post传输数据
            $responseText = curl_exec($curl);
            // var_dump(curl_error($curl));//如果执行curl过程中出现异常，可打开此开关，以便查看异常内容
            curl_close($curl);

            return $responseText;
        }

        /**
         * 建立请求，以模拟远程HTTP的POST请求方式构造并获取支付宝的处理结果，带文件上传功能
         *
         * @param $para_temp      请求参数数组
         * @param $file_para_name 文件类型的参数名
         * @param $file_name      文件完整绝对路径
         * @return 支付宝返回处理结果
         */
        public function buildRequestHttpInFile($para_temp, $file_para_name, $file_name)
        {
            //待请求参数数组
            $para                  = $this->buildRequestPara($para_temp);
            $para[$file_para_name] = "@" . $file_name;

            //远程获取数据
            $sResult = $this->getHttpResponsePOST($this->gateway, $this->cacert, $para, trim(strtolower($this->input_charset)));

            return $sResult;
        }

        /**
         * 用于防钓鱼，调用接口query_timestamp来获取时间戳的处理函数
         * 注意：该功能PHP5环境及以上支持，因此必须服务器、本地电脑中装有支持DOMDocument、SSL的PHP配置环境。建议本地调试时使用PHP开发软件
         * return 时间戳字符串
         */
        public function query_timestamp()
        {
            $url         = $this->gateway . "service=query_timestamp&partner=" . trim(strtolower($this->partner)) . "&_input_charset=" . trim(strtolower($this->input_charset));
            $encrypt_key = "";

            $doc = new DOMDocument();
            $doc->load($url);
            $itemEncrypt_key = $doc->getElementsByTagName("encrypt_key");
            $encrypt_key     = $itemEncrypt_key->item(0)->nodeValue;

            return $encrypt_key;
        }

        /**
         * 写日志，方便测试（看网站需求，也可以改成把记录存入数据库）
         * 注意：服务器需要开通fopen配置
         *
         * @param $word 要写入日志里的文本内容 默认值：空值
         */
        public function logResult($word = '')
        {
            $fp = fopen("log.txt", "a");
            flock($fp, LOCK_EX);
            fwrite($fp, "执行日期：" . strftime("%Y%m%d%H%M%S", time()) . "\n" . $word . "\n");
            flock($fp, LOCK_UN);
            fclose($fp);
        }

        /**
         * 实现多种字符编码方式
         *
         * @param $input           需要编码的字符串
         * @param $_output_charset 输出的编码格式
         * @param $_input_charset  输入的编码格式
         *                         return 编码后的字符串
         */
        public function charsetEncode($input, $_output_charset, $_input_charset)
        {
            $output = "";
            if(!isset($_output_charset)) $_output_charset = $_input_charset;
            if($_input_charset == $_output_charset || $input == null) {
                $output = $input;
            } elseif(function_exists("mb_convert_encoding")) {
                $output = mb_convert_encoding($input, $_output_charset, $_input_charset);
            } elseif(function_exists("iconv")) {
                $output = iconv($_input_charset, $_output_charset, $input);
            } else die("sorry, you have no libs support for charset change.");
            return $output;
        }

        /**
         * 实现多种字符解码方式
         *
         * @param $input           需要解码的字符串
         * @param $_output_charset 输出的解码格式
         * @param $_input_charset  输入的解码格式
         *                         return 解码后的字符串
         */
        public function charsetDecode($input, $_input_charset, $_output_charset)
        {
            $output = "";
            if(!isset($_input_charset)) $_input_charset = $_input_charset;
            if($_input_charset == $_output_charset || $input == null) {
                $output = $input;
            } elseif(function_exists("mb_convert_encoding")) {
                $output = mb_convert_encoding($input, $_output_charset, $_input_charset);
            } elseif(function_exists("iconv")) {
                $output = iconv($_input_charset, $_output_charset, $input);
            } else die("sorry, you have no libs support for charset changes.");
            return $output;
        }

        /**
         * 针对notify_url验证消息是否是支付宝发出的合法消息
         *
         * @return 验证结果
         */
        function verifyNotify()
        {
            if(empty($_POST)) {
                //判断POST来的数组是否为空
                return false;
            } else {
                //生成签名结果
                $isSign = $this->getSignVeryfy($_POST, $_POST["sign"]);
                //获取支付宝远程服务器ATN结果（验证是否是支付宝发来的消息）
                $responseTxt = 'false';
                if(!empty($_POST["notify_id"])) {
                    $responseTxt = $this->_getResponse($_POST["notify_id"]);
                }

                // 验证
                // $responsetTxt的结果不是true，与服务器设置问题、合作身份者ID、notify_id一分钟失效有关
                // isSign的结果不是true，与安全校验码、请求时的参数格式（如：带自定义参数等）、编码格式有关
                if(preg_match("/true$/i", $responseTxt) && $isSign) {
                    if(isset($_POST['notify_type']) && $_POST['notify_type'] == 'batch_refund_notify') {
                        // 初始化日志
                        $logHandler = new CLogFileHandler(SRCPATH . "common/log/alipay/refund_notify_" . date('Y-m-d') . '.log');
                        $log        = Log::Init($logHandler, 15);
                        Log::DEBUG(json_encode($_POST));

                        // 退款
                        if(empty($_POST['batch_no'])) {
                            return false;
                        }

                        if(empty($_POST['success_num']) || $_POST['success_num'] < 1) {
                            return false;
                        }

                        // 获取订单号
                        // 批次号 = 退款日期（8位）+ 订单号
                        $order_sn  = substr($_POST['batch_no'], 8, strlen($_POST['batch_no']) - 1);
                        $order_len = strlen($order_sn);
                        if($order_len != 16 && $order_len != 18) {
                            return false;
                        }

                        switch($order_len) {
                            // 产品订单
                            case 16:
                                $this->CI->load->model('order_model');
                                $order = $this->CI->order_model->get_single(array('order_sn' => $order_sn));
                                if(empty($order['order_sn'])) {
                                    return false;
                                }

                                if($order['status'] != -2) {
                                    $order['status'] = -2;
                                    $result          = $this->CI->order_model->save($order);
                                    return $result;
                                }
                                break;

                            // 活动订单
                            case 18:
                                $this->CI->load->model('activity_order_model');
                                $order = $this->CI->activity_order_model->get_single(array('order_sn' => $order_sn));
                                if(empty($order['order_sn'])) {
                                    return false;
                                }

                                if($order['status'] != -1) {
                                    $order['status'] = -1;
                                    $result          = $this->CI->activity_order_model->save($order);
                                    return $result;
                                }
                                break;

                            default:
                                return false;
                                break;
                        }

                        return true;
                    } else {
                        // 初始化日志
                        $logHandler = new CLogFileHandler(SRCPATH . "common/log/alipay/pay_notify_" . date('Y-m-d') . '.log');
                        $log        = Log::Init($logHandler, 15);
                        Log::DEBUG(json_encode($_POST));

                        // 支付
                        if(empty($_POST['out_trade_no'])) {
                            Log::DEBUG('校验失败，缺少out_trade_no参数');
                            return false;
                        }

                        $order_len   = strlen($_POST['out_trade_no']);
                        $order_model = $order_len == 16 ? 'order_model' : 'activity_order_model';
                        $this->CI->load->model($order_model);
                        $order = $this->CI->{$order_model}->get_single(array('order_sn' => $_POST['out_trade_no']));
                        if(!$order) {
                            Log::DEBUG('订单【' . $_POST['out_trade_no'] . '】不存在');
                            return false;
                        }

                        if($order['amount'] != $_POST['total_fee']) {
                            Log::DEBUG('订单金额不一致');
                            return false;
                        }

                        if($order['status'] < 0) {
                            Log::DEBUG('订单不是可支付状态');
                            return false;
                        }

                        if($order['status'] > 0) {
                            Log::DEBUG('订单不是可支付状态');
                            return true;
                        }

                        // 更新订单的状态
                        $update_res = $this->CI->{$order_model}->update_paid_order($order['order_sn'], $_POST['trade_no']);

                        Log::DEBUG('订单【' . $order['order_sn'] . '】校验结果:' . $update_res);

                        return $update_res;
                    }
                } else {
                    return false;
                }
            }
        }

        /**
         * 获取返回时的签名验证结果
         *
         * @param $para_temp 通知返回来的参数数组
         * @param $sign      返回的签名结果
         * @return 签名验证结果
         */
        function getSignVeryfy($para_temp, $sign)
        {
            //除去待签名参数数组中的空值和签名参数
            $para_filter = $this->paraFilter($para_temp);

            //对待签名参数数组排序
            $para_sort = $this->argSort($para_filter);

            //把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串
            $prestr = $this->createLinkstring($para_sort);

            $isSgin = false;
            switch(strtoupper(trim($this->sign_type))) {
                case "MD5" :
                    $isSgin = $this->md5Verify($prestr, $sign, $this->key);
                    break;
                default :
                    $isSgin = false;
            }

            return $isSgin;
        }

        /**
         * 验证签名
         *
         * @param $prestr 需要签名的字符串
         * @param $sign   签名结果
         * @param $key    私钥
         *                return 签名结果
         */
        public function md5Verify($prestr, $sign, $key)
        {
            $prestr = $prestr . $key;
            $mysgin = md5($prestr);

            if($mysgin == $sign) {
                return true;
            } else {
                return false;
            }
        }

        /**
         * 获取远程服务器ATN结果,验证返回URL
         *
         * @param $notify_id 通知校验ID
         * @return 服务器ATN结果
         *                   验证结果集：
         *                   invalid命令参数不对 出现这个错误，请检测返回处理中partner和key是否为空
         *                   true 返回正确信息
         *                   false 请检查防火墙或者是服务器阻止端口问题以及验证时间是否超过一分钟
         */
        private function _getResponse($notify_id)
        {
            $transport  = strtolower(trim($this->transport));
            $partner    = trim($this->partner);
            $veryfy_url = '';
            if($transport == 'https') {
                $veryfy_url = $this->https_verify_url;
            } else {
                $veryfy_url = $this->http_verify_url;
            }
            $veryfy_url  = $veryfy_url . "partner=" . $partner . "&notify_id=" . $notify_id;
            $responseTxt = $this->getHttpResponseGET($veryfy_url, $this->cacert);

            return $responseTxt;
        }

        /**
         * 远程获取数据，GET模式
         * 注意：
         * 1.使用Crul需要修改服务器中php.ini文件的设置，找到php_curl.dll去掉前面的";"就行了
         * 2.文件夹中cacert.pem是SSL证书请保证其路径有效，目前默认路径是：getcwd().'\\cacert.pem'
         *
         * @param $url        指定URL完整路径地址
         * @param $cacert_url 指定当前工作目录绝对路径
         *                    return 远程输出的数据
         */
        public function getHttpResponseGET($url, $cacert_url)
        {
            $curl = curl_init($url);
            curl_setopt($curl, CURLOPT_HEADER, 0); // 过滤HTTP头
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);// 显示输出结果
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);//SSL证书认证
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);//严格认证
            curl_setopt($curl, CURLOPT_CAINFO, $cacert_url);//证书地址
            $responseText = curl_exec($curl);
            //var_dump( curl_error($curl) );//如果执行curl过程中出现异常，可打开此开关，以便查看异常内容
            curl_close($curl);

            return $responseText;
        }

        /**
         * 针对return_url验证消息是否是支付宝发出的合法消息
         *
         * @return 验证结果
         */
        function verifyReturn()
        {
            if(empty($_GET['out_trade_no'])) {
                return false;
            }

            $this->CI->load->model('order_model');
            $order = $this->CI->order_model->get_single(array('order_sn' => $_GET['out_trade_no']));
            if(!$order) {
                return false;
            }

            if($order['status'] <= 0) {
                return false;
            }

            return true;
        }
    }