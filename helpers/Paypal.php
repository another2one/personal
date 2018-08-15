<?php

    /**
     * Paypal支付类
     */
    class Paypal
    {
        /**
         * 商家账号
         *
         * @var string
         */
        protected $seller_username;

        /**
         * 商家账号登录密码
         *
         * @var string
         */
        protected $seller_password;

        /**
         * 商家签名
         *
         * @var string
         */
        protected $seller_signature;

        /**
         *    是否使用代理服务器
         *
         * @var boolean
         */
        protected $use_proxy = false;

        /**
         * 代理服务器主机
         *
         * @var [type]
         */
        protected $proxy_host = '127.0.0.1';

        /**
         * 代理服务器主机端口
         *
         * @var [type]
         */
        protected $proxy_port = '808';

        /**
         * paypal校验地址
         *
         * @var string
         */
        protected $paypal_verify_url;

        /**
         * In-Context in Express Checkout URLs for Sandbox
         *
         * @var string
         */
        protected $checkout_url;
        // protected $checkout_url = 'https://www.paypal.com/checkoutnow?token=';

        /**
         * In-Context in Express Checkout URLs for Sandbox
         *
         * @var string
         */
        protected $nvp_endpoint;
        // protected $nvp_endpoint = 'https://api-3t.paypal.com/nvp';

        /**
         * 付款后从Paypal跳转回来的url
         *
         * @var [type]
         */
        protected $return_url;

        /**
         * 取消付款后从Paypal跳转回来的url
         *
         * @var [type]
         */
        protected $cancel_url;

        /**
         * 异步通知地址
         *
         * @var [type]
         */
        protected $notify_url;

        /**
         * 当前接口版本
         *
         * @var [type]
         */
        protected $api_version = '109.0';

        /**
         * ButtonSource Tracker Code,is only applicable for partners
         *
         * @var string
         */
        protected $sbn_code = 'PP-DemoPortal-EC-IC-php';

        /**
         * CI对象
         *
         * @var [type]
         */
        protected $CI;

        /**
         * 防止更改收货地址
         *
         * @var boolean
         */
        protected $address_override = true;

        /**
         * paypal退款日志文件
         *
         * @var string
         */
        protected $refund_log;

        /**
         * 客服聊天账号，用于发送及时消息通知
         *
         * @var string
         */
        protected $customer_account = '';

        function __construct($params = array())
        {
            $CI            = &get_instance();
            $paypal_config = $CI->config->item('paypal');

            $this->CI                = $CI;
            $this->seller_username   = isset($paypal_config['seller_username']) ? $paypal_config['seller_username'] : '';
            $this->seller_password   = isset($paypal_config['seller_password']) ? $paypal_config['seller_password'] : '';
            $this->seller_signature  = isset($paypal_config['seller_signature']) ? $paypal_config['seller_signature'] : '';
            $this->paypal_verify_url = isset($paypal_config['paypal_verify_url']) ? $paypal_config['paypal_verify_url'] : '';
            $this->checkout_url      = isset($paypal_config['checkout_url']) ? $paypal_config['checkout_url'] : '';
            $this->nvp_endpoint      = isset($paypal_config['nvp_endpoint']) ? $paypal_config['nvp_endpoint'] : '';

            $domain_web       = $CI->config->item('domain_web');
            $this->return_url = $domain_web . 'pay/pay_return/paypal';
            $this->cancel_url = $domain_web . 'pay/paypal_cancel';
            $this->notify_url = $domain_web . 'pay/notify/paypal';

            $this->refund_log = SRCPATH . 'common/log/paypal/refund_log.txt';

            if(!empty($params)) {
                foreach($params as $key => $value) {
                    $this->setValue($key, $value);
                }
            }
        }

        protected function setValue($key, $value)
        {
            $vals = get_class_vars(get_class($this));
            if(in_array($key, $vals)) {
                $this->$key = $value;
                return true;
            }

            return false;
        }

        /*public function getValue($key)
        {
            $vals = get_class_vars(get_class($this));
            if (in_array($key, $vals)) {
                return $this->$key;
            }

            return false;
        }*/

        public function pay($order)
        {
            $pay_params = $this->buildPayParams($order);
            $nvpstr     = $this->buildNvpStr($pay_params);

            if($nvpstr !== false) {
                $result_arr = $this->hash_call("SetExpressCheckout", $nvpstr);
                $ack        = isset($result_arr["ACK"]) ? strtoupper($result_arr["ACK"]) : '';

                if($ack == "SUCCESS" || $ack == "SUCCESSWITHWARNING") {
                    $token             = urldecode($result_arr["TOKEN"]);
                    $_SESSION['TOKEN'] = $token;

                    $this->RedirectToPayPal($token);
                }
            }

            return false;
        }

        /**
         * 构造支付参数
         *
         * @param  [type] $[name] [description]
         * @return [type] [description]
         */
        public function buildPayParams($order)
        {
            $CI            = $this->CI;
            $domain_static = $CI->config->item('domain_static');

            $pay_params = array();
            $relations  = array(
                'title'    => 'PAYMENTREQUEST_0_NAME',
                'book_num' => 'PAYMENTREQUEST_0_QTY',
                'amount'   => 'PAYMENTREQUEST_0_AMT',
            );

            $order['amount'] = number_format($order['amount'], 2, '.', '');

            foreach($relations as $key => $relation) {
                if(!isset($order[$key]) && $order[$key]) {
                    return false;
                }

                $pay_params[$relation] = $order[$key];
                if($key == 'amount') {
                    $book_num = isset($order['book_num']) ? $order['book_num'] : 1;
                    // $pay_params['PAYMENTREQUEST_0_ITEMAMT'] = intval($order['amount'] / $book_num);
                    $pay_params['PAYMENTREQUEST_0_ITEMAMT'] = $order['amount'];
                }
            }

            $pay_params['LOGOIMG']    = $domain_static . 'images/web/site_logo.png';
            $pay_params['LOCALECODE'] = 'en_US';
            // $pay_params['USERSELECTEDFUNDINGSOURCE'] = 'CreditCard';
            $pay_params['RETURNURL']            = $this->return_url;
            $pay_params['CANCELURL']            = $this->cancel_url;
            $pay_params['NOSHIPPING']           = 1;
            $pay_params['PAYMENTREQUEST_0_QTY'] = 1;

            $pay_params['PAYMENTREQUEST_0_CURRENCYCODE']  = 'USD';
            $pay_params['PAYMENTREQUEST_0_PAYMENTACTION'] = 'Sale';
            $pay_params['PAYMENTREQUEST_0_CUSTOM']        = $this->buildSign($order);
            $pay_params['PAYMENTREQUEST_0_INVNUM']        = isset($order['order_sn']) ? $order['order_sn'] : '';
            $pay_params['PAYMENTREQUEST_0_NUMBER']        = isset($order['order_sn']) ? $order['order_sn'] : '';
            $pay_params['PAYMENTREQUEST_0_DESC']          = $pay_params['PAYMENTREQUEST_0_NAME'];

            return $pay_params;
        }

        public function buildSign($order)
        {
            $seller_username = $this->seller_username;
            $order_id        = isset($order['order_id']) ? $order['order_id'] : '';
            $order_sn        = isset($order['order_sn']) ? $order['order_sn'] : '';
            $amount          = isset($order['amount']) ? intval($order['amount']) : '';

            if(!$seller_username || !$order_id || !$order_sn || !$amount) {
                return false;
            }

            $sign = md5($seller_username) . md5($order_id) . md5($order_sn) . md5($amount) . md5($seller_username);
            $sign = md5($sign);

            return $sign;
        }

        /**
         * 拼接参数
         *
         * @param  [type] $pay_params [description]
         * @return [type]             [description]
         */
        public function buildNvpStr($pay_params)
        {
            $nvpstr = '';
            if(is_array($pay_params) && !empty($pay_params)) {
                foreach($pay_params as $param_name => $param_val) {
                    $nvpstr .= '&' . strtoupper($param_name) . '=' . $param_val;
                }

                // $nvpstr .= "&LANDINGPAGE=Billing";
                // $nvpstr .= "&USERSELECTEDFUNDINGSOURCE=BML";

                return $nvpstr;
            }

            return false;
        }

        public function hash_call($methodName, $nvpStr)
        {
            //setting the curl parameters.
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->nvp_endpoint);
            curl_setopt($ch, CURLOPT_VERBOSE, 1);

            //turning off the server and peer verification(TrustManager Concept).
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            // curl_setopt($ch, CURLOPT_SSLVERSION, CURL_SSLVERSION_TLSv1_2);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);

            //if USE_PROXY constant set to TRUE in Constants.php, then only proxy will be enabled.
            //Set proxy name to PROXY_HOST and port number to PROXY_PORT in constants.php
            if($this->use_proxy)
                curl_setopt($ch, CURLOPT_PROXY, $this->proxy_host . ":" . $this->proxy_port);

            //NVPRequest for submitting to server
            $nvpreq = "METHOD=" . urlencode($methodName) . "&VERSION=" . urlencode($this->api_version) . "&PWD=" . urlencode($this->seller_password) . "&USER=" . urlencode($this->seller_username) . "&SIGNATURE=" . urlencode($this->seller_signature) . $nvpStr . "&BUTTONSOURCE=" . urlencode($this->sbn_code);

            //setting the nvpreq as POST FIELD to curl
            curl_setopt($ch, CURLOPT_POSTFIELDS, $nvpreq);

            //getting response from server
            $response = curl_exec($ch);

            //convrting NVPResponse to an Associative Array
            $nvpResArray             = $this->deformatNVP($response);
            $nvpReqArray             = $this->deformatNVP($nvpreq);
            $_SESSION['nvpReqArray'] = $nvpReqArray;

            if(curl_errno($ch)) {
                // moving to display page to display curl errors
                $_SESSION['curl_error_no']  = curl_errno($ch);
                $_SESSION['curl_error_msg'] = curl_error($ch);

                //Execute the Error handling module to display errors.
            } else {
                //closing the curl
                curl_close($ch);
            }

            return $nvpResArray;
        }

        public function deformatNVP($nvpstr)
        {
            $intial   = 0;
            $nvpArray = array();

            while(strlen($nvpstr)) {
                //postion of Key
                $keypos = strpos($nvpstr, '=');
                //position of value
                $valuepos = strpos($nvpstr, '&') ? strpos($nvpstr, '&') : strlen($nvpstr);

                /*getting the Key and Value values and storing in a Associative Array*/
                $keyval = substr($nvpstr, $intial, $keypos);
                $valval = substr($nvpstr, $keypos + 1, $valuepos - $keypos - 1);
                //decoding the respose
                $nvpArray[urldecode($keyval)] = urldecode($valval);
                $nvpstr                       = substr($nvpstr, $valuepos + 1, strlen($nvpstr));
            }

            return $nvpArray;
        }

        public function RedirectToPayPal($token)
        {
            // Redirect to paypal.com here
            // With useraction=commit user will see "Pay Now" on Paypal website and when user clicks "Pay Now" and returns to our website we can call DoExpressCheckoutPayment API without asking the user
            $payPalURL = $this->checkout_url . $token;
            if($_SESSION['EXPRESS_MARK'] == 'ECMark') {
                $payPalURL = $payPalURL . '&useraction=commit';
            } else {
                if($this->address_override)
                    $payPalURL = $payPalURL . '&useraction=commit';
            }

            header("Location:" . $payPalURL);
            exit;
        }

        public function refund($order)
        {

            $trade_sn = isset($order['trade_sn']) ? $order['trade_sn'] : '';
            if($trade_sn == '') {
                $log_content = '/=========================================== ' . date('Y-m-d H:i:s') . ' =============================================/' . "\n";
                $log_content .= 'Missing trade_sn.' . "\n";
                $log_content .= '/=================================================================================================================/' . "\n";

                file_put_contents($this->refund_log, $log_content, FILE_APPEND);

                return false;
            }

            $nvpstr        = '&TRANSACTIONID=' . $trade_sn . '&REFUNDTYPE=Full';
            $refund_result = $this->hash_call('RefundTransaction', $nvpstr);

            $log_content = '/=========================================== ' . date('Y-m-d H:i:s') . ' =============================================/' . "\n";
            $log_content .= json_encode($refund_result) . "\n";
            $log_content .= '/=================================================================================================================/' . "\n";

            file_put_contents($this->refund_log, $log_content, FILE_APPEND);

            $ack = isset($refund_result['ACK']) ? $refund_result['ACK'] : '';
            if(trim(strtoupper($ack)) == 'SUCCESS') {
                return true;
            }

            return false;
        }

        /**
         * 校验支付返回的数据
         *
         * @return [type] [description]
         */
        public function verifyReturn()
        {
            // 日志文件
            $log_file = SRCPATH . 'common/log/paypal/verify_return_log.txt';

            // $finalPaymentAmount =  $_SESSION["Payment_Amount"];
            $payer_id = isset($_GET['PayerID']) ? $_GET['PayerID'] : '';

            // Check to see if the Request object contains a variable named 'token'	or Session object contains a variable named TOKEN
            $token = isset($_REQUEST['token']) ? $_REQUEST['token'] : '';

            // If the Request object contains the variable 'token' then it means that the user is coming from PayPal site.
            if($token != '' && $payer_id != '') {
                /*
                * Calls the GetExpressCheckoutDetails API call
                */
                $payment_details       = $this->getPaymentDetails($token);
                $ackGetExpressCheckout = isset($payment_details["ACK"]) ? strtoupper($payment_details["ACK"]) : '';
                if($ackGetExpressCheckout == "SUCCESS" || $ackGetExpressCheckout == "SUCESSWITHWARNING") {
                    /*
                    * The information that is returned by the GetExpressCheckoutDetails call should be integrated by the partner into his Order Review
                    * page
                    */
                    // $email 				= $payment_details["EMAIL"]; // ' Email address of payer.
                    $payerId = isset($payment_details["PAYERID"]) ? $payment_details["PAYERID"] : ''; // ' Unique PayPal customer account identification number.
                    // $firstName			= $payment_details["FIRSTNAME"]; // ' Payer's first name.
                    // $lastName			= $payment_details["LASTNAME"]; // ' Payer's last name.

                    $totalAmt     = isset($payment_details["PAYMENTREQUEST_0_AMT"]) ? $payment_details["PAYMENTREQUEST_0_AMT"] : ''; // ' Total Amount to be paid by buyer
                    $currencyCode = isset($payment_details["CURRENCYCODE"]) ? $payment_details["CURRENCYCODE"] : ''; // 'Currency being used

                    $order_sn  = isset($payment_details['PAYMENTREQUEST_0_INVNUM']) ? $payment_details['PAYMENTREQUEST_0_INVNUM'] : '';
                    $order_len = strlen($order_sn);
                    $sign      = isset($payment_details['PAYMENTREQUEST_0_CUSTOM']) ? $payment_details['PAYMENTREQUEST_0_CUSTOM'] : '';

                    if(!$order_sn) {
                        $log_content = '/=========================================== ' . date('Y-m-d H:i:s') . ' =============================================/' . "\n";
                        $log_content .= 'Missing order_sn.' . "\n";
                        $log_content .= serialize($payment_details) . "\n";
                        $log_content .= '/=================================================================================================================/' . "\n";

                        file_put_contents($log_file, $log_content, FILE_APPEND);
                        return false;
                    }

                    $model = $order_len == 16 ? 'order_model' : 'activity_order_model';
                    $this->CI->load->model($model);
                    $order = $this->CI->{$model}->get_single(array('order_sn' => $order_sn));
                    if(!$order) {
                        $log_content = '/=========================================== ' . date('Y-m-d H:i:s') . ' =============================================/' . "\n";
                        $log_content .= 'The order dose not exist.' . "\n";
                        $log_content .= serialize($payment_details) . "\n";
                        $log_content .= '/=================================================================================================================/' . "\n";

                        file_put_contents($log_file, $log_content, FILE_APPEND);
                        return false;
                    }

                    if(intval($totalAmt) != intval($order['amount'])) {
                        $log_content = '/=========================================== ' . date('Y-m-d H:i:s') . ' =============================================/' . "\n";
                        $log_content .= 'Order amount verify failed.' . "\n";
                        $log_content .= serialize($payment_details) . "\n";
                        $log_content .= '/=================================================================================================================/' . "\n";

                        file_put_contents($log_file, $log_content, FILE_APPEND);
                        return false;
                    }

                    if(strtoupper($currencyCode) != strtoupper($order['currency_code'])) {
                        $log_content = '/=========================================== ' . date('Y-m-d H:i:s') . ' =============================================/' . "\n";
                        $log_content .= 'Currency code verify failed.' . "\n";
                        $log_content .= serialize($payment_details) . "\n";
                        $log_content .= '/=================================================================================================================/' . "\n";

                        file_put_contents($log_file, $log_content, FILE_APPEND);
                        return false;
                    }

                    if($sign != $this->buildSign($order)) {
                        $log_content = '/=========================================== ' . date('Y-m-d H:i:s') . ' =============================================/' . "\n";
                        $log_content .= 'Sign verify failed.' . "\n";
                        $log_content .= serialize($payment_details) . "\n";
                        $log_content .= '/=================================================================================================================/' . "\n";

                        file_put_contents($log_file, $log_content, FILE_APPEND);
                        return false;
                    }

                    $trip_activity_id    = $order_len == 16 ? 'trip_id' : 'activity_id';
                    $trip_activity_name  = $order_len == 16 ? 'trip' : 'activity';
                    $trip_activity_model = $order_len == 16 ? 'trip_model' : 'activity_model';
                    $this->CI->load->model($trip_activity_model);
                    $trip_activity = $this->CI->{$trip_activity_model}->get_single(array($trip_activity_id => $order[$trip_activity_id]));
                    if(!$trip_activity) {
                        $log_content = '/=========================================== ' . date('Y-m-d H:i:s') . ' =============================================/' . "\n";
                        $log_content .= 'The ' . $trip_activity_name . ' dose not exist.' . "\n";
                        $log_content .= serialize($payment_details) . "\n";
                        $log_content .= '/=================================================================================================================/' . "\n";

                        file_put_contents($log_file, $log_content, FILE_APPEND);
                        return false;
                    }

                    $order['book_num'] = isset($order['quantity']) ? $order['quantity'] : $order['book_num'];
                    $order['title']    = $trip_activity['title'];
                    $pay_params        = $this->buildPayParams($order);
                    $nvpstr            = $this->buildNvpStr($pay_params);

                    $confirm_result       = $this->ConfirmPayment($nvpstr, $payer_id, $token);
                    $ackDoExpressCheckout = isset($confirm_result["ACK"]) ? strtoupper($confirm_result["ACK"]) : '';

                    if($ackDoExpressCheckout == "SUCCESS" || $ackDoExpressCheckout == "SUCCESSWITHWARNING") {
                        $transactionId   = $confirm_result["PAYMENTINFO_0_TRANSACTIONID"]; // ' Unique transaction ID of the payment. Note:  If the PaymentAction of the request was Authorization or Order, this value is your AuthorizationID for use with the Authorization & Capture APIs.
                        $transactionType = $confirm_result["PAYMENTINFO_0_TRANSACTIONTYPE"]; //' The type of transaction Possible values: l  cart l  express-checkout
                        $paymentType     = $confirm_result["PAYMENTINFO_0_PAYMENTTYPE"];  //' Indicates whether the payment is instant or delayed. Possible values: l  none l  echeck l  instant
                        $pay_time        = isset($confirm_result["PAYMENTINFO_0_ORDERTIME"]) ? $confirm_result["PAYMENTINFO_0_ORDERTIME"] : '';  //' Time/date stamp of payment
                        $amt             = $confirm_result["PAYMENTINFO_0_AMT"];  //' The final amount charged, including any shipping and taxes from your Merchant Profile.
                        $currencyCode    = $confirm_result["PAYMENTINFO_0_CURRENCYCODE"];  //' A three-character currency code for one of the currencies listed in PayPay-Supported Transactional Currencies. Default: USD.
                        /*
                        * Status of the payment:
                        * Completed: The payment has been completed, and the funds have been added successfully to your account balance.
                        * Pending: The payment is pending. See the PendingReason element for more information.
                        */

                        $paymentStatus = $confirm_result["PAYMENTINFO_0_PAYMENTSTATUS"];

                        /*
                        * The reason the payment is pending
                        */
                        $pendingReason = $confirm_result["PAYMENTINFO_0_PENDINGREASON"];

                        /*
                        * The reason for a reversal if TransactionType is reversal
                        */
                        $reasonCode = $confirm_result["PAYMENTINFO_0_REASONCODE"];

                        // 更新订单的状态
                        $update_res = $this->CI->{$model}->update_paid_order($order['order_sn'], $transactionId);

                        if($update_res) {
                            return $payment_details;
                        }
                    }

                }
            }

            $log_content = '/=========================================== ' . date('Y-m-d H:i:s') . ' =============================================/' . "\n";
            $log_content .= 'Unknow error.' . "\n";
            $log_content .= '/=================================================================================================================/' . "\n";

            file_put_contents($log_file, $log_content, FILE_APPEND);
            return false;
        }

        /*
        '-------------------------------------------------------------------------------------------------------------------------------------------
        ' Purpose: 	Prepares the parameters for the SetExpressCheckout API Call.
        ' Inputs:
        '		paymentAmount:  	Total value of the shopping cart
        '		currencyCodeType: 	Currency code value the PayPal API
        '		paymentType: 		paymentType has to be one of the following values: Sale or Order or Authorization
        '		returnURL:			the page where buyers return to after they are done with the payment review on PayPal
        '		cancelURL:			the page where buyers return to when they cancel the payment review on PayPal
        '		shipToName:		    the Ship to name entered on the merchant's site
        '		shipToStreet:		the Ship to Street entered on the merchant's site
        '		shipToCity:			the Ship to City entered on the merchant's site
        '		shipToState:		the Ship to State entered on the merchant's site
        '		shipToCountryCode:	the Code for Ship to Country entered on the merchant's site
        '		shipToZip:			the Ship to ZipCode entered on the merchant's site
        '		shipToStreet2:		the Ship to Street2 entered on the merchant's site
        '		phoneNum:			the phoneNum  entered on the merchant's site
        '--------------------------------------------------------------------------------------------------------------------------------------------
        */

        public function getPaymentDetails($token)
        {
            /*
            * Build a second API request to PayPal, using the token as the
            *  ID to get the details on the payment authorization
            */
            $nvpstr = "&TOKEN=" . $token;

            /*
            * Make the API call and store the results in an array.
            * If the call was a success, show the authorization details, and provide an action to complete the payment.
            * If failed, show the error
            */
            $resArray = $this->hash_call("GetExpressCheckoutDetails", $nvpstr);
            $ack      = isset($resArray["ACK"]) ? strtoupper($resArray["ACK"]) : '';
            if($ack == "SUCCESS" || $ack == "SUCCESSWITHWARNING") {
                $_SESSION['payer_id'] = $resArray['PAYERID'];
            }

            return $resArray;
        }


        /* Purpose:
        * Prepares the parameters for the GetExpressCheckoutDetails API Call.
        * Inputs:  None
        * Returns: The NVP Collection object of the GetExpressCheckoutDetails Call Response.
        */

        public function ConfirmPayment($nvpstr, $payer_id, $token)
        {
            $nvpstr .= '&PAYMENTREQUEST_0_NOTIFYURL=' . $this->notify_url;
            $nvpstr .= '&PAYERID=' . urlencode($payer_id) . '&TOKEN=' . urlencode($token);
            $resArray = $this->hash_call("DoExpressCheckoutPayment", $nvpstr);

            return $resArray;
        }

        /*
        * Purpose: 	Prepares the parameters for the DoExpressCheckoutPayment API Call.
        * Inputs:   FinalPaymentAmount:	The total transaction amount.
        * Returns: 	The NVP Collection object of the DoExpressCheckoutPayment Call Response.
        */
        // public function ConfirmPayment( $FinalPaymentAmt )
        // {
        // 	/* Gather the information to make the final call to finalize the PayPal payment.  The variable nvpstr
        //         * holds the name value pairs
        // 	 */

        // 	//mandatory parameters in DoExpressCheckoutPayment call
        // 	if(isset($_SESSION['TOKEN']))
        // 	$nvpstr = '&TOKEN=' . urlencode($_SESSION['TOKEN']);

        // 	if(isset($_SESSION['payer_id']))
        // 	$nvpstr .= '&PAYERID=' . urlencode($_SESSION['payer_id']);

        // 	if(isset($_SESSION['PaymentType']))
        // 	$nvpstr .= '&PAYMENTREQUEST_0_PAYMENTACTION=' . urlencode($_SESSION['PaymentType']);

        // 	if(isset($_SERVER['SERVER_NAME']))
        // 	$nvpstr .= '&IPADDRESS=' . urlencode($_SERVER['SERVER_NAME']);

        // 	$nvpstr .= '&PAYMENTREQUEST_0_AMT=' . $FinalPaymentAmt;


        // 	//Check for additional parameters that can be passed in DoExpressCheckoutPayment API call
        // 	if(isset($_SESSION['currencyCodeType']))
        // 	$nvpstr .= '&PAYMENTREQUEST_0_CURRENCYCODE=' . urlencode($_SESSION['currencyCodeType']);

        // 	if(isset($_SESSION['itemAmt']))
        // 	$nvpstr = $nvpstr . '&PAYMENTREQUEST_0_ITEMAMT=' . urlencode($_SESSION['itemAmt']);

        // 	if(isset($_SESSION['taxAmt']))
        // 	$nvpstr = $nvpstr . '&PAYMENTREQUEST_0_TAXAMT=' . urlencode($_SESSION['taxAmt']);

        // 	if(isset($_SESSION['shippingAmt']))
        // 	$nvpstr = $nvpstr . '&PAYMENTREQUEST_0_SHIPPINGAMT=' . urlencode($_SESSION['shippingAmt']);

        // 	if(isset($_SESSION['handlingAmt']))
        // 	$nvpstr = $nvpstr . '&PAYMENTREQUEST_0_HANDLINGAMT=' . urlencode($_SESSION['handlingAmt']);

        // 	if(isset($_SESSION['shippingDiscAmt']))
        // 	$nvpstr = $nvpstr . '&PAYMENTREQUEST_0_SHIPDISCAMT=' . urlencode($_SESSION['shippingDiscAmt']);

        // 	if(isset($_SESSION['insuranceAmt']))
        // 	$nvpstr = $nvpstr . '&PAYMENTREQUEST_0_INSURANCEAMT=' . urlencode($_SESSION['insuranceAmt']);


        // 	 /* Make the call to PayPal to finalize payment
        //          * If an error occured, show the resulting errors
        // 	  */


        // 	$resArray = $this->hash_call("DoExpressCheckoutPayment", $nvpstr);

        // 	/* Display the API response back to the browser.
        // 	 * If the response from PayPal was a success, display the response parameters'
        // 	 * If the response was an error, display the errors received using APIError.php.
        // 	 */
        // 	$ack = strtoupper($resArray["ACK"]);

        // 	return $resArray;
        // }

        /*
          * hash_call: public Function to perform the API call to PayPal using API signature
          * @methodName is name of API  method.
          * @nvpStr is nvp string.
          * returns an associtive array containing the response from the server.
        */

        public function notify()
        {
            $log_file = COMPATH . 'log/paypal/notify_log_' . date('Ymd') . '.txt';

            $order_sn       = isset($_POST['invoice']) ? $_POST['invoice'] : '';
            $order_len      = strlen($order_sn);
            $sign           = isset($_POST['custom']) ? $_POST['custom'] : '';
            $receiver_email = isset($_POST['receiver_email']) ? $_POST['receiver_email'] : '';
            $total_amount   = isset($_POST['mc_gross']) ? $_POST['mc_gross'] : '';
            $currency_code  = isset($_POST['mc_currency']) ? $_POST['mc_currency'] : '';

            // if ($receiver_email != $this->seller_username)
            // {
            // 	file_put_contents($log_file, date('Y-m-d H:i:s',time())."\n".serialize($_POST).' fail1',FILE_APPEND);
            // 	exit('fail');
            // }

            // 向paypal发送校验请求的信息
            $_POST['cmd'] = '_notify-validate';
            $ch           = curl_init();
            curl_setopt($ch, CURLOPT_URL, $this->paypal_verify_url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $_POST);
            $notify_result = curl_exec($ch);
            curl_close($ch);

            // 如果这次的请求是Paypal的服务器发送到我方服务器的则继续验证，否则退出
            $this->CI->load->library('ChatLibrary');
            if(strcmp($notify_result, 'VERIFIED') == 0) {
                if($_POST['payment_status'] != 'Completed' && $_POST['payment_status'] != 'Pending') {
                    file_put_contents($log_file, date('Y-m-d H:i:s', time()) . "\n" . serialize($_POST) . ' fail2', FILE_APPEND);
                    return false;
                }

                // 获取交易id
                $txn_id = isset($_POST['txn_id']) ? trim($_POST['txn_id']) : '';
                if(!$txn_id) {
                    file_put_contents($log_file, date('Y-m-d H:i:s', time()) . "\n" . serialize($_POST) . ' fail3', FILE_APPEND);
                    return false;
                }

                if(!$order_sn) {
                    file_put_contents($log_file, date('Y-m-d H:i:s', time()) . "\n" . serialize($_POST) . ' fail4', FILE_APPEND);
                    return false;
                }

                // 校验交易id是否重复
                $this->CI->load->model('order_model');
                $txn_res = $this->CI->order_model->get_single(array('trade_sn' => $txn_id));;
                if($txn_res) {
                    file_put_contents($log_file, date('Y-m-d H:i:s', time()) . "\n" . serialize($_POST) . ' fail5', FILE_APPEND);
                    return false;
                }

                // 获取订单信息
                $model = $order_len == 16 ? 'order_model' : 'activity_order_model';
                $order = $this->CI->{$model}->get_single(array('order_sn' => $order_sn));
                if(!$order) {
                    file_put_contents($log_file, date('Y-m-d H:i:s', time()) . "\n" . serialize($_POST) . ' fail6', FILE_APPEND);
                    return false;
                }

                if($total_amount != $order['amount']) {
                    file_put_contents($log_file, date('Y-m-d H:i:s', time()) . "\n" . serialize($_POST) . ' fail7', FILE_APPEND);
                    return false;
                }

                if(strtoupper($currency_code) != strtoupper($order['currency_code'])) {
                    file_put_contents($log_file, date('Y-m-d H:i:s', time()) . "\n" . serialize($_POST) . ' fail8', FILE_APPEND);
                    return false;
                }

                // if ($sign != $this->buildSign($order))
                // {
                // 	file_put_contents($log_file, date('Y-m-d H:i:s',time())."\n".serialize($_POST).' fail9',FILE_APPEND);
                // 	return false;
                // }

                if($order['status'] != 0) {
                    file_put_contents($log_file, date('Y-m-d H:i:s', time()) . "\n" . serialize($_POST) . ' fail10', FILE_APPEND);
                    return false;
                }

                $this->CI->load->model('user_model');
                $user         = $this->CI->user_model->get_single(array('user_id' => $order['user_id']), 'chat_account');
                $user_account = isset($user['chat_account']) ? $user['chat_account'] : '';

                // 更新订单的状态
                $update_res = $this->CI->{$model}->update_paid_order($order['order_sn'], $txn_id);
                if($update_res) {
                    $msg_data['status']   = 'y';
                    $msg_data['action']   = 'complete';
                    $msg_data['order_sn'] = $order_sn;
                    $this->CI->chatlibrary->send_to_user($this->customer_account, $user_account, 1, 'Payment successful', 'pay_notify', $msg_data);
                    file_put_contents($log_file, date('Y-m-d H:i:s', time()) . "\n" . serialize($_POST) . ' success', FILE_APPEND);

                    return true;
                }

                $msg_data['status']   = 'n';
                $msg_data['action']   = 'complete';
                $msg_data['order_sn'] = $order_sn;
                $this->CI->chatlibrary->send_to_user($this->customer_account, $user_account, 1, 'Payment failed', 'pay_notify', $msg_data);
            }

            // if ($order_sn)
            // {
            // 	$order = $this->CI->order_model->get_single(array('order_sn'=>$order_sn));
            // 	if ($order)
            // 	{
            // 		// 取消订单
            // 		if ($order['status'] == 0)
            // 		{
            // 			$order_data['order_id'] = $order['order_id'];
            // 			$order_data['status'] = -1;

            // 			$this->CI->order_model->save($order_data);
            // 		}
            // 	}
            // }

            return false;
        }

        /*
        * Purpose: Redirects to PayPal.com site.
        * Inputs:  NVP string.
        *  Returns:
        */

        public function CallMarkExpressCheckout($paramsArray, $shippingDetail = array())
        {
            //------------------------------------------------------------------------------------------------------------------------------------
            // Construct the parameter string that describes the SetExpressCheckout API call in the shortcut implementation

            //Mandatory parameters for SetExpressCheckout API call
            if(isset($paramsArray["PAYMENTREQUEST_0_AMT"])) {
                $nvpstr                     = "&PAYMENTREQUEST_0_AMT=" . $paramsArray["PAYMENTREQUEST_0_AMT"];
                $_SESSION["Payment_Amount"] = $paramsArray["PAYMENTREQUEST_0_AMT"];
            }

            if(isset($paramsArray["paymentType"])) {
                $nvpstr                  = $nvpstr . "&PAYMENTREQUEST_0_PAYMENTACTION=" . $paramsArray["paymentType"];
                $_SESSION["PaymentType"] = $paramsArray["paymentType"];
            }

            if(isset($paramsArray["RETURN_URL"]))
                $nvpstr = $nvpstr . "&RETURNURL=" . $paramsArray["RETURN_URL"];

            if(isset($paramsArray["CANCEL_URL"]))
                $nvpstr = $nvpstr . "&CANCELURL=" . $paramsArray["CANCEL_URL"];

            //Optional parameters for SetExpressCheckout API call
            if(isset($paramsArray["currencyCodeType"])) {
                $nvpstr                       = $nvpstr . "&PAYMENTREQUEST_0_CURRENCYCODE=" . $paramsArray["currencyCodeType"];
                $_SESSION["currencyCodeType"] = $paramsArray["currencyCodeType"];
            }

            /************************ 自定义参数1 *************************/
            if(isset($paramsArray["PAYMENTREQUEST_0_CUSTOM"])) {
                $nvpstr = $nvpstr . "&PAYMENTREQUEST_0_CUSTOM=" . $paramsArray["PAYMENTREQUEST_0_CUSTOM"];
            }

            /************************ 自定义参数2 *************************/
            if(isset($paramsArray["PAYMENTREQUEST_0_INVNUM"])) {
                $nvpstr = $nvpstr . "&PAYMENTREQUEST_0_INVNUM=" . $paramsArray["PAYMENTREQUEST_0_INVNUM"];
            }

            /************************ 是否显示收货地址，0：是，1：否 *************************/
            if(isset($paramsArray["NOSHIPPING"])) {
                $nvpstr = $nvpstr . "&NOSHIPPING=" . $paramsArray["NOSHIPPING"];
            }

            if(isset($paramsArray["PAYMENTREQUEST_0_ITEMAMT"])) {
                $nvpstr              = $nvpstr . "&PAYMENTREQUEST_0_ITEMAMT=" . $paramsArray["PAYMENTREQUEST_0_ITEMAMT"];
                $_SESSION['itemAmt'] = $paramsArray["PAYMENTREQUEST_0_ITEMAMT"];
            }

            if(isset($paramsArray["PAYMENTREQUEST_0_TAXAMT"])) {
                $nvpstr             = $nvpstr . "&PAYMENTREQUEST_0_TAXAMT=" . $paramsArray["PAYMENTREQUEST_0_TAXAMT"];
                $_SESSION['taxAmt'] = $paramsArray["PAYMENTREQUEST_0_TAXAMT"];
            }

            if(isset($paramsArray["PAYMENTREQUEST_0_SHIPPINGAMT"])) {
                $nvpstr                  = $nvpstr . "&PAYMENTREQUEST_0_SHIPPINGAMT=" . $paramsArray["PAYMENTREQUEST_0_SHIPPINGAMT"];
                $_SESSION['shippingAmt'] = $paramsArray["PAYMENTREQUEST_0_SHIPPINGAMT"];
            }

            if(isset($paramsArray["PAYMENTREQUEST_0_HANDLINGAMT"])) {
                $nvpstr                  = $nvpstr . "&PAYMENTREQUEST_0_HANDLINGAMT=" . $paramsArray["PAYMENTREQUEST_0_HANDLINGAMT"];
                $_SESSION['handlingAmt'] = $paramsArray["PAYMENTREQUEST_0_HANDLINGAMT"];
            }

            if(isset($paramsArray["PAYMENTREQUEST_0_SHIPDISCAMT"])) {
                $nvpstr                      = $nvpstr . "&PAYMENTREQUEST_0_SHIPDISCAMT=" . $paramsArray["PAYMENTREQUEST_0_SHIPDISCAMT"];
                $_SESSION['shippingDiscAmt'] = $paramsArray["PAYMENTREQUEST_0_SHIPDISCAMT"];
            }

            if(isset($paramsArray["PAYMENTREQUEST_0_INSURANCEAMT"])) {
                $nvpstr                   = $nvpstr . "&PAYMENTREQUEST_0_INSURANCEAMT=" . $paramsArray["PAYMENTREQUEST_0_INSURANCEAMT"];
                $_SESSION['insuranceAmt'] = $paramsArray["PAYMENTREQUEST_0_INSURANCEAMT"];
            }

            if(isset($paramsArray["L_PAYMENTREQUEST_0_NAME0"]))
                $nvpstr = $nvpstr . "&L_PAYMENTREQUEST_0_NAME0=" . $paramsArray["L_PAYMENTREQUEST_0_NAME0"];

            if(isset($paramsArray["L_PAYMENTREQUEST_0_NUMBER0"]))
                $nvpstr = $nvpstr . "&L_PAYMENTREQUEST_0_NUMBER0=" . $paramsArray["L_PAYMENTREQUEST_0_NUMBER0"];

            if(isset($paramsArray["L_PAYMENTREQUEST_0_DESC0"]))
                $nvpstr = $nvpstr . "&L_PAYMENTREQUEST_0_DESC0=" . $paramsArray["L_PAYMENTREQUEST_0_DESC0"];

            if(isset($paramsArray["L_PAYMENTREQUEST_0_AMT0"]))
                $nvpstr = $nvpstr . "&L_PAYMENTREQUEST_0_AMT0=" . $paramsArray["L_PAYMENTREQUEST_0_AMT0"];

            if(isset($paramsArray["L_PAYMENTREQUEST_0_QTY0"]))
                $nvpstr = $nvpstr . "&L_PAYMENTREQUEST_0_QTY0=" . $paramsArray["L_PAYMENTREQUEST_0_QTY0"];

            if(isset($paramsArray["LOGOIMG"]))
                $nvpstr = $nvpstr . "&LOGOIMG=" . $paramsArray["LOGOIMG"];

            if($this->address_override)
                $nvpstr = $nvpstr . "&ADDROVERRIDE=1";

            // Shipping parameters for API call

            if(isset($shippingDetail["L_PAYMENTREQUEST_FIRSTNAME"])) {
                $fullname = $shippingDetail["L_PAYMENTREQUEST_FIRSTNAME"];
                if(isset($shippingDetail["L_PAYMENTREQUEST_LASTNAME"]))
                    $fullname = $fullname . " " . $shippingDetail["L_PAYMENTREQUEST_LASTNAME"];

                $nvpstr                 = $nvpstr . "&PAYMENTREQUEST_0_SHIPTONAME=" . $fullname;
                $_SESSION["shipToName"] = $fullname;
            }

            if(isset($shippingDetail["PAYMENTREQUEST_0_SHIPTOSTREET"])) {
                $nvpstr                    = $nvpstr . "&PAYMENTREQUEST_0_SHIPTOSTREET=" . $shippingDetail["PAYMENTREQUEST_0_SHIPTOSTREET"];
                $_SESSION['shipToAddress'] = $shippingDetail["PAYMENTREQUEST_0_SHIPTOSTREET"];
            }

            if(isset($shippingDetail["PAYMENTREQUEST_0_SHIPTOSTREET2"])) {
                $nvpstr                     = $nvpstr . "&PAYMENTREQUEST_0_SHIPTOSTREET2=" . $shippingDetail["PAYMENTREQUEST_0_SHIPTOSTREET2"];
                $_SESSION['shipToAddress2'] = $shippingDetail["PAYMENTREQUEST_0_SHIPTOSTREET2"];
            }

            if(isset($shippingDetail["PAYMENTREQUEST_0_SHIPTOCITY"])) {
                $nvpstr                 = $nvpstr . "&PAYMENTREQUEST_0_SHIPTOCITY=" . $shippingDetail["PAYMENTREQUEST_0_SHIPTOCITY"];
                $_SESSION['shipToCity'] = $shippingDetail["PAYMENTREQUEST_0_SHIPTOCITY"];
            }

            if(isset($shippingDetail["PAYMENTREQUEST_0_SHIPTOSTATE"])) {
                $nvpstr                  = $nvpstr . "&PAYMENTREQUEST_0_SHIPTOSTATE=" . $shippingDetail["PAYMENTREQUEST_0_SHIPTOSTATE"];
                $_SESSION['shipToState'] = $shippingDetail["PAYMENTREQUEST_0_SHIPTOSTATE"];
            }
            if(isset($shippingDetail["PAYMENTREQUEST_0_SHIPTOZIP"])) {
                $nvpstr                = $nvpstr . "&PAYMENTREQUEST_0_SHIPTOZIP=" . $shippingDetail["PAYMENTREQUEST_0_SHIPTOZIP"];
                $_SESSION['shipToZip'] = $shippingDetail["PAYMENTREQUEST_0_SHIPTOZIP"];
            }
            if(isset($shippingDetail["PAYMENTREQUEST_0_SHIPTOCOUNTRY"])) {
                $nvpstr                    = $nvpstr . "&PAYMENTREQUEST_0_SHIPTOCOUNTRY=" . $shippingDetail["PAYMENTREQUEST_0_SHIPTOCOUNTRY"];
                $_SESSION['shipToCountry'] = $shippingDetail["PAYMENTREQUEST_0_SHIPTOCOUNTRY"];
            }
            if(isset($shippingDetail["PAYMENTREQUEST_0_SHIPTOPHONENUM"])) {
                $nvpstr                  = $nvpstr . "&PAYMENTREQUEST_0_SHIPTOPHONENUM=" . $shippingDetail["PAYMENTREQUEST_0_SHIPTOPHONENUM"];
                $_SESSION['shipToPhone'] = $shippingDetail["PAYMENTREQUEST_0_SHIPTOPHONENUM"];
            }
            /*
            * Make the API call to PayPal
            * If the API call succeded, then redirect the buyer to PayPal to begin to authorize payment.
            * If an error occured, show the resulting errors
            */
            $resArray = $this->hash_call("SetExpressCheckout", $nvpstr);
            $ack      = isset($resArray["ACK"]) ? strtoupper($resArray["ACK"]) : '';
            if($ack == "SUCCESS" || $ack == "SUCCESSWITHWARNING") {
                $token             = urldecode($resArray["TOKEN"]);
                $_SESSION['TOKEN'] = $token;
            }
            return $resArray;
        }


        /*
          * This public function will take NVPString and convert it to an Associative Array and it will decode the response.
          * It is usefull to search for a particular key and displaying arrays.
          * @nvpstr is NVPString.
          * @nvpArray is Associative Array.
          */

        public function GetShippingDetails($token)
        {
            /*
            * Build a second API request to PayPal, using the token as the
            *  ID to get the details on the payment authorization
            */
            $nvpstr = "&TOKEN=" . $token;

            /*
            * Make the API call and store the results in an array.
            * If the call was a success, show the authorization details, and provide an action to complete the payment.
            * If failed, show the error
            */
            $resArray = $this->hash_call("GetExpressCheckoutDetails", $nvpstr);
            $ack      = strtoupper($resArray["ACK"]);
            if($ack == "SUCCESS" || $ack == "SUCCESSWITHWARNING") {
                $_SESSION['payer_id'] = $resArray['PAYERID'];
            }

            return $resArray;
        }
    }