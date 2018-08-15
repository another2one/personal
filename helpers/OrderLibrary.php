<?php

    /**
     * 订单扩展类
     */
    class OrderLibrary
    {
        /**
         * CI对象
         *
         * @var [type]
         */
        protected $CI;

        function __construct()
        {
            $CI       = &get_instance();
            $this->CI = $CI;
        }

        /**
         * 更新订单信息（产品价格，预订数量，开始时间，订单总额等）
         *
         * @param string $order_sn 订单号
         * @param bool $save_mode  是否需要保存订单信息
         * @return [type] [description]
         */
        public function update_info($order_sn, $save_mode = false)
        {
            //error_reporting(E_ALL);
            //ini_set('display_errors','1');
            if(!$order_sn) {
                return false;
            }

            // 获取订单信息
            $this->CI->load->model('order_model');
            $order = $this->CI->order_model->get_single(array('order_sn' => $order_sn));

            if($order['cycle_id'] > 0) {
                return $this->update_lesson_info($order_sn, $save_mode);
            }

            if(!$order) {
                return false;
            }

            // 获取产品信息
            $this->CI->load->model('trip_model');
            $trip = $this->CI->trip_model->get_single(array('trip_id' => $order['trip_id']));
            if(!$trip) {
                return false;
            }

            // 处理价格
            $this->CI->load->model('trip_model');
            $trip = $this->CI->trip_model->calculate_price($trip);

            $trip_options = isset($trip['options']) && $trip['options'] ? unserialize($trip['options']) : array();
            $trip_content = isset($trip['content']) && $trip['content'] ? unserialize($trip['content']) : array();

            // 开始、持续、结束时间
            $duration_time = isset($trip['duration_time']) ? $trip['duration_time'] : 1;
            $duration_type = isset($trip['duration_type']) ? $trip['duration_type'] : '';
            $tmp_duration  = $duration_type == 'day' ? $duration_time * 60 * 24 : $duration_time * 60;

            $book_num = $order['book_num'];
            if($trip['category_id'] == 2) {
                $tmp_duration  = $book_num * 60 * 24;
                $duration_time = $book_num;
            }

            // 导游和一次性活动时开始时间为当地人设置的服务开始时间
            $time_start = $order['time_start'];
            if($trip['category_id'] == 2 || $trip['category_id'] == 5) {
                $time_start = strtotime(date('m/d/Y H:i', strtotime(date('Y-m-d', $time_start) . ' ' . date('H:i', $trip['time_start']))));
            }

            $tmp_start = $time_start + $tmp_duration;
            $end_year  = date('Y', $tmp_start);
            $end_month = date('j', $tmp_start);
            $end_day   = date('n', $tmp_start);
            $time_end  = mktime(23, 59, 59, $end_day, $end_month, $end_year);

            $order['time_start'] = $time_start;
            $order['time_end']   = $time_end;

            $order['options']                  = isset($order['options']) && $order['options'] ? unserialize($order['options']) : array();
            $order['options']['duration_time'] = $duration_time;
            $order['options']['duration_type'] = $duration_type;
            $order['options']['confirm_time']  = 48;
            $order['options']['advance_days']  = $trip['advance_days'];
            $order['options']['refund_days']   = $trip['refund_days'];
            $order['options']['category_id']   = isset($trip['category_id']) ? $trip['category_id'] : '';

            // 费用清单
            $order['options']['fee_list'] = isset($trip_content['cost_details']['fee_list']) ? $trip_content['cost_details']['fee_list'] : array();
            if(!empty($trip_content['cost_details']['extra_fee'])) {
                array_push($order['options']['fee_list'], $trip_content['cost_details']['extra_fee']);
            }

            // 获取车辆服务价格
            if($trip['category_id'] == 3) {
                $vehicle_type = isset($order['options']['vehicle_type']) ? $order['options']['vehicle_type'] : '';
                if(!empty($trip_options['price_' . $vehicle_type])) {
                    $trip['usd_price']                 = $trip_options['price_' . $vehicle_type];
                    $order['options']['vehicle_price'] = $trip_options['price_' . $vehicle_type];
                }

                $this->CI->load->model('trip_category_model');
                $trip_category                   = $this->CI->trip_category_model->get_single(array('trip_id' => $trip['trip_id']));
                $order['options']['category_id'] = isset($trip_category['category_id']) ? $trip_category['category_id'] : '';
            } elseif($trip['category_id'] == 1 || $trip['category_id'] == 5) {
                // 接送地址
                $this->CI->load->model('city_model');
                $this->CI->load->model('area_model');

                $meeting_city = $this->CI->city_model->get_single(array('city_id' => $trip['city_id'], 'e_name'));
                $meeting_area = $this->CI->area_model->get_single(array('area_id' => $trip['area_id'], 'e_name'));

                $area_name = isset($meeting_area['e_name']) ? $meeting_area['e_name'] : '';
                $city_name = isset($meeting_city['e_name']) ? $meeting_city['e_name'] : '';
                $address   = isset($trip['address']) ? $trip['address'] : '';

                $place_destination = $area_name ? $area_name . ($city_name ? ', ' . $city_name : '') : '';
                $place_destination .= $place_destination && $address ? ', ' . $address : '';

                $order['options']['place_destination'] = $place_destination;
            }

            // 获取汇率
            $this->CI->load->model('sys_config_model');
            $cny_usd_rate = $this->CI->sys_config_model->get_single(array('key' => 'cny_usd_rate'));
            $cny_usd_rate = isset($cny_usd_rate['value']) ? $cny_usd_rate['value'] : 1;

            // 保存价格、汇率信息
            $order['options']['price_type']   = isset($trip['price_type']) ? $trip['price_type'] : '';
            $order['options']['guide_price']  = isset($trip['local_price']) ? $trip['local_price'] : '';
            $order['options']['usd_price']    = isset($trip['usd_price']) ? $trip['usd_price'] : '';
            $order['options']['cny_price']    = isset($trip['cny_price']) ? $trip['cny_price'] : '';
            $order['options']['cny_usd_rate'] = $cny_usd_rate;
            $order['options']                 = serialize($order['options']);

            // 获取默认显示的价格
            $price_config  = $this->CI->config->item('price');
            $default_price = isset($price_config['default']) ? $price_config['default'] : 'usd';

            // 下单的信息
            $order['amount'] = $trip['usd_price'] * $book_num;;
            $order['status']        = 0;
            $order['currency_code'] = strtoupper($default_price);
            $order['trip_id']       = $trip['trip_id'];

            // 是否需要翻译服务
            // if ($order['guide_fee'] > 0)
            // {
            // 	$order['guide_fee'] = $trip['translation_fee'];
            // 	$order['amount'] += $order['guide_fee'];
            // }

            // $order['order_time'] = time();
            $order['is_lock'] = 0;
            // $order['currency_code'] = $trip['currency_code'];

            if($save_mode === true) {
                $result = $this->CI->order_model->save($order);
                if(!$result) {
                    return false;
                }
            }

            return $order;
        }

        /**
         * 更新课程订单信息（产品价格，预订数量，开始时间，订单总额等）
         *
         * @param string $order_sn 订单号
         * @param bool $save_mode  是否需要保存订单信息
         * @return [type] [description]
         */
        public function update_lesson_info($order_sn, $save_mode = false)
        {
            if(!$order_sn) {
                return false;
            }

            // 获取订单信息
            $this->CI->load->model('order_model');
            $order = $this->CI->order_model->get_single(array('order_sn' => $order_sn));
            if(!$order) {
                return false;
            }

            // 获取产品信息
            $this->CI->load->model('lesson_cycle_model');
            $trip = $this->CI->lesson_cycle_model->get_lesson_single(array('cycle_id' => $order['cycle_id']));

            if(!$trip) {
                return false;
            }

            // 获取汇率
            $this->CI->load->model('sys_config_model');
            $cny_usd_rate = $this->CI->sys_config_model->get_single(array('key' => 'cny_usd_rate'));
            $cny_usd_rate = isset($cny_usd_rate['value']) ? $cny_usd_rate['value'] : 1;

            // 保存价格、汇率信息
            $order_data['options']['cycle_id']     = $order['cycle_id'];
            $order_data['options']['cycle_desc']   = $trip['cycle_desc'];
            $order_data['options']['spec_desc']    = $trip['spec_desc'];
            $order_data['options']['price_type']   = 'usd';
            $order_data['options']['guide_price']  = $trip['local_price'];
            $order_data['options']['usd_price']    = $trip['usd_price'];
            $order_data['options']['cny_price']    = $trip['usd_price'] * $cny_usd_rate;
            $order_data['options']['cny_usd_rate'] = $cny_usd_rate;

            // 获取默认显示的价格
            $price_config  = $this->CI->config->item('price');
            $default_price = isset($price_config['default']) ? $price_config['default'] : 'usd';

            // 下单的信息
            $order['amount'] = $trip['usd_price'] * $order['book_num'];;
            $order['status']        = 0;
            $order['currency_code'] = strtoupper($default_price);
            $order['trip_id']       = $trip['trip_id'];
            $order['cycle_id']      = $trip['cycle_id'];


            $order['is_lock'] = 0;

            if($save_mode === true) {
                $result = $this->CI->order_model->save($order);
                if(!$result) {
                    return false;
                }
            }

            return $order;
        }
    }