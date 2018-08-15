<?php
    if(!defined('BASEPATH')) exit('No direct script access allowed');

    class Layout
    {
        public $obj;
        public $layout;
        public $title = '';
        public $meta = array();
        public $js_files = array();
        public $css_files = array();
        public $data = array();

        public function __construct($layout = "layout/main_layout")
        {
            $this->obj    =& get_instance();
            $this->layout = $layout;
        }

        public function setLayout($layout)
        {
            $this->layout = $layout;
        }

        public function getData()
        {
            return $this->data;
        }

        public function setData($data)
        {
            $this->data = $data;
        }

        /**
         * 设置标题
         *
         * @param  [type] $title [description]
         * @return [type]        [description]
         */
        public function headTitle($title)
        {
            $this->title = $title;
        }

        /**
         * 设置meta内容
         *
         * @param  [type] $type    author、description、keywords、generator、revised、content-type、content-type、expires、refresh、set-cookie
         * @param  [type] $content 内容
         * @return [type]          [description]
         */
        public function headMeta($type, $content)
        {
            $this->meta[] = array('type' => $type, 'content' => $content);
        }

        /**
         * 加载js文件
         */
        public function add_js($file_path)
        {
            array_push($this->js_files, $file_path);
        }

        /**
         * 加载css文件
         */
        public function add_css($file_path)
        {
            array_push($this->css_files, $file_path);
        }

        public function view($view, $data = array(), $return = false)
        {
            $data['content_for_layout'] = $this->obj->load->view($view, $data, true);

            $data = array_merge($this->data, $data);

            // lizhi
            if(isset($data['wy_bt'])) {
                $data['wy_bt'] = str_replace('ComeToChina', 'come to china', $data['wy_bt']);
            }
            // lizhi
            if(isset($data['wy_ms'])) {
                $data['wy_ms'] = str_replace('ComeToChina', 'come to china', $data['wy_ms']);
            }

            if($return) {
                $output = $this->obj->load->view($this->layout, $data, true);
                return $output;
            } else {
                $this->obj->load->view($this->layout, $data, false);
            }
        }
    }
