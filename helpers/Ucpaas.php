<?php

    /**
     * Created by PhpStorm.
     * User: UCPAAS JackZhao
     * Date: 2014/10/22
     * Time: 12:04
     * Dec : ucpass php sdk
     */
    class Ucpaas
    {

        /**
         *  云之讯REST API版本号。当前版本号为：2014-06-30
         */
        const SoftVersion = "2014-06-30";
        /**
         * API请求地址
         */
        const BaseUrl = "https://api.ucpaas.com/";
        /**
         * @var string
         * 开发者账号ID。由32个英文字母和阿拉伯数字组成的开发者账号唯一标识符。
         */
        private $accountSid;
        /**
         * @var string
         * 开发者账号TOKEN
         */
        private $token;
        /**
         * @var string
         * 时间戳
         */
        private $timestamp;


        /**
         * @param $options 数组参数必填
         *                 $options = array(
         *
         * )
         * @throws Exception
         */
        public function __construct($options)
        {
            if(is_array($options) && !empty($options)) {
                $this->accountSid = isset($options['accountsid']) ? $options['accountsid'] : '';
                $this->token      = isset($options['token']) ? $options['token'] : '';
                $this->timestamp  = date("YmdHis") + 7200;
            } else {
                throw new Exception("非法参数");
            }
        }

        /**
         * 开发者账号信息查询
         *
         * @param string $type 默认json,也可指定xml,否则抛出异常
         * @return mixed|string 返回指定$type格式的数据
         * @throws Exception
         */
        public function getDevinfo($type = 'json')
        {
            if($type == 'json') {
                $type = 'json';
            } elseif($type == 'xml') {
                $type = 'xml';
            } else {
                throw new Exception("只能json或xml，默认为json");
            }
            $url  = self::BaseUrl . self::SoftVersion . '/Accounts/' . $this->accountSid . '?sig=' . $this->getSigParameter();
            $data = $this->getResult($url, null, $type, 'get');
            return $data;
        }

        /**
         * @return string
         * 验证参数,URL后必须带有sig参数，sig= MD5（账户Id + 账户授权令牌 + 时间戳，共32位）(注:转成大写)
         */
        private function getSigParameter()
        {
            $sig = $this->accountSid . $this->token . $this->timestamp;
            return strtoupper(md5($sig));
        }

        /**
         * @param $url
         * @param string $type
         * @return mixed|string
         */
        private function getResult($url, $body = null, $type = 'json', $method)
        {
            $data = $this->connection($url, $body, $type, $method);
            if(isset($data) && !empty($data)) {
                $result = $data;
            } else {
                $result = '没有返回数据';
            }
            return $result;
        }

        /**
         * @param $url
         * @param $type
         * @param $body   post数据
         * @param $method post或get
         * @return mixed|string
         */
        private function connection($url, $body, $type, $method)
        {
            if($type == 'json') {
                $mine = 'application/json';
            } else {
                $mine = 'application/xml';
            }
            if(function_exists("curl_init")) {
                $header = array(
                    'Accept:' . $mine,
                    'Content-Type:' . $mine . ';charset=utf-8',
                    'Authorization:' . $this->getAuthorization(),
                );
                $ch     = curl_init($url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
                curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
                if($method == 'post') {
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                }
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
                $result = curl_exec($ch);
                curl_close($ch);
            } else {
                $opts                = array();
                $opts['http']        = array();
                $headers             = array(
                    "method" => strtoupper($method),
                );
                $headers[]           = 'Accept:' . $mine;
                $headers['header']   = array();
                $headers['header'][] = "Authorization: " . $this->getAuthorization();
                $headers['header'][] = 'Content-Type:' . $mine . ';charset=utf-8';

                if(!empty($body)) {
                    $headers['header'][] = 'Content-Length:' . strlen($body);
                    $headers['content']  = $body;
                }

                $opts['http'] = $headers;
                $result       = file_get_contents($url, false, stream_context_create($opts));
            }
            return $result;
        }

        /**
         * @return string
         * 包头验证信息,使用Base64编码（账户Id:时间戳）
         */
        private function getAuthorization()
        {
            $data = $this->accountSid . ":" . $this->timestamp;
            return trim(base64_encode($data));
        }

        /**
         * 申请client账号
         *
         * @param $appId        应用ID
         * @param $clientType   计费方式。0  开发者计费；1 云平台计费。默认为0.
         * @param $charge       充值的金额
         * @param $friendlyName 昵称
         * @param $mobile       手机号码
         * @return json/xml
         */
        public function applyClient($appId, $clientType, $charge, $friendlyName, $mobile, $type = 'json')
        {
            $url = self::BaseUrl . self::SoftVersion . '/Accounts/' . $this->accountSid . '/Clients?sig=' . $this->getSigParameter();
            if($type == 'json') {
                $body_json                           = array();
                $body_json['client']                 = array();
                $body_json['client']['appId']        = $appId;
                $body_json['client']['clientType']   = $clientType;
                $body_json['client']['charge']       = $charge;
                $body_json['client']['friendlyName'] = $friendlyName;
                $body_json['client']['mobile']       = $mobile;
                $body                                = json_encode($body_json);
            } elseif($type == 'xml') {
                $body_xml = '<?xml version="1.0" encoding="utf-8"?>
                        <client><appId>' . $appId . '</appId>
                        <clientType>' . $clientType . '</clientType>
                        <charge>' . $charge . '</charge>
                        <friendlyName>' . $friendlyName . '</friendlyName>
                        <mobile>' . $mobile . '</mobile>
                        </client>';
                $body     = trim($body_xml);
            } else {
                throw new Exception("只能json或xml，默认为json");
            }
            $data = $this->getResult($url, $body, $type, 'post');
            return $data;
        }

        /**
         * @param $appId
         * @param $start
         * @param $limit
         * @param string $type
         * @return mixed|string
         * @throws Exception
         */
        public function getClientList($appId, $start, $limit, $type = 'json')
        {
            $url = self::BaseUrl . self::SoftVersion . '/Accounts/' . $this->accountSid . '/clientList?sig=' . $this->getSigParameter();
            if($type == 'json') {
                $body_json = array('client' => array(
                    'appId' => $appId,
                    'start' => $start,
                    'limit' => $limit
                ));
                $body      = json_encode($body_json);
            } elseif($type == 'xml') {
                $body_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
                        <client>
                            <appId>' . $appId . '</appId>
                            <start>' . $start . '</start>
                            <limit>' . $limit . '</limit>
                        </client>';
                $body     = trim($body_xml);
            } else {
                throw new Exception("只能json或xml，默认为json");
            }
            $data = $this->getResult($url, $body, $type, 'post');
            return $data;
        }

        /**
         * 以Client账号方式查询Client信息
         *
         * @param $appId
         * @param $clientNumber
         * @param string $type
         * @return mixed|string
         * @throws Exception
         */
        public function getClientInfo($appId, $clientNumber, $type = 'json')
        {
            if($type == 'json') {
                $type = 'json';
            } elseif($type == 'xml') {
                $type = 'xml';
            } else {
                throw new Exception("只能json或xml，默认为json");
            }
            $url  = self::BaseUrl . self::SoftVersion . '/Accounts/' . $this->accountSid . '?sig=' . $this->getSigParameter() . '&clientNumber=' . $clientNumber . '&appId=' . $appId;
            $data = $this->getResult($url, null, $type, 'get');
            return $data;
        }

        /**
         * 应用话单下载,通过HTTPS POST方式提交请求，云之讯融合通讯开放平台收到请求后，返回应用话单下载地址及文件下载检验码。
         * day 代表前一天的数据（从00:00 – 23:59）；week代表前一周的数据(周一 到周日)
         * month表示上一个月的数据（上个月表示当前月减1，如果今天是4月10号，则查询结果是3月份的数据）
         * $appId = "xxxx";
         * $date = "day";
         *
         * @param $appId
         * @param $date
         * @param string $type
         * @return mixed|string
         * @throws Exception
         */
        public function getBillList($appId, $date, $type = 'json')
        {
            $url = self::BaseUrl . self::SoftVersion . '/Accounts/' . $this->accountSid . '/billList?sig=' . $this->getSigParameter();
            if($type == 'json') {
                $body_json = array('appBill' => array(
                    'appId' => $appId,
                    'date'  => $date,
                ));
                $body      = json_encode($body_json);
            } elseif($type == 'xml') {
                $body_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
                        <appBill>
                            <appId>' . $appId . '</appId>
                            <date>' . $date . '</date>
                        </appBill>';
                $body     = trim($body_xml);
            } else {
                throw new Exception("只能json或xml，默认为json");
            }
            $data = $this->getResult($url, $body, $type, 'post');
            return $data;
        }

        /**
         * Client充值,通过HTTPS POST方式提交充值请求，云之讯融合通讯开放平台收到请求后，返回Client充值结果。
         *
         * @param $appId
         * @param $clientNumber
         * @param $chargeType
         * @param $charge
         * @param string $type
         * @return mixed|string
         * @throws Exception
         */
        public function chargeClient($appId, $clientNumber, $chargeType, $charge, $type = 'json')
        {
            $url = self::BaseUrl . self::SoftVersion . '/Accounts/' . $this->accountSid . '/chargeClient?sig=' . $this->getSigParameter();
            if($type == 'json') {
                $body_json = array('client' => array(
                    'appId'        => $appId,
                    'clientNumber' => $clientNumber,
                    'chargeType'   => $chargeType,
                    'charge'       => $charge
                ));
                $body      = json_encode($body_json);
            } elseif($type == 'xml') {
                $body_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
                        <client>
                            <clientNumber>' . $clientNumber . '</clientNumber>
                            <chargeType>' . $chargeType . '</chargeType>
                            <charge>' . $charge . '</charge>
                            <appId>' . $appId . '</appId>
                        </client>';
                $body     = trim($body_xml);
            } else {
                throw new Exception("只能json或xml，默认为json");
            }
            $data = $this->getResult($url, $body, $type, 'post');
            return $data;

        }

        /**
         * 双向回拨,云之讯融合通讯开放平台收到请求后，将向两个电话终端发起呼叫，双方接通电话后进行通话。
         *
         * @param $appId
         * @param $fromClient
         * @param $to
         * @param null $fromSerNum
         * @param null $toSerNum
         * @param string $type
         * @return mixed|string
         * @throws Exception
         */
        public function callBack($appId, $fromClient, $to, $fromSerNum = null, $toSerNum = null, $type = 'json')
        {
            $url = self::BaseUrl . self::SoftVersion . '/Accounts/' . $this->accountSid . '/Calls/callBack?sig=' . $this->getSigParameter();
            if($type == 'json') {
                $body_json = array('callback' => array(
                    'appId'      => $appId,
                    'fromClient' => $fromClient,
                    'fromSerNum' => $fromSerNum,
                    'to'         => $to,
                    'toSerNum'   => $toSerNum
                ));
                $body      = json_encode($body_json);
            } elseif($type == 'xml') {
                $body_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
                        <callback>
                            <fromClient>' . $fromClient . '</clientNumber>
                            <fromSerNum>' . $fromSerNum . '</chargeType>
                            <to>' . $to . '</charge>
                            <toSerNum>' . $toSerNum . '</toSerNum>
                            <appId>' . $appId . '</appId>
                        </callback>';
                $body     = trim($body_xml);
            } else {
                throw new Exception("只能json或xml，默认为json");
            }
            $data = $this->getResult($url, $body, $type, 'post');
            return $data;
        }

        /**
         * 语音验证码,云之讯融合通讯开放平台收到请求后，向对象电话终端发起呼叫，接通电话后将播放指定语音验证码序列
         *
         * @param $appId
         * @param $verifyCode
         * @param $to
         * @param string $type
         * @return mixed|string
         * @throws Exception
         */
        public function voiceCode($appId, $verifyCode, $to, $type = 'json')
        {
            $url = self::BaseUrl . self::SoftVersion . '/Accounts/' . $this->accountSid . '/Calls/voiceCode?sig=' . $this->getSigParameter();
            if($type == 'json') {
                $body_json = array('voiceCode' => array(
                    'appId'      => $appId,
                    'verifyCode' => $verifyCode,
                    'to'         => $to
                ));
                $body      = json_encode($body_json);
            } elseif($type == 'xml') {
                $body_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
                        <voiceCode>
                            <verifyCode>' . $verifyCode . '</clientNumber>
                            <to>' . $to . '</charge>
                            <appId>' . $appId . '</appId>
                        </voiceCode>';
                $body     = trim($body_xml);
            } else {
                throw new Exception("只能json或xml，默认为json");
            }
            $data = $this->getResult($url, $body, $type, 'post');
            return $data;
        }

        /**
         * 短信验证码（模板短信）,默认以65个汉字（同65个英文）为一条（可容纳字数受您应用名称占用字符影响），
         * 超过长度短信平台将会自动分割为多条发送。分割后的多条短信将按照具体占用条数计费。
         *
         * @param $appId
         * @param $to
         * @param $templateId
         * @param null $param
         * @param string $type
         * @return mixed|string
         * @throws Exception
         */
        public function templateSMS($appId, $to, $templateId, $param = null, $type = 'json')
        {
            $url = self::BaseUrl . self::SoftVersion . '/Accounts/' . $this->accountSid . '/Messages/templateSMS?sig=' . $this->getSigParameter();
            if($type == 'json') {
                $body_json = array('templateSMS' => array(
                    'appId'      => $appId,
                    'templateId' => $templateId,
                    'to'         => $to,
                    'param'      => $param
                ));
                $body      = json_encode($body_json);
            } elseif($type == 'xml') {
                $body_xml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
                        <templateSMS>
                            <templateId>' . $templateId . '</templateId>
                            <to>' . $to . '</to>
                            <param>' . $param . '</param>
                            <appId>' . $appId . '</appId>
                        </templateSMS>';
                $body     = trim($body_xml);
            } else {
                throw new Exception("只能json或xml，默认为json");
            }
            $data = $this->getResult($url, $body, $type, 'post');
            return $data;
        }

        /**
         * 子账号服务-创建子账号
         *
         * @param $appId        app应用ID
         * @param $friendlyName 昵称
         * @param $mobile       手机号码
         * @param $userId       用户注册子账号输入的userid，原则上跟手机号码相同。同一个应用内唯一·
         * @param string $type  默认json,也可指定xml,否则抛出异常
         * @return mixed|string 返回指定$type格式的数据
         * @throws Exception
         */
        public function createClient($appId, $friendlyName, $mobile, $userId, $type = 'json')
        {
            $url = self::BaseUrl . self::SoftVersion . '/Accounts/' . $this->accountSid . '/Clients?sig=' . $this->getSigParameter();
            if($type == 'json') {
                $body_json                           = array();
                $body_json['client']                 = array();
                $body_json['client']['appId']        = $appId;
                $body_json['client']['friendlyName'] = $friendlyName;
                $body_json['client']['mobile']       = $mobile;
                $body_json['client']['userId']       = $userId;
                $body                                = json_encode($body_json);
            } elseif($type == 'xml') {
                $body_xml = '<?xml version="1.0" encoding="utf-8"?>
                        <client><appId>' . $appId . '</appId>
                        <friendlyName>' . $friendlyName . '</friendlyName>
                        <mobile>' . $mobile . '</mobile>
                        <userId>' . $userId . '</userId>
                        </client>';
                $body     = trim($body_xml);
            } else {
                throw new Exception("只能json或xml，默认为json");
            }
            $data = $this->getResult($url, $body, $type, 'post');
            return $data;
        }

        /**
         * 子账号服务-释放子账号
         *
         * @param $userId      用户注册子账号输入的userid，原则上跟手机号码相同。同一个应用内唯一·
         * @param $appId       app应用ID
         * @param string $type 默认json,也可指定xml,否则抛出异常
         * @return mixed|string 返回指定$type格式的数据
         * @throws Exception
         */
        public function releaseClient($userId, $appId, $type = 'json')
        {
            $url = self::BaseUrl . self::SoftVersion . '/Accounts/' . $this->accountSid . '/dropClient?sig=' . $this->getSigParameter();
            if($type == 'json') {
                $body_json                     = array();
                $body_json['client']           = array();
                $body_json['client']['appId']  = $appId;
                $body_json['client']['userId'] = $userId;
                $body                          = json_encode($body_json);
            } elseif($type == 'xml') {
                $body_xml = '<?xml version="1.0" encoding="utf-8"?>
                        <client>
                        <userId>' . $userId . '</userId>
                        <appId>' . $appId . '</appId >
                        </client>';
                $body     = trim($body_xml);
            } else {
                throw new Exception("只能json或xml，默认为json");
            }
            $data = $this->getResult($url, $body, $type, 'post');
            return $data;
        }

        /**
         * 子账号服务-通过手机号获取子账号
         *
         * @param $appId       app应用ID
         * @param $mobile      手机号码
         * @param string $type 默认json,也可指定xml,否则抛出异常
         * @return mixed|string返回指定$type格式的数据
         * @throws Exception
         */
        public function getClientInfoByMobile($appId, $mobile, $type = 'json')
        {
            if($type == 'json') {
                $type = 'json';
            } elseif($type == 'xml') {
                $type = 'xml';
            } else {
                throw new Exception("只能json或xml，默认为json");
            }
            $url  = self::BaseUrl . self::SoftVersion . '/Accounts/' . $this->accountSid . '/ClientsByMobile?sig=' . $this->getSigParameter() . '&mobile=' . $mobile . '&appId=' . $appId;
            $data = $this->getResult($url, null, $type, 'get');
            return $data;
        }

        /**
         * 子账号服务-通过useId获取子账号
         *
         * @param $appId          app应用ID
         * @param $userId         用户注册子账号输入的userid，原则上跟手机号码相同。同一个应用内唯一·
         * @param string $type    默认json,也可指定xml,否则抛出异常
         * @return mixed|string   返回指定$type格式的数据
         * @throws Exception
         */
        public function getClientInfoByUserId($appId, $userId, $type = 'json')
        {
            if($type == 'json') {
                $type = 'json';
            } elseif($type == 'xml') {
                $type = 'xml';
            } else {
                throw new Exception("只能json或xml，默认为json");
            }
            $url  = self::BaseUrl . self::SoftVersion . '/Accounts/' . $this->accountSid . '/ClientsByUserId?sig=' . $this->getSigParameter() . '&userId=' . $userId . '&appId=' . $appId;
            $data = $this->getResult($url, null, $type, 'get');
            return $data;
        }

        /**
         * 群组服务-创建群组
         *
         * @param $appId          APP应用ID
         * @param $userId         用户注册子账号输入的userId，原则上跟手机号码相同。同一个应用内唯一·
         * @param $groupId        群组ID
         * @param $groupName      群组名称
         * @param string $type    默认json,也可指定xml,否则抛出异常
         * @return mixed|string   返回指定$type格式的数据
         */
        public function imCreateGroup($appId, $userId, $groupId, $groupName, $type = 'json')
        {
            return $this->imGroupOperation($appId, $userId, $groupId, $groupName, '/im/group/createGroup', $type);
        }

        /**
         * 群组服务-群组操作共用的方法
         *
         * @param $appId          APP应用ID
         * @param $userId         用户注册子账号输入的userId，原则上跟手机号码相同。同一个应用内唯一·
         * @param $groupId        群组ID
         * @param $groupName      群组名称
         * @param $path           群组REST API接口请求映射路径
         * @param string $type    默认json,也可指定xml,否则抛出异常
         * @return mixed|string   返回指定$type格式的数据
         * @throws Exception
         */
        public function imGroupOperation($appId, $userId, $groupId, $groupName, $path, $type = 'json')
        {
            $url = self::BaseUrl . self::SoftVersion . '/Accounts/' . $this->accountSid . $path . '?sig=' . $this->getSigParameter();
            if($type == 'json') {
                $body_json                         = array();
                $body_json['imGroup']              = array();
                $body_json['imGroup']['appId']     = $appId;
                $body_json['imGroup']['userId']    = $userId;
                $body_json['imGroup']['groupId']   = $groupId;
                $body_json['imGroup']['groupName'] = $groupName;
                $body                              = json_encode($body_json);
            } elseif($type == 'xml') {
                $body_xml = '<?xml version="1.0" encoding="utf-8"?>
                        <imGroup><appId>' . $appId . '</appId>
                        <userId>' . $userId . '</userId>
                        <groupId>' . $groupId . '</groupId>
                        <groupName>' . $groupName . '</groupName>
                        </imGroup>';
                $body     = trim($body_xml);
            } else {
                throw new Exception("只能json或xml，默认为json");
            }
            $data = $this->getResult($url, $body, $type, 'post');
            return $data;
        }

        /**
         * 群组服务-释放群组
         *
         * @param $appId          APP应用ID
         * @param $groupId        群组ID
         * @param string $type    默认json,也可指定xml,否则抛出异常
         * @return mixed|string   返回指定$type格式的数据
         */
        public function imDismissGroup($appId, $groupId, $type = 'json')
        {
            return $this->imGroupOperation($appId, null, $groupId, null, '/im/group/dismissGroup', $type);
        }

        /**
         * 群组服务-加入群组
         *
         * @param $appId          APP应用ID
         * @param $userId         用户注册子账号输入的userId，原则上跟手机号码相同。同一个应用内唯一·（这里是数组集合）
         * @param $groupId        群组ID
         * @param string $type    默认json,也可指定xml,否则抛出异常
         * @return mixed|string   返回指定$type格式的数据
         */
        public function imJoinGroupBatch($appId, $userId, $groupId, $type = 'json')
        {
            return $this->imGroupOperation($appId, $userId, $groupId, null, '/im/group/joinGroupBatch', $type);
        }

        /**
         * 群组服务-退出群组
         *
         * @param $appId          APP应用ID
         * @param $userId         用户注册子账号输入的userId，原则上跟手机号码相同。同一个应用内唯一·
         * @param $groupId        群组ID
         * @param string $type    默认json,也可指定xml,否则抛出异常
         * @return mixed|string   返回指定$type格式的数据
         */
        public function imQuitGroup($appId, $userId, $groupId, $type = 'json')
        {
            return $this->imGroupOperation($appId, $userId, $groupId, null, '/im/group/quitGroup', $type);
        }

        /**
         * 群组服务-更新群组
         *
         * @param $appId          APP应用ID
         * @param $groupId        群组ID
         * @param $groupName      群组名称
         * @param string $type    默认json,也可指定xml,否则抛出异常
         * @return mixed|string   返回指定$type格式的数据
         */
        public function imUpdateGroup($appId, $groupId, $groupName, $type = 'json')
        {
            return $this->imGroupOperation($appId, null, $groupId, $groupName, '/im/group/updateGroup', $type);
        }

        /**
         * 群组服务-查询群组信息
         *
         * @param $appId          APP应用ID
         * @param $groupId        群组ID
         * @param string $type    默认json,也可指定xml,否则抛出异常
         * @return mixed|string   返回指定$type格式的数据
         */
        public function imGetGroup($appId, $groupId, $type = 'json')
        {
            return $this->imGroupOperation($appId, null, $groupId, null, '/im/group/getGroup', $type);
        }
    }