<?php
    /**
     * 微信支付类
     *
     * @author  lushifa <515651483@qq.com>
     * @version 20170510 1007
     */
// require_once SRCPATH."sdk/Wechatpay/lib/WxPay.Data.php";
    require_once SRCPATH . "sdk/Wechatpay/lib/WxPay.Api.php";
    require_once SRCPATH . "sdk/Wechatpay/lib/Log.php";
    require_once SRCPATH . "sdk/Wechatpay/lib/WxPay.JsApiPay.php";
    require_once SRCPATH . "sdk/Wechatpay/lib/WxPay.Notify.php";
    require_once SRCPATH . "sdk/Wechatpay/lib/WxPay.Notify.php";

    class Wechatpay extends WxPayNotify
    {
        /**
         * 微信支付分配的公众账号ID（企业号corpid即为此appId）
         *
         * @var string
         */
        // protected $appid = '';

        // protected $api_key = '623983311c7ab378fbdc46c8421a1756';

        /**
         * 微信支付分配的商户号
         *
         * @var string
         */
        // protected $mch_id = '';

        /**
         * 签名类型，默认为MD5，支持HMAC-SHA256和MD5。
         *
         * @var string
         */
        // protected $sign_type = '';

        /**
         * 异步接收微信支付结果通知的回调地址，通知url必须为外网可访问的url，不能携带参数。
         *
         * @var string
         */
        protected $notify_url = '';

        /**
         * 交易类型，取值如下：JSAPI(公众号支付)，NATIVE(扫码支付)，APP(app支付)等
         *
         * @var string
         */
        protected $trade_type = 'NATIVE';

        /**
         * 扫码模式，值为1或2
         *
         * @var integer
         */
        protected $mode = 1;

        /**
         * CI对象
         *
         * @var string
         */
        protected $CI = '';

        /**
         * 客服聊天账号，用于发送及时消息通知
         *
         * @var string
         */
        protected $customer_account = '8ac7828f8f80c6f716b11a3252c3b774';

        function __construct($config = array())
        {
            $CI       = &get_instance();
            $this->CI = $CI;

            $domain_web = $CI->config->item('domain_web');
            // $this->return_url = $domain_web.'pay/notify/alipay';
            $this->notify_url = $domain_web . 'pay/notify/wechatpay';

            if(!empty($config)) {
                foreach($config as $key => $val) {
                    if(isset($this->{$key})) {
                        $this->{$key} = $val;
                    }
                }
            }
        }

        /******************************************************** 刷卡（扫码）支付开始 ***********************************************************/

        /**
         * 支付
         *
         * @return [type] [description]
         */
        public function pay($pay_data)
        {
            if(!isset($pay_data['type']) || !isset($pay_data['order_sn']) || !isset($pay_data['title'])) {
                $logHandler = new CLogFileHandler(SRCPATH . "common/log/wechatpay/wechat_error_" . date('Y-m-d') . '.txt');
                Log::Init($logHandler, 15);
                Log::DEBUG("error: missing field");
                return false;
            }

            if(!isset($pay_data['amount']) || !isset($pay_data['user_id'])) {
                $logHandler = new CLogFileHandler(SRCPATH . "common/log/wechatpay/wechat_error_" . date('Y-m-d') . '.txt');
                Log::Init($logHandler, 15);
                Log::DEBUG("error: missing field");
                return false;
            }

            $this->CI->load->model('user_model');
            $user         = $this->CI->user_model->get_single(array('user_id' => $pay_data['user_id']), 'chat_account');
            $chat_account = isset($user['chat_account']) ? $user['chat_account'] : '';

            $encrypt_key  = md5(md5(WxPayConfig::APPID . WxPayConfig::KEY . WxPayConfig::MCHID . $pay_data['order_sn']));
            $chat_account = vaya_encrypt($chat_account, $encrypt_key);

            try {
                switch($pay_data['type']) {
                    case 'wechatpay': // PC端扫码支付
                        if($this->mode == 1) {
                            $order_sn = isset($pay_data['order_sn']) ? $pay_data['order_sn'] : '';
                            if(!$order_sn) {
                                return false;
                            }

                            $code_url = $this->getPrePayUrl($order_sn);
                        } else {
                            $data     = $this->getPayImage($pay_data);
                            $code_url = isset($data['code_url']) ? $data['code_url'] : '';
                            if(!$code_url) {
                                return false;
                            }
                        }

                        return $code_url;
                        break;

                    case 'wechat_h5': // H5支付
                        // 统一下单
                        $input = new WxPayUnifiedOrder();
                        $input->SetAttach($chat_account);
                        $input->SetBody('ComeToChina-' . $pay_data['title']);
                        $input->SetOut_trade_no($pay_data['order_sn']);
                        $input->SetTotal_fee($pay_data['amount']);
                        $input->SetNotify_url($this->notify_url);
                        $input->SetTrade_type("MWEB");
                        $input->SetSceneInfo(json_encode(array('h5_info' => array('type' => 'Wap', 'wap_url' => $this->CI->config->item('domain_web'), 'wap_name' => '深圳沃亚旅行'))));
                        $wechat_order = WxPayApi::unifiedOrder($input);

                        if(empty($wechat_order['mweb_url'])) {
                            return false;
                        }

                        $redirect_url = $this->CI->config->item('domain_web') . 'pay/wechatpay_return/' . $pay_data['order_sn'];
                        return $wechat_order['mweb_url'] . '&redirect_url=' . urlencode($redirect_url);
                        break;

                    case 'wechat_browser': // 微信浏览器内支付（公众号支付）
                        $tools   = new JsApiPay();
                        $open_id = isset($_SESSION['open_id']) ? $_SESSION['open_id'] : '';
                        if(!$open_id) {
                            $logHandler = new CLogFileHandler(SRCPATH . "common/log/wechatpay/wechat_browser_pay_" . date('Y-m-d') . '.txt');
                            Log::Init($logHandler, 15);
                            Log::DEBUG("error : missing  open_id");
                            return false;
                        }

                        // 统一下单
                        $input = new WxPayUnifiedOrder();
                        $input->SetAttach($chat_account);
                        $input->SetBody('ComeToChina-' . $pay_data['title']);
                        // $input->SetOut_trade_no($pay_data['order_sn'].mt_rand(1000,9999));
                        $input->SetOut_trade_no($pay_data['order_sn']);
                        $input->SetTotal_fee($pay_data['amount']);
                        $input->SetTime_start(date("YmdHis"));
                        $input->SetTime_expire(date("YmdHis", time() + 600));
                        $input->SetNotify_url($this->notify_url);
                        $input->SetTrade_type("JSAPI");
                        $input->SetOpenid($open_id);
                        $order = WxPayApi::unifiedOrder($input);

                        $logHandler = new CLogFileHandler(SRCPATH . "common/log/wechatpay/wechat_browser_pay_" . date('Y-m-d') . '.txt');
                        Log::Init($logHandler, 15);
                        Log::DEBUG("input:" . json_encode($input));
                        Log::DEBUG("order:" . json_encode($order));

                        $data['jsApiParameters'] = $tools->GetJsApiParameters($order);
                        Log::DEBUG("jsApiParameters" . json_encode($data));
                        return $data;
                        break;

                    case 'wechat_app': // 微信app
                        $tools = new JsApiPay();

                        // 统一下单
                        $input = new WxPayUnifiedOrder();
                        $input->SetAttach($chat_account);
                        $input->SetBody('ComeToChina-' . $pay_data['title']);
                        $input->SetOut_trade_no($pay_data['order_sn']);
                        $input->SetTotal_fee($pay_data['amount']);
                        $input->SetTime_start(date("YmdHis"));
                        $input->SetTime_expire(date("YmdHis", time() + 600));
                        $input->SetNotify_url($this->notify_url);
                        $input->SetTrade_type("APP");
                        $order = WxPayApi::unifiedOrder($input);

                        $logHandler = new CLogFileHandler(SRCPATH . "common/log/wechatpay/wechat_app_pay_" . date('Y-m-d') . '.txt');
                        Log::Init($logHandler, 15);
                        Log::DEBUG("input:" . json_encode($input));
                        Log::DEBUG("order:" . json_encode($order));

                        $result    = $tools->GetJsApiParameters($order);
                        $result    = $result ? json_decode($result, true) : array();
                        $tmp_arr   = !empty($result['package']) ? explode('=', $result['package']) : '';
                        $prepay_id = isset($tmp_arr[1]) ? $tmp_arr[1] : '';

                        $jsapi = new WxPayJsApiPay();
                        $jsapi->SetAppid(WxPayConfig::APP_APPID);
                        $jsapi->SetPartnerid(WxPayConfig::APP_MCHID);
                        $jsapi->SetNonceStr(WxPayApi::getNonceStr());
                        $jsapi->SetPrepayid($prepay_id);
                        $jsapi->SetTimeStamp(time());
                        $jsapi->SetPackage("Sign=WXPay");

                        $data         = $jsapi->GetValues();
                        $data['sign'] = $jsapi->MakeAppSign();
                        return $data;
                        break;

                    default:
                        return false;
                        break;
                }
            } catch(Exception $e) {
                $logHandler = new CLogFileHandler(SRCPATH . "common/log/wechatpay/pay_error_" . date('Y-m-d') . '.txt');
                Log::Init($logHandler, 15);
                Log::DEBUG("error message:" . $e->getMessage());
                return false;
            }
        }

        /**
         *
         * 生成扫描支付URL,模式一
         *
         * @param BizPayUrlInput $bizUrlInfo
         */
        public function getPrePayUrl($productId)
        {
            $biz = new WxPayBizPayUrl();
            $biz->SetProduct_id($productId);
            $values = WxpayApi::bizpayurl($biz);
            $url    = "weixin://wxpay/bizpayurl?" . $this->GetUrlParams($values);
            return $url;
        }

        /**
         * 格式化参数格式化成url参数
         */
        public function GetUrlParams($values)
        {
            $buff = "";
            foreach($values as $k => $v) {
                if($v != "" && !is_array($v)) {
                    $buff .= $k . "=" . $v . "&";
                }
            }

            $buff = trim($buff, "&");
            return $buff;
        }

        /**
         * 获取支付的二维码（模式二）
         */
        public function getPayImage($pay_data)
        {
            $amount   = isset($pay_data['amount']) ? $pay_data['amount'] : '';
            $user_id  = isset($pay_data['user_id']) ? $pay_data['user_id'] : '';
            $order_sn = isset($pay_data['order_sn']) ? $pay_data['order_sn'] : '';
            $title    = isset($pay_data['title']) ? $pay_data['title'] : '';

            if(!$amount || !$user_id || !$order_sn || !$title) {
                return false;
            }

            $this->CI->load->model('user_model');
            $user         = $this->CI->user_model->get_single(array('user_id' => $user_id), 'chat_account');
            $chat_account = isset($user['chat_account']) ? $user['chat_account'] : '';

            $encrypt_key  = md5(md5(WxPayConfig::APPID . WxPayConfig::KEY . WxPayConfig::MCHID . $order_sn));
            $chat_account = vaya_encrypt($chat_account, $encrypt_key);

            $input = new WxPayUnifiedOrder();
            $input->SetBody($title);
            $input->SetAttach($chat_account); // 附加数据，在查询API和支付通知中原样返回，可作为自定义参数使用
            $input->SetOut_trade_no($order_sn);
            $input->SetTotal_fee($amount);
            $input->SetNotify_url($this->notify_url);
            $input->SetTrade_type($this->trade_type);
            $input->SetProduct_id($order_sn);

            return $this->getPayUrl($input);
        }

        //查询订单（模式二）

        /**
         *
         * 生成直接支付url，支付url有效期为2小时,模式二
         *
         * @param UnifiedOrderInput $input
         */
        public function getPayUrl($input)
        {
            if($input->GetTrade_type() == "NATIVE") {
                $result = WxPayApi::unifiedOrder($input);
                return $result;
            }
        }

        public function VerifyReturn($order_sn = '')
        {
            $logHandler = new CLogFileHandler(SRCPATH . "common/log/wechatpay/return_" . date('Y-m-d') . '.txt');
            $log        = Log::Init($logHandler, 15);

            // 扫码支付
            if($order_sn == '') {
                // 获取通知的数据
                $xml = @$GLOBALS['HTTP_RAW_POST_DATA'];
                Log::DEBUG("Wechatpay.verifyReturn.xml:" . $xml);

                //如果返回成功则验证签名
                try {
                    $xml_data = WxPayResults::Init($xml);
                    $pay_data = $this->unifiedorder($xml_data['openid'], $xml_data['product_id']);

                    $pay_data['nonce_str'] = $xml_data['nonce_str'];
                    $response_xml          = $this->ResponseToXml($pay_data);
                    Log::DEBUG("Wechatpay.verifyReturn.response_xml:" . $response_xml);
                    return $response_xml;
                } catch(WxPayException $e) {
                    $msg = $e->errorMessage();
                    Log::DEBUG("Wechatpay.verifyReturn.msg:" . $msg);

                    $err_data['return_msg'] = $msg;
                    return $this->ResponseToXml($err_data);
                }
            } else // H5或公众号支付
            {
                $order_len = strlen($order_sn);
                $model     = $order_len == 16 ? 'order_model' : 'activity_order_model';
                $this->CI->load->model($model);
                $order = $this->CI->{$model}->get_single(array('order_sn' => $order_sn), 'status');
                if(!isset($order['status'])) {
                    return false;
                }

                if($order['status'] > 0) {
                    return true;
                }

                if($order['status'] < 0) {
                    return false;
                }

                $input = new WxPayOrderQuery();
                $input->SetOut_trade_no($order_sn);
                $result = WxPayApi::orderQuery($input);
                Log::DEBUG("query:" . json_encode($result));
                if(array_key_exists("return_code", $result)
                    && array_key_exists("result_code", $result)
                    && array_key_exists("trade_state", $result)
                    && $result["return_code"] == "SUCCESS"
                    && $result["result_code"] == "SUCCESS"
                    && $result["trade_state"] == "SUCCESS"
                ) {
                    return true;
                }

                return false;
            }
        }

        /**
         * 统一下单（模式一）
         *
         * @param  [type] $open_id     [description]
         * @param  [type] $order_sn [description]
         * @return [type]             [description]
         */
        public function unifiedorder($open_id, $order_sn)
        {
            // 获取订单信息
            $this->CI->load->model('order_model');
            $order = $this->CI->order_model->get_single(array('order_sn' => $order_sn));
            if(!$order) {
                return false;
            }

            // 获取产品信息
            $this->CI->load->model('trip_model');
            $trip  = $this->CI->trip_model->get_single(array('trip_id' => $order['trip_id']), 'title');
            $title = isset($trip['title']) ? $trip['title'] : '';

            // 获取用户信息
            $this->CI->load->model('user_model');
            $user         = $this->CI->user_model->get_single(array('user_id' => $order['user_id']), 'chat_account');
            $chat_account = isset($user['chat_account']) ? $user['chat_account'] : '';

            $encrypt_key  = md5(md5(WxPayConfig::APPID . WxPayConfig::KEY . WxPayConfig::MCHID . $order_sn));
            $user_account = vaya_encrypt($chat_account, $encrypt_key);

            // 统一下单
            $input = new WxPayUnifiedOrder();
            $input->SetBody($title);
            $input->SetAttach($user_account);
            $input->SetOut_trade_no($order_sn);
            $input->SetTotal_fee($order['amount'] * 100);
            $input->SetNotify_url($this->notify_url);
            $input->SetTrade_type("NATIVE");
            $input->SetOpenid($open_id);
            $input->SetProduct_id($order_sn);
            $result = WxPayApi::unifiedOrder($input);

            // 初始化日志
            $logHandler = new CLogFileHandler(SRCPATH . "common/log/wechatpay/notify_" . date('Y-m-d') . '.txt');
            $log        = Log::Init($logHandler, 15);
            Log::DEBUG("unifiedorder:" . json_encode($result));

            $msg_data['status']   = 'y';
            $msg_data['action']   = 'process';
            $msg_data['order_sn'] = $order_sn;
            $this->CI->load->library('ChatLibrary');
            $this->CI->chatlibrary->send_to_user($this->customer_account, $chat_account, 1, 'Payment process', 'pay_notify', $msg_data);

            return $result;
        }

        public function ResponseToXml($data = array())
        {
            $xml_data['appid']       = WxPayConfig::APPID;
            $xml_data['mch_id']      = WxPayConfig::MCHID;
            $xml_data['nonce_str']   = isset($data['nonce_str']) ? $data['nonce_str'] : '';
            $xml_data['prepay_id']   = isset($data['prepay_id']) ? $data['prepay_id'] : '';
            $xml_data['return_msg']  = isset($data['return_msg']) ? $data['return_msg'] : 'FAIL';
            $xml_data['return_code'] = isset($data['return_code']) ? $data['return_code'] : 'FAIL';
            $xml_data['result_code'] = isset($data['result_code']) ? $data['result_code'] : 'FAIL';

            ksort($xml_data);
            reset($xml_data);

            $obj = WxPayResults::InitFromArray($xml_data, true);
            $obj->SetSign();

            $xml = $obj->ToXml();
            return $xml;
        }

        /**
         * 退款
         *
         * @param  [type] $order [description]
         * @return [type]        [description]
         */
        public function refund($order)
        {
            $logHandler = new CLogFileHandler(SRCPATH . "common/log/wechatpay/refund_" . date('Y-m-d') . '.txt', FILE_APPEND);
            $log        = Log::Init($logHandler, 15);
            Log::DEBUG("Begin refund");

            if(empty($order['trade_sn']) || empty($order['amount']) || empty($order['order_sn'])) {
                Log::DEBUG("Missing params:" . json_encode($order));
                return false;
            }

            $transaction_id = $order["trade_sn"];
            $total_fee      = $order["amount"] * 100;
            $refund_fee     = $total_fee;

            $input = new WxPayRefund();
            $input->SetTransaction_id($transaction_id);
            $input->SetTotal_fee($total_fee);
            $input->SetRefund_fee($refund_fee);
            $input->SetOut_refund_no($order['order_sn']);
            $input->SetOp_user_id(WxPayConfig::MCHID);
            $result = WxPayApi::refund($input);
            Log::DEBUG("Response data:" . json_encode($result));

            if(empty($result['return_code']) || strtoupper($result['return_code']) != 'SUCCESS') {
                Log::DEBUG("End refund:\n order" . json_encode($order) . "\n input" . json_encode($input));
                return false;
            }

//		if (empty($result['result_code']) || strtoupper($result['result_code']) != 'SUCCESS')
//		{
//			Log::DEBUG("End refund");
//			return false;
//		}

            return true;
        }

        /**
         * 退款查询
         *
         * @param  [type] $trade_sn [description]
         * @return [type]           [description]
         */
        public function refundQuery($trade_sn)
        {
            $input = new WxPayRefundQuery();
            $input->SetTransaction_id($trade_sn);

            return WxPayApi::refundQuery($input);
        }

        public function NotifyProcess($data, &$msg)
        {
            $data['verify_result'] = false;
            $data['verify_msg']    = '';
            $notfiyOutput          = array();

            try {
                Log::DEBUG("Wechatpay.NotifyProcess begin");

                if(empty($data['attach'])) {
                    $msg                   = "attach不存在";
                    $data['verify_result'] = false;
                    $data['verify_msg']    = $msg;
                    Log::DEBUG("call back:" . json_encode($data));
                }

                if(empty($data['out_trade_no'])) {
                    $msg                   = "商户订单号不存在";
                    $data['verify_result'] = false;
                    $data['verify_msg']    = $msg;
                    Log::DEBUG("call back:" . json_encode($data));
                    return false;
                }

                $order_sn = stripos($data['out_trade_no'], 'A') !== false ? substr($data['out_trade_no'], 0, 18) : substr($data['out_trade_no'], 0, 16);
                $this->CI->load->library('ChatLibrary');
                $encrypt_key          = md5(md5(WxPayConfig::APPID . WxPayConfig::KEY . WxPayConfig::MCHID . $order_sn));
                $user_account         = vaya_decrypt($data['attach'], $encrypt_key);
                $data['order_sn']     = $order_sn;
                $data['chat_account'] = $user_account;

                if(!array_key_exists("transaction_id", $data)) {
                    $msg                   = "输入参数不正确";
                    $data['verify_result'] = false;
                    $data['verify_msg']    = $msg;
                    Log::DEBUG("call back:" . json_encode($data));

                    $msg_data['status']   = 'n';
                    $msg_data['action']   = 'complete';
                    $msg_data['order_sn'] = $order_sn;
                    $this->CI->chatlibrary->send_to_user($this->customer_account, $user_account, 1, 'Payment failed', 'pay_notify', $msg_data);

                    return false;
                }

                //查询订单，判断订单真实性
                $is_app = isset($data['trade_type']) && strtolower($data['trade_type']) == 'app';
                if(!$this->Queryorder($data["transaction_id"], $is_app)) {
                    $msg                   = "订单查询失败";
                    $data['verify_result'] = false;
                    $data['verify_msg']    = $msg;
                    $data['is_app']        = $is_app;
                    Log::DEBUG("call back:" . json_encode($data));

                    $msg_data['status']   = 'n';
                    $msg_data['action']   = 'complete';
                    $msg_data['order_sn'] = $order_sn;
                    $this->CI->chatlibrary->send_to_user($this->customer_account, $user_account, 1, 'Payment failed', 'pay_notify', $msg_data);

                    return false;
                }

                // 获取订单信息
                $order_len = strlen($order_sn);
                $model     = $order_len == 16 ? 'order_model' : 'activity_order_model';
                $this->CI->load->model($model);
                $order = $this->CI->{$model}->get_single(array('order_sn' => $order_sn));
                if(!$order) {
                    $msg                   = "订单不存在";
                    $data['verify_result'] = false;
                    $data['verify_msg']    = $msg;
                    Log::DEBUG("call back:" . json_encode($data));

                    $msg_data['status']   = 'n';
                    $msg_data['action']   = 'complete';
                    $msg_data['order_sn'] = $order_sn;
                    $this->CI->chatlibrary->send_to_user($this->customer_account, $user_account, 1, 'Payment failed', 'pay_notify', $msg_data);

                    return false;
                }

                // 退款
                if(!empty($data['refund_id'])) {
                    if(!in_array($order['status'], array(-1, 1, 2))) {
                        $msg                   = "退款失败";
                        $data['verify_result'] = false;
                        $data['verify_msg']    = $msg;
                        Log::DEBUG("call back:" . json_encode($data));

                        return false;
                    }
                } else {
                    if(empty($data['result_code']) || $data['result_code'] != 'SUCCESS') {
                        $msg                   = "支付失败";
                        $data['verify_result'] = false;
                        $data['verify_msg']    = $msg;
                        Log::DEBUG("call back:" . json_encode($data));

                        $msg_data['status']   = 'n';
                        $msg_data['action']   = 'complete';
                        $msg_data['order_sn'] = $order_sn;
                        $this->CI->chatlibrary->send_to_user($this->customer_account, $user_account, 1, 'Payment failed', 'pay_notify', $msg_data);

                        return false;
                    }

                    // $this->CI->load->model('user_model');
                    // $user = $this->CI->user_model->get_single(array('user_id'=>$order['user_id']));
                    // $user_account = isset($user['chat_account'])? $user['chat_account']: '';
                    // $user_role = isset($user['role'])? $user['role']: '';

                    // 校验金额
                    if(empty($data['total_fee']) || $data['total_fee'] / 100 != $order['amount']) {
                        $msg                   = "订单金额不一致";
                        $data['verify_result'] = false;
                        $data['verify_msg']    = $msg;
                        Log::DEBUG("call back:" . json_encode($data));

                        $msg_data['status']   = 'n';
                        $msg_data['action']   = 'complete';
                        $msg_data['order_sn'] = $order_sn;
                        $this->CI->chatlibrary->send_to_user($this->customer_account, $user_account, 1, 'Payment failed', 'pay_notify', $msg_data);

                        return false;
                    }

                    // 校验币种
                    if(empty($data['fee_type']) || $data['fee_type'] != strtoupper($order['currency_code'])) {
                        $msg                   = "币种不一致";
                        $data['verify_result'] = false;
                        $data['verify_msg']    = $msg;
                        Log::DEBUG("call back:" . json_encode($data));

                        $msg_data['status']   = 'n';
                        $msg_data['action']   = 'complete';
                        $msg_data['order_sn'] = $order_sn;
                        $this->CI->chatlibrary->send_to_user($this->customer_account, $user_account, 1, 'Payment failed', 'pay_notify', $msg_data);

                        return false;
                    }

                    if($order['status'] > 0 || $order['status'] == -1) {
                        $msg                   = "订单已经支付，状态为：" . $order['status'];
                        $data['verify_result'] = false;
                        $data['verify_msg']    = $msg;
                        Log::DEBUG("call back:" . json_encode($data));

                        $msg_data['status']   = 'y';
                        $msg_data['action']   = 'complete';
                        $msg_data['order_sn'] = $order_sn;
                        $this->CI->chatlibrary->send_to_user($this->customer_account, $user_account, 1, 'Payment successful', 'pay_notify', $msg_data);

                        return true;
                    }

                    if($order['status'] < 0) {
                        $msg                   = "订单不是可支付状态";
                        $data['verify_result'] = false;
                        $data['verify_msg']    = $msg;
                        Log::DEBUG("call back:" . json_encode($data));

                        $msg_data['status']   = 'n';
                        $msg_data['action']   = 'complete';
                        $msg_data['order_sn'] = $order_sn;
                        $this->CI->chatlibrary->send_to_user($this->customer_account, $user_account, 1, 'Payment failed', 'pay_notify', $msg_data);

                        return false;
                    }

                    // 更新订单为已支付
                    $result = $this->CI->{$model}->update_paid_order($order['order_sn'], $data['transaction_id']);
                    if(!$result) {
                        $msg                   = "订单更新失败";
                        $data['verify_result'] = false;
                        $data['verify_msg']    = $msg;
                        Log::DEBUG("call back:" . json_encode($data));

                        $msg_data['status']   = 'n';
                        $msg_data['action']   = 'complete';
                        $msg_data['order_sn'] = $order_sn;
                        $this->CI->chatlibrary->send_to_user($this->customer_account, $user_account, 1, 'Payment failed', 'pay_notify', $msg_data);

                        return false;
                    }
                }

                $data['verify_result'] = true;
                $data['verify_msg']    = '订单更新成功';
                Log::DEBUG("call back:" . json_encode($data));

                $msg_data['status']   = 'y';
                $msg_data['action']   = 'complete';
                $msg_data['order_sn'] = $order_sn;
                $this->CI->chatlibrary->send_to_user($this->customer_account, $user_account, 1, 'Payment successful', 'pay_notify', $msg_data);
            } catch(Exception $e) {
                $msg = $e->errorMessage();
                Log::DEBUG("Wechatpay.NotifyProcess.msg:" . $msg);
                return false;
            }

            return true;
        }

        //重写回调处理函数

        public function Queryorder($transaction_id, $is_app = false)
        {
            $input = new WxPayOrderQuery();
            $input->SetTransaction_id($transaction_id);
            $result           = WxPayApi::orderQuery($input, 6, $is_app);
            $result['is_app'] = $is_app;
            Log::DEBUG("[-------- Wechatpay.Queryorder --------]:" . json_encode($result));
            if(array_key_exists("return_code", $result)
                && array_key_exists("result_code", $result)
                && $result["return_code"] == "SUCCESS"
                && $result["result_code"] == "SUCCESS"
            ) {
                Log::DEBUG("Wechatpay.Queryorder.return:true");
                return true;
            }
            Log::DEBUG("Wechatpay.Queryorder.return:false");
            return false;
        }

        /**
         * 校验通知
         *
         * @return [type] [description]
         */
        public function verifyNotify()
        {
            // 初始化日志
            $logHandler = new CLogFileHandler(SRCPATH . "common/log/wechatpay/notify_" . date('Y-m-d') . '.txt');
            $log        = Log::Init($logHandler, 15);
            Log::DEBUG("【begin notify】");

            $this->Handle(true);
        }

        /******************************************************** 刷卡（扫码）支付结束 ***********************************************************/
    }
