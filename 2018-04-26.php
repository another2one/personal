<?php

// +----------------------------------------------------------------------
// | 1. trait	 extends 	implements 用法及不同，trait优先级较高
// +----------------------------------------------------------------------
// | 2. xdebug
// +----------------------------------------------------------------------

    require './li_func.php';

// 1

    trait traitLog
    {
        public function publicF ()
        {
            echo __METHOD__ . ' trait public function' . "<br/>";
        }

        protected function protectF ()
        {
            echo __METHOD__ . ' trait protected function' . "<br/>";
        }
    }

    class parentLog
    {
        public function publicF ()
        {
            echo __METHOD__ . ' public function' . "<br/>";
        }

        protected function protectF ()
        {
            echo __METHOD__ . ' protected function' . "<br/>";
        }
    }

    class childLog extends parentLog
    {
        use traitLog;

        public function doPublish ()
        {
            $this->publicF ();
            parent::protectF ();
            $this->protectF ();
        }

        public function publicF ()
        {
            echo __METHOD__ . ' public function' . "<br/>";
        }
    }

    $childLog = new childLog();
    $childLog->doPublish (); //输出  Publish::publicF public function 		Log::protectF protected function