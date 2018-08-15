<?php

    /**
     * 聊天扩展类（注意：此类中不能引用CI对象！）
     */
    class ChatLibrary
    {
        public $role_arr = array('1' => 'user', '2' => 'guide', '0' => 'system', '-1' => 'virtual_user');

        /**
         * 解密获取聊天数据
         *
         * @param  [type] $encrypt_chat_data [description]
         * @return [type]                    [description]
         */
        public function decrypt_chat_data($encrypt_chat_data, $type = 'sender')
        {
            if($type == 'sender') {
                // $ip = $_SERVER['REMOTE_ADDR'];
                $ip        = isset($encrypt_chat_data['ip']) ? strip_tags($encrypt_chat_data['ip']) : '';
                $uid       = isset($encrypt_chat_data['uid']) ? strip_tags($encrypt_chat_data['uid']) : '';
                $role      = isset($encrypt_chat_data['role']) ? strip_tags($encrypt_chat_data['role']) : '';
                $sign      = isset($encrypt_chat_data['sign']) ? strip_tags($encrypt_chat_data['sign']) : '';
                $timestamp = isset($encrypt_chat_data['timestamp']) ? intval($encrypt_chat_data['timestamp']) : '';
            } else {
                $ip        = isset($encrypt_chat_data['ip']) ? strip_tags($encrypt_chat_data['ip']) : '';
                $uid       = isset($encrypt_chat_data['tid']) ? strip_tags($encrypt_chat_data['tid']) : '';
                $role      = isset($encrypt_chat_data['t_role']) ? strip_tags($encrypt_chat_data['t_role']) : '';
                $sign      = isset($encrypt_chat_data['t_sign']) ? strip_tags($encrypt_chat_data['t_sign']) : '';
                $timestamp = isset($encrypt_chat_data['t_timestamp']) ? intval($encrypt_chat_data['t_timestamp']) : '';
            }

            // 校验数据
            if(!$ip || strlen($uid) != 44 || strlen($role) != 44 || !$sign || !$timestamp) {
                $return_data['error'] = 1;
                $return_data['code']  = 'DCD001';
                $return_data['info']  = 'Invalid data.';
                return $return_data;
            }

            // 生成秘钥
            $encryption_key = $this->generate_encryption_key($sign, $ip, $timestamp);

            // 对聊天账号进行解密
            $uid          = str_replace('_', '+', $uid);
            $chat_account = $this->decrypt($uid, $encryption_key);

            // 对用户角色进行解密
            $role      = str_replace('_', '+', $role);
            $role      = $this->decrypt($role, $encryption_key);
            $real_role = $this->get_real_role($role);

            // 校验签名
            $encrypt_chat_data['sign']         = $sign;
            $encrypt_chat_data['role']         = $real_role;
            $encrypt_chat_data['timestamp']    = $timestamp;
            $encrypt_chat_data['chat_account'] = $chat_account;
            $sign_available                    = $this->verify_chat_sign($encrypt_chat_data);

            if((strlen($chat_account) != 32 && strlen($chat_account) != 18) || !in_array($real_role, $this->role_arr) || !$sign_available) {
                $return_data['error'] = 1;
                $return_data['code']  = 'DCD002';
                $return_data['info']  = 'Invalid data.';
                return $return_data;
            }

            $chat_data['role']         = $real_role;
            $chat_data['chat_account'] = $chat_account;

            $return_data['error']     = 0;
            $return_data['code']      = 'DCD000';
            $return_data['info']      = 'Decrypt successful.';
            $return_data['chat_data'] = $chat_data;

            return $return_data;
        }

        /**
         * 生成加密秘钥
         *
         * @param  [type] $sign      [description]
         * @param  [type] $ip        [description]
         * @param  [type] $timestamp [description]
         * @return [type]            [description]
         */
        public function generate_encryption_key($sign, $ip, $timestamp)
        {
            if(!$sign || !$ip || $ip == 'unknown' || !$timestamp) {
                return false;
            }

            $len       = strlen($timestamp);
            $start_arr = array();

            // 截取时间戳的每一个数字作为截取sign的开始位置
            for($i = 0; $i < $len; $i++) {
                array_push($start_arr, substr($timestamp, $i, 1));
            }

            // 去掉sign前面7个固定不变的字符串
            $new_sign      = substr($sign, 7, (strlen($sign) - 8));
            $str           = 'abcdefghijklmnopqrstuvwxyz';
            $new_start_arr = array();

            // 循环开始位置数组，并根据开始位置获取new_sign中对应位置的字符（字母），并取得该字母在26个字母中的位置索引
            foreach($start_arr as $key => $start) {
                $index = intval(stripos($str, substr($new_sign, $start, 1)));
                $index = $index < 0 ? 0 : $index;
                array_push($new_start_arr, $index);
            }

            $encryption_key = '';
            $start_arr      = $new_start_arr;
            foreach($start_arr as $key => $start) {
                $sub_len = $key < count($start_arr) - 1 ? $start_arr[$key + 1] : $start_arr[0];
                $encryption_key .= substr($sign, $start, $sub_len);
            }

            $key1           = md5($ip . $encryption_key);
            $key2           = md5($key1 . $encryption_key);
            $encryption_key = md5($key1 . $key2);

            return $encryption_key;
        }

        public function decrypt($str_encrypt, $key)
        {
            $cipher = MCRYPT_RIJNDAEL_256;
            $modes  = MCRYPT_MODE_ECB;
            $iv     = mcrypt_create_iv(mcrypt_get_iv_size($cipher, $modes), MCRYPT_RAND);

            return mcrypt_decrypt($cipher, $key, base64_decode($str_encrypt), $modes, $iv);
        }

        public function get_real_role($md5_role)
        {
            $real_role = '';
            foreach($this->role_arr as $val) {
                if($md5_role == md5($val)) {
                    $real_role = $val;
                    break;
                }
            }

            return $real_role;
        }

        /**
         * 校验聊天签名
         *
         * @param  [type] $encrypt_chat_data [description]
         * @return [type]                    [description]
         */
        public function verify_chat_sign($encrypt_chat_data)
        {
            $sign = isset($encrypt_chat_data['sign']) ? $encrypt_chat_data['sign'] : '';

            if(!$sign) {
                return false;
            }

            $local_sign = $this->built_chat_sign($encrypt_chat_data, false);

            return password_verify($local_sign, $sign);
        }

        /**
         * 生成聊天签名
         *
         * @param  [type]  $data          [description]
         * @param  boolean $password_hash [description]
         * @return [type]                 [description]
         */
        public function built_chat_sign($data, $password_hash = true)
        {
            // $ip = $_SERVER['REMOTE_ADDR'];
            $ip           = isset($data['ip']) ? $data['ip'] : '';
            $role         = isset($data['role']) ? $data['role'] : '';
            $timestamp    = isset($data['timestamp']) ? intval($data['timestamp']) : '';
            $chat_account = isset($data['chat_account']) ? $data['chat_account'] : '';

            if(!$chat_account || !in_array($role, $this->role_arr) || !$ip || !$timestamp) {
                return false;
            }

            $str1 = md5($chat_account);
            $str2 = md5($str1 . $chat_account . $role);
            $str3 = md5($str2 . $chat_account . $role . $timestamp);
            $str4 = md5($str3 . $timestamp . $role . $chat_account);
            $str5 = md5($str1 . $str2 . $str3 . $str4);

            if($ip == '' || $ip == 'unknown') {
                return false;
            }

            $sign = md5($ip . $str5);
            if($password_hash) {
                $sign = password_hash($sign, PASSWORD_DEFAULT);
            }

            return $sign;
        }

        /**
         * 加密保存聊天消息的数据
         *
         * @param  [type] $message_data [description]
         * @return [type]               [description]
         */
        public function encrypt_save_message_data($message_data)
        {
            $ip                = isset($message_data['ip']) ? $message_data['ip'] : '';
            $action            = isset($message_data['action']) ? $message_data['action'] : 'save';
            $content           = isset($message_data['content']) ? $message_data['content'] : '';
            $timestamp         = isset($message_data['timestamp']) ? intval($message_data['timestamp']) : '';
            $to_user_role      = isset($message_data['to_user_role']) ? $message_data['to_user_role'] : '';
            $from_user_role    = isset($message_data['from_user_role']) ? $message_data['from_user_role'] : '';
            $to_user_account   = isset($message_data['to_user_account']) ? $message_data['to_user_account'] : '';
            $from_user_account = isset($message_data['from_user_account']) ? $message_data['from_user_account'] : '';

            if(!$from_user_account || !$to_user_account || !in_array($from_user_role, $this->role_arr) || !in_array($from_user_role, $this->role_arr)) {
                return false;
            }

            if($ip == '' || $ip == 'unknown' || !$timestamp) {
                return false;
            }

            // 生成签名
            $sign = $this->built_save_message_sign($message_data);

            // 生成加密秘钥
            $encryption_key = $this->generate_encryption_key($sign, $ip, $timestamp);

            // 加密发送者的聊天账号
            $from_user_account = $this->encrypt($from_user_account, $encryption_key);
            $from_user_account = $from_user_account ? str_replace('+', '_', $from_user_account) : '';

            // 加密接收者的聊天账号
            $to_user_account = $this->encrypt($to_user_account, $encryption_key);
            $to_user_account = $to_user_account ? str_replace('+', '_', $to_user_account) : '';

            // 加密发送者的角色类型
            $from_user_role = $this->encrypt(md5($from_user_role), $encryption_key);
            $from_user_role = $from_user_role ? str_replace('+', '_', $from_user_role) : '';

            // 加密接收者的角色类型
            $to_user_role = $this->encrypt(md5($to_user_role), $encryption_key);
            $to_user_role = $to_user_role ? str_replace('+', '_', $to_user_role) : '';

            $data['sign']              = $sign;
            $data['action']            = $action;
            $data['content']           = $content;
            $data['timestamp']         = $timestamp;
            $data['to_user_role']      = $to_user_role;
            $data['from_user_role']    = $from_user_role;
            $data['to_user_account']   = $to_user_account;
            $data['from_user_account'] = $from_user_account;

            return $data;
        }

        public function built_save_message_sign($message_data, $password_hash = true)
        {
            $ip                = isset($message_data['ip']) ? $message_data['ip'] : '';
            $timestamp         = isset($message_data['timestamp']) ? intval($message_data['timestamp']) : '';
            $to_user_role      = isset($message_data['to_user_role']) ? $message_data['to_user_role'] : '';
            $from_user_role    = isset($message_data['from_user_role']) ? $message_data['from_user_role'] : '';
            $to_user_account   = isset($message_data['to_user_account']) ? $message_data['to_user_account'] : '';
            $from_user_account = isset($message_data['from_user_account']) ? $message_data['from_user_account'] : '';

            if(!$from_user_account || !$to_user_account || !in_array($from_user_role, $this->role_arr) || !in_array($from_user_role, $this->role_arr)) {
                return false;
            }

            $str1 = md5($from_user_account);
            $str2 = md5($to_user_account);
            $str3 = md5($str1 . $from_user_role);
            $str4 = md5($str2 . $to_user_role);
            $str5 = md5($timestamp . $str1 . $str2 . $timestamp . $str3 . $str4 . $timestamp);

            if($ip == '' || $ip == 'unknown') {
                return false;
            }

            $sign = md5($ip . $str5);
            if($password_hash) {
                $sign = password_hash($sign, PASSWORD_DEFAULT);
            }

            return $sign;
        }

        public function encrypt($str, $key)
        {
            $cipher = MCRYPT_RIJNDAEL_256;
            $modes  = MCRYPT_MODE_ECB;
            $iv     = mcrypt_create_iv(mcrypt_get_iv_size($cipher, $modes), MCRYPT_RAND);
            return base64_encode(mcrypt_encrypt($cipher, $key, $str, $modes, $iv));
        }

        /**
         * 解密保存聊天消息的数据
         *
         * @param  [type] $encrypt_message_data [description]
         * @return [type]                       [description]
         */
        public function decrypt_save_message_data($encrypt_message_data)
        {
            $ip                = isset($encrypt_message_data['ip']) ? $encrypt_message_data['ip'] : '';
            $sign              = isset($encrypt_message_data['sign']) ? $encrypt_message_data['sign'] : '';
            $action            = !empty($encrypt_message_data['action']) ? $encrypt_message_data['action'] : 'save';
            $content           = isset($encrypt_message_data['content']) ? $encrypt_message_data['content'] : '';
            $timestamp         = isset($encrypt_message_data['timestamp']) ? intval($encrypt_message_data['timestamp']) : '';
            $to_user_role      = isset($encrypt_message_data['to_user_role']) ? $encrypt_message_data['to_user_role'] : '';
            $from_user_role    = isset($encrypt_message_data['from_user_role']) ? $encrypt_message_data['from_user_role'] : '';
            $to_user_account   = isset($encrypt_message_data['to_user_account']) ? $encrypt_message_data['to_user_account'] : '';
            $from_user_account = isset($encrypt_message_data['from_user_account']) ? $encrypt_message_data['from_user_account'] : '';

            // 校验数据
            if(!$sign || !$from_user_account || !$to_user_account || !$to_user_role || !$from_user_role) {
                $return_data['error'] = 1;
                $return_data['code']  = 'DSMD001';
                $return_data['info']  = 'Invalid data.';
                return $return_data;
            }

            if($ip == '' || $ip == 'unknown' || !$timestamp) {
                $return_data['error'] = 1;
                $return_data['code']  = 'DSMD002';
                $return_data['info']  = 'Invalid data.';
                return $return_data;
            }

            if(time() - $timestamp > 60 * 2) {
                $return_data['error'] = 1;
                $return_data['code']  = 'DSMD003';
                $return_data['info']  = 'Invalid data.';
                return $return_data;
            }

            // 生成秘钥
            $encryption_key = $this->generate_encryption_key($sign, $ip, $timestamp);

            // 对聊天账号进行解密
            $from_user_account = str_replace('_', '+', $from_user_account);
            $from_user_account = $this->decrypt($from_user_account, $encryption_key);
            $to_user_account   = str_replace('_', '+', $to_user_account);
            $to_user_account   = $this->decrypt($to_user_account, $encryption_key);

            // 对用户角色进行解密
            $from_user_role = str_replace('_', '+', $from_user_role);
            $from_user_role = $this->decrypt($from_user_role, $encryption_key);
            $to_user_role   = str_replace('_', '+', $to_user_role);
            $to_user_role   = $this->decrypt($to_user_role, $encryption_key);

            $from_real_role = $this->get_real_role($from_user_role);
            $to_real_role   = $this->get_real_role($to_user_role);

            // 校验签名
            $encrypt_message_data['from_user_account'] = $from_user_account;
            $encrypt_message_data['to_user_account']   = $to_user_account;
            $encrypt_message_data['from_user_role']    = $from_real_role;
            $encrypt_message_data['to_user_role']      = $to_real_role;
            $sign_available                            = $this->verify_save_message_sign($encrypt_message_data);

            $from_account_available = (strlen($from_user_account) != 32 || strlen($from_user_account) != 18) ? true : false;
            $to_account_available   = (strlen($to_user_account) != 32 || strlen($to_user_account) != 18) ? true : false;
            $from_role_available    = in_array($from_real_role, $this->role_arr) ? true : false;
            $to_role_available      = in_array($to_real_role, $this->role_arr) ? true : false;

            if(!$from_account_available || !$to_account_available || !$from_role_available || !$to_role_available || !$sign_available) {
                $return_data['error'] = 1;
                $return_data['code']  = 'DSMD004';
                $return_data['info']  = 'Invalid data.';
                return $return_data;
            }

            $message_data['action']            = $action;
            $message_data['content']           = $content;
            $message_data['timestamp']         = $timestamp;
            $message_data['to_user_role']      = $to_real_role;
            $message_data['from_user_role']    = $from_real_role;
            $message_data['to_user_account']   = $to_user_account;
            $message_data['from_user_account'] = $from_user_account;

            $return_data['error']        = 0;
            $return_data['code']         = 'DSMD000';
            $return_data['info']         = 'Decrypt successful.';
            $return_data['message_data'] = $message_data;

            return $return_data;
        }

        public function verify_save_message_sign($encrypt_message_data)
        {
            $sign = isset($encrypt_message_data['sign']) ? $encrypt_message_data['sign'] : '';

            if(!$sign) {
                return false;
            }

            $local_sign = $this->built_save_message_sign($encrypt_message_data, false);

            return password_verify($local_sign, $sign);
        }

        /**
         * 发送消息给指定的用户
         *
         * @param string $customer_account 客服聊天账号
         * @param string $to_user_account  接收消息方的聊天账号
         * @param int $to_user_role        接收消息方的用户角色（未登录情况下请传-1）
         * @param string $content          消息内容
         * @param string $chat_type        消息类型，say（聊天）或者pay_notify（支付通知）
         * @return [type] [description]
         */
        public function send_to_user($customer_account, $to_user_account, $to_user_role, $content, $chat_type, $extend_data = array())
        {
            $target_data = $this->generate_login_chat_data($to_user_account, $to_user_role);

            $send_data                = $this->generate_login_chat_data($customer_account, 0);
            $send_data['tid']         = isset($target_data['uid']) ? $target_data['uid'] : '';
            $send_data['type']        = $chat_type;
            $send_data['t_role']      = isset($target_data['role']) ? $target_data['role'] : '';
            $send_data['t_sign']      = isset($target_data['sign']) ? $target_data['sign'] : '';
            $send_data['content']     = $content;
            $send_data['t_timestamp'] = isset($target_data['timestamp']) ? $target_data['timestamp'] : '';

            $send_data = array_merge($send_data, $extend_data);

            // 及时聊天消息
            require_once SRCPATH . "common/libraries/Gateway.php";
            $gateway = new GatewayClient\Gateway;
            $gateway::sendToUid($to_user_account, json_encode($send_data));

            return true;
        }

        /**
         * 生成聊天登录的数据
         * 未登录的情况下需传虚拟用户ID
         *
         * @param string $chat_account 聊天账号
         * @param int $role            用户角色（未登录情况下请传-1）
         * @return [type] [description]
         */
        public function generate_login_chat_data($chat_account, $role)
        {
            $timestamp = time();
            // $generate_data['ip'] = '127.0.0.1';
            $generate_data['ip']           = @gethostbyname($_SERVER['SERVER_NAME']);
            $generate_data['role']         = $this->get_role($role, 'name');
            $generate_data['timestamp']    = $timestamp;
            $generate_data['chat_account'] = $chat_account;
            $chat_data                     = $this->generate_chat_data($generate_data);

            return $chat_data;
        }

        public function get_role($role, $type = 'name')
        {
            if($type == 'name') {
                return isset($this->role_arr[$role]) ? $this->role_arr[$role] : false;
            } else {
                $role_arr = array_flip($this->role_arr);
                return isset($role_arr[$role]) ? $role_arr[$role] : false;
            }
        }

        /**
         * 生成加密后的聊天数据
         *
         * @param  [type] $chat_data [description]
         * @return [type]            [description]
         */
        public function generate_chat_data($chat_data)
        {
            // $ip = $_SERVER['REMOTE_ADDR'];
            $ip           = isset($chat_data['ip']) ? $chat_data['ip'] : '';
            $role         = isset($chat_data['role']) ? $chat_data['role'] : '';
            $timestamp    = isset($chat_data['timestamp']) ? intval($chat_data['timestamp']) : '';
            $chat_account = isset($chat_data['chat_account']) ? $chat_data['chat_account'] : '';

            if(!$chat_account || !in_array($role, $this->role_arr) || !$ip || !$timestamp) {
                return false;
            }

            if($ip == '' || $ip == 'unknown') {
                return false;
            }

            // 生成签名
            $sign = $this->built_chat_sign($chat_data);

            // 对聊天账号进行加密
            $encryption_key = $this->generate_encryption_key($sign, $ip, $timestamp);
            $uid            = $this->encrypt($chat_account, $encryption_key);
            $uid            = $uid ? str_replace('+', '_', $uid) : '';

            // 对用户角色进行加密
            $role = $this->encrypt(md5($role), $encryption_key);
            $role = $role ? str_replace('+', '_', $role) : '';

            $data['uid']       = $uid;
            $data['sign']      = $sign;
            $data['role']      = $role;
            $data['timestamp'] = $timestamp;

            return $data;
        }
    }