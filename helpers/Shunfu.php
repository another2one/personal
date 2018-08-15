<?php

    /**
     * 顺付支付类
     */
    class Shunfu
    {
        /**
         * 商户号
         *
         * @var string
         */
        protected $merchant_number = '';

        /**
         * 网关接入号
         *
         * @var string
         */
        protected $gateway_number = '';

        /**
         * 支付秘钥
         *
         * @var string
         */
        protected $key = '';

        /**
         * 测试支付网关
         *
         * @var [type]
         */
        protected $gateway = 'https://security.sslspay.com/securityPay'; // 正式支付网关

        /**
         *    查询交易信息网关
         *
         * @var string
         */
        protected $query_gateway = 'https://mer.ronghuibill.com/mer/traderecord/customerQuery.action';

        /**
         *    退款网关
         *
         * @var string
         */
        protected $refund_gateway = 'https://mer.ronghuibill.com/mer/traderecord/refundInterface.action';

        /**
         * 货币代码，三位大写字母
         *
         * @var [type]
         */
        protected $currency_code = 'USD';

        /**
         * 异步通知地址
         *
         * @var [type]
         */
        protected $return_url;

        /**
         * 取消付款后从顺付跳转回来的url
         *
         * @var [type]
         */
        protected $cancel_url;

        /**
         * 付款后从顺付跳转回来的url
         *
         * @var [type]
         */
        protected $inform_url;

        /**
         * CI对象
         *
         * @var [type]
         */
        protected $CI;

        /**
         * [__construct description]
         *
         * @param array $params [description]
         */
        function __construct($params = array())
        {
            $CI       = &get_instance();
            $this->CI = $CI;

            $domain_main      = $CI->config->item('domain_main');
            $this->return_url = $domain_main . 'pay/notify/shunfu';
            $this->cancel_url = $domain_main . 'pay/paypal_cancel';
            $this->inform_url = $domain_main . 'pay/pay_return/shunfu';

            !empty($params['currency_code']) && $this->currency_code = $params['currency_code'];
        }

        /**
         * 创建支付表单
         *
         * @param $params 订单数组
         * @return 提交表单HTML文本
         */
        public function pay($params)
        {
            // 待请求参数数组
            $trade_data = $this->buildRequestPara($params);
            if(!$trade_data) {
                return false;
            }

            $pay_data['tradeData'] = $trade_data;
            $pay_data['merNo']     = $this->merchant_number;
            $pay_data['gatewayNo'] = $this->gateway_number;
            $pay_data              = http_build_query($pay_data, '', '&');

            $result = $this->curl_post($this->gateway, $pay_data);

            if($result && !is_numeric($result)) {
                $return_data  = json_decode(json_encode(simplexml_load_string($result, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
                $return_code  = isset($return_data['returnCode']) ? $return_data['returnCode'] : '';
                $redirect_url = isset($return_data['thirdDomainUrl']) ? $return_data['thirdDomainUrl'] : '';

                if($return_code == -1) {
                    header("Location:" . $redirect_url);
                    return true;
                }
            }

            return false;
        }

        /**
         * 构建支付xml数据
         *
         * @param  [type] $params [description]
         * @return [type]         [description]
         */
        public function buildRequestPara($params)
        {
            $this->CI->load->helper('common');

            $requests = array(
                'order_sn', 'goods_name', 'goods_qty', 'goods_price', 'order_amount',
                'card_number', 'email', 'c_code', 'expire_month', 'expire_year',
                'bill_address', 'bill_city', 'bill_state', 'bill_country', 'bill_zip'
            );

            $is_valid = true;

            foreach($requests as $request) {
                if(empty($params[$request])) {
                    $is_valid = false;
                    break;
                }
            }

            if(!$is_valid) {
                return false;
            }

            // 车辆服务
            $car_num   = isset($params['car_num']) ? intval($params['car_num']) : 0;
            $car_price = isset($params['car_price']) ? number_format($params['car_price'], 2) : 0;

            // 导游服务
            $tour_num   = isset($params['tour_num']) ? intval($params['tour_num']) : 0;
            $tour_price = isset($params['tour_price']) ? number_format($params['tour_price'], 2) : 0;

            // 积分抵扣费用
            $point_fee = isset($params['point_fee']) ? number_format($params['point_fee'], 2) : 0;

            $tmp_xml = '';
            if($car_num && $car_price) {
                $tmp_xml .= '<goods><goodsName>Car Service</goodsName><qty>' . $car_num . '</qty><price>' . $car_price . '</price></goods>';
            }

            if($tour_num && $tour_price) {
                $tmp_xml .= '<goods><goodsName>Tour Service</goodsName><qty>' . $tour_num . '</qty><price>' . $tour_price . '</price></goods>';
            }

            $sign        = $this->buildSign($params['email'], $params['order_sn'], $params['order_amount']);
            $broser_type = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'ie';
            $broser_lang = isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : 'en';

            // 订单信息
            $xml = '<?xml version="1.0" encoding="UTF-8"?>';
            $xml .= '<order><merNo>' . $this->merchant_number . '</merNo><orderNo>' . $params['order_sn'] . '</orderNo>';
            $xml .= '<goodsList><goods><goodsName>' . $params['goods_name'] . '</goodsName><qty>' . $params['goods_qty'] . '</qty>';
            $xml .= '<price>' . (number_format($params['goods_price'] - $point_fee, 2)) . '</price></goods>' . $tmp_xml . '</goodsList>';
            $xml .= '<orderAmount>' . $params['order_amount'] . '</orderAmount><currency>' . $this->currency_code . '</currency>';

            // 持卡人基本信息
            $xml .= '<firstName>firstname</firstName><lastName>lastname</lastName><email>test@hotmail.com</email>';
            $xml .= '<phone>12345678</phone><issuingBank>ccb</issuingBank>';
            $xml .= '<cardNo>' . $params['card_number'] . '</cardNo><cCode>' . $params['c_code'] . '</cCode>';
            $xml .= '<cardExpireMonth>' . $params['expire_month'] . '</cardExpireMonth>';
            $xml .= '<cardExpireYear>' . $params['expire_year'] . '</cardExpireYear>';
            $xml .= '<ip>' . get_ip() . '</ip><broserType>' . $broser_type . '</broserType>';
            $xml .= '<browserLang>' . $broser_lang . '</browserLang><sessionId>' . session_id() . '</sessionId>';

            // 账单信息
            $xml .= '<billAddress>' . $params['bill_address'] . '</billAddress><billCity>' . $params['bill_city'] . '</billCity>';
            $xml .= '<billState>' . $params['bill_state'] . '</billState><billCountry>' . $params['bill_country'] . '</billCountry>';
            $xml .= '<billZip>' . $params['bill_zip'] . '</billZip>';

            // 收货地址
            $xml .= '<shipCountry>' . $params['bill_country'] . '</shipCountry><shipState>' . $params['bill_state'] . '</shipState>';
            $xml .= '<shipCity>' . $params['bill_city'] . '</shipCity><shipAddress>' . $params['bill_address'] . '</shipAddress>';
            $xml .= '<sFirstName>sFirstName</sFirstName><sLastName>sLastName</sLastName>';
            $xml .= '<shipZip>' . $params['bill_zip'] . '</shipZip>';

            // 加密信息
            $xml .= '<signData>' . $sign . '</signData>';

            // 接口参数
            $xml .= '<gatewayNo>' . $this->gateway_number . '</gatewayNo><returnURL>' . $this->return_url . '</returnURL>';
            $xml .= '<informURL>' . $this->inform_url . '/' . $params['order_sn'] . '</informURL>';
            $xml .= '<remark></remark></order>';

            return $this->encrypt($xml, $this->key);
        }

        /**
         * 生成支付签名
         *
         * @param  [type] $email        [description]
         * @param  [type] $order_sn     [description]
         * @param  [type] $order_amount [description]
         * @return [type]               [description]
         */
        public function buildSign($email, $order_sn, $order_amount)
        {
            // sha256(merNo+ orderNo + orderAmount + email+returnURL+currency+ signKey).

            $input = $this->merchant_number . $order_sn . $order_amount . $email . $this->return_url . $this->currency_code . $this->key;

            return hash('sha256', $input);
        }

        /**
         * [encrypt description]
         *
         * @param  [type] $input [description]
         * @param  [type] $key   [description]
         * @return [type]        [description]
         */
        protected function encrypt($input, $key)
        {
            $size  = mcrypt_get_block_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_ECB);
            $input = $this->pkcs5_pad($input, $size);
            $td    = mcrypt_module_open(MCRYPT_RIJNDAEL_128, '', MCRYPT_MODE_ECB, '');
            $iv    = mcrypt_create_iv(mcrypt_enc_get_iv_size($td), MCRYPT_RAND);

            mcrypt_generic_init($td, $key, $iv);

            $data = mcrypt_generic($td, $input);

            mcrypt_generic_deinit($td);
            mcrypt_module_close($td);

            $data = base64_encode($data);
            return $data;
        }

        /**
         * [pkcs5_pad description]
         *
         * @param  [type] $text      [description]
         * @param  [type] $blocksize [description]
         * @return [type]            [description]
         */
        protected function pkcs5_pad($text, $blocksize)
        {
            $pad = $blocksize - (strlen($text) % $blocksize);
            return $text . str_repeat(chr($pad), $pad);
        }

        /**
         * [curl_post description]
         *
         * @param  [type] $url       [description]
         * @param  [type] $post_data [description]
         * @param  array $header [description]
         * @return [type]            [description]
         */
        public function curl_post($url, $post_data, $header = array())
        {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $post_data);
            curl_setopt($ch, CURLOPT_REFERER, $_SERVER['HTTP_HOST']);

            if(!empty($header)) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
            }

            $result = curl_exec($ch);
            curl_close($ch);

            return $result;
        }

        /**
         * 接收异步通知
         *
         * @return [type] [description]
         */
        public function notify()
        {
            $log_file = COMPATH . 'log/shunfu/shunfu_notify_log.txt';

            $return_code = isset($_POST['returnCode']) ? $_POST['returnCode'] : '';
            $return_msg  = isset($_POST['returnMsg']) ? $_POST['returnMsg'] : '';

            $order_sn      = isset($_POST['orderNo']) ? $_POST['orderNo'] : '';
            $trade_sn      = isset($_POST['tradeNo']) ? $_POST['tradeNo'] : '';
            $order_amount  = isset($_POST['orderAmount']) ? $_POST['orderAmount'] : '';
            $currency_code = isset($_POST['orderCurrency']) ? $_POST['orderCurrency'] : '';
            $sign          = isset($_POST['signData']) ? $_POST['signData'] : '';

            // 校验状态
            if(!is_numeric($return_code) || intval($return_code) != 0) {
                $msg = 'returnCode error:' . $return_code;
                file_put_contents($log_file, date('Y-m-d H:i:s', time()) . "\n" . serialize($_POST) . ' ' . $msg, FILE_APPEND);
                return false;
            }

            // 校验订单号
            if(!$order_sn) {
                file_put_contents($log_file, date('Y-m-d H:i:s', time()) . "\n" . serialize($_POST) . ' missing orderNo', FILE_APPEND);
                return false;
            }

            // 校验交易号
            if(!$trade_sn) {
                file_put_contents($log_file, date('Y-m-d H:i:s', time()) . "\n" . serialize($_POST) . ' missing tradeNo', FILE_APPEND);
                return false;
            }

            // 订单金额
            if(!is_numeric($order_amount)) {
                file_put_contents($log_file, date('Y-m-d H:i:s', time()) . "\n" . serialize($_POST) . ' missing orderAmount', FILE_APPEND);
                return false;
            }

            // 校验币种
            if(!$currency_code) {
                file_put_contents($log_file, date('Y-m-d H:i:s', time()) . "\n" . serialize($_POST) . ' missing orderCurrency', FILE_APPEND);
                return false;
            }

            // 校验签名 sha256(orderCurrency+returnCode+orderAmount + tradeNo + orderNo +signKey)
            if($sign != hash('sha256', $currency_code . $return_code . $order_amount . $trade_sn . $order_sn . $this->key)) {
                file_put_contents($log_file, date('Y-m-d H:i:s', time()) . "\n" . serialize($_POST) . ' sign error', FILE_APPEND);
                return false;
            }

            // 交易号是否已存在
            $this->CI->load->model('order_model');
            $trade_exist = $this->CI->order_model->get_single(array('trade_sn' => $trade_sn));
            if($trade_exist) {
                $msg = 'tradeNo is already exist.';
                file_put_contents($log_file, date('Y-m-d H:i:s', time()) . "\n" . serialize($_POST) . ' ' . $msg, FILE_APPEND);
                return false;
            }

            // 获取订单信息
            $order = $this->CI->order_model->get_single(array('order_sn' => $order_sn));
            if(!$order) {
                $msg = 'order dose not exist.';
                file_put_contents($log_file, date('Y-m-d H:i:s', time()) . "\n" . serialize($_POST) . ' ' . $msg, FILE_APPEND);
                return false;
            }

            // 币种
            if($currency_code != strtoupper($order['currency_code'])) {
                $msg = 'orderCurrency error.';
                file_put_contents($log_file, date('Y-m-d H:i:s', time()) . "\n" . serialize($_POST) . ' ' . $msg, FILE_APPEND);
                return false;
            }

            // 订单金额
            if($order_amount != $order['amount']) {
                $msg = 'orderAmount error.';
                file_put_contents($log_file, date('Y-m-d H:i:s', time()) . "\n" . serialize($_POST) . ' ' . $msg, FILE_APPEND);
                return false;
            }

            if($order['status'] != 0) {
                $msg = 'The current status of this order is not allow to pay.';
                file_put_contents($log_file, date('Y-m-d H:i:s', time()) . "\n" . serialize($_POST) . ' ' . $msg, FILE_APPEND);
                return false;
            }

            // 获取交易信息
            // $payment_details = $this->getPaymentDetails($order);
            // $return_status = isset($payment_details['returnStatus'])? $payment_details['returnStatus']: '';
            // $query_result = isset($payment_details['queryResult'])? $payment_details['queryResult']: '';
            // $refund_status = isset($payment_details['refundStatus'])? $payment_details['refundStatus']: '';

            // if ($return_status != 1)
            // {
            // 	$msg = 'query failed, return status:'.$return_status;
            // 	file_put_contents($log_file, date('Y-m-d H:i:s',time())."\n".serialize($_POST).' '.$msg,FILE_APPEND);
            // 	return false;
            // }

            // if ($query_result != 1 && ( ! is_numeric($refund_status) || $refund_status != 0))
            // {
            // 	$msg = 'pay failed, queryResult:'.$query_result.' refundStatus:'.$refund_status;
            // 	file_put_contents($log_file, date('Y-m-d H:i:s',time())."\n".serialize($_POST).' '.$msg,FILE_APPEND);
            // 	return false;
            // }

            // 更新订单的状态
            $update_res = $this->CI->order_model->update_paid_order($order['order_sn'], $trade_sn);
            return $update_res;
        }

        /**
         * 退款，支持多个订单同时退款，目前做成单个订单退款
         *
         * @param array $refund_orders 退款的订单数据，二维数组,具体参数如下：
         *                             tradeNo：交易号
         *                             currency：币种
         *                             tradeAmount：订单金额
         *                             refundAmount：退款金额
         *                             refundReason：退款原因（1：缺货 2：协商退款 3：货物被退回 4：客户取消订单 5：折扣 6：重复支付 7：可疑订单 8：其他
         *                             9：冻结单银行默认退款）
         *                             refundRemark：退款备注
         * @return [type] [description]
         */
        public function refund($refund_orders)
        {
            $log_file = COMPATH . 'log/shunfu/shunfu_refund_log.txt';
            $requests = array('tradeNo', 'currency', 'tradeAmount', 'refundAmount', 'refundReason');

            if(!is_array($refund_orders) || empty($refund_orders)) {
                return false;
            }

            $trade_sn = '';

            foreach($refund_orders as $key => $order) {
                foreach($requests as $request) {
                    if(empty($order[$request])) {
                        $log_content = "\n\n\n" . '/=========================================== ' . date('Y-m-d H:i:s');
                        $log_content .= ' =============================================/' . "\n";
                        $log_content .= 'Missing ' . $request . "\n";
                        $log_content .= '/==========================================================';
                        $log_content .= '=======================================================/' . "\n";

                        file_put_contents($log_file, $log_content, FILE_APPEND);

                        return false;
                    } else {
                        $refundOrders[$key][$request] = $order[$request];
                    }
                }

                $trade_sn .= $order['tradeNo'];

                !empty($order['refundRemark']) && $refundOrders[$key]['refundRemark'] = $order['refundRemark'];
            }

            // signInfo=sha256(merNo+gatewayNo+tradeNo+signkey) 例如：(merNo+gatewayNo+tradeNo(1)+...+tradeNo(n) + signkey);
            $sign = hash('sha256', $this->merchant_number . $this->gateway_number . $trade_sn . $this->key);

            $refund_params['merNo']        = $this->merchant_number;
            $refund_params['gatewayNo']    = $this->gateway_number;
            $refund_params['signInfo']     = $sign;
            $refund_params['refundOrders'] = $refundOrders;

            $curl_header = array('Content-Type: application/json', 'Content-Length: ' . strlen(json_encode($refund_params)));

            $result      = $this->curl_post($this->refund_gateway, json_encode($refund_params), $curl_header);
            $return_data = $result ? json_decode($result, true) : array();

            // 日志
            $log_content = "\n\n\n" . '/=========================================== ' . date('Y-m-d H:i:s');
            $log_content .= ' =============================================/' . "\n";
            $log_content .= 'Post Params ' . json_encode($refund_params) . "\n\n";
            $log_content .= 'Return Data ' . $result . "\n\n";
            $log_content .= '/==========================================================';
            $log_content .= '=======================================================/' . "\n";

            file_put_contents($log_file, $log_content, FILE_APPEND);

            $this->CI->load->model('order_model');

            // 返回的状态
            $return_status = isset($return_data['errorStatus']) ? $return_data['errorStatus'] : '';

            if($return_status == 1) {
                if(!empty($return_data['refundOrders']) && is_array($return_data['refundOrders'])) {
                    foreach($return_data['refundOrders'] as $refund_order) {
                        $refund_status = isset($refund_order['refundStatus']) ? $refund_order['refundStatus'] : '';

                        if($refund_status == 2) {
                            return true;
                        }
                    }

                }
            }

            return false;
        }

        /**
         * [verifyReturn description]
         *
         * @param  [type] $order_sn [description]
         * @return [type]           [description]
         */
        public function verifyReturn($order_sn)
        {
            $this->CI->load->model('order_model');
            $order = $this->CI->model->get_single(array('order_sn' => $order_sn), 'status');

            $status = isset($order['status']) ? $order['status'] : '';

            return $status == 1 ? true : false;
        }

        /**
         * 获取交易详情
         *
         * @param  [type] $order [description]
         * @return [type]        [description]
         */
        public function getPaymentDetails($order)
        {
            $post_data['merNo']     = $this->merchant_number;
            $post_data['gatewayNo'] = $this->gateway_number;
            $post_data['orderNo']   = isset($order['order_sn']) ? $order['order_sn'] : '';
            $post_data['signInfo']  = hash('sha256', $post_data['merNo'] . $post_data['gatewayNo'] . $post_data['orderNo'] . $this->key);
            $post_data              = http_build_query($post_data, '', '&');

            $result = $this->curl_post($this->query_gateway, $post_data);

            return json_decode($result);
        }
    }