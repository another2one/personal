<?php

    /**
     * 任务队列
     */
    class RedisQueue
    {
        private $_redis;

        public function __construct($param = null)
        {
            $this->_redis = RedisFactory::get($param);
        }

        /**
         * 入队一个 Task
         *
         * @param  [type]  $name          队列名称
         * @param  [type]  $id            任务id（或者其数组）
         * @param  integer $timeout       入队超时时间(秒)
         * @param  integer $afterInterval [description]
         * @return [type]                 [description]
         */
        public function enqueue($name, $id, $timeout = 10, $afterInterval = 0)
        {
            //合法性检测
            if(empty($name) || empty($id) || $timeout <= 0) return false;

            //加锁
            if(!$this->_redis->lock->lock("Queue:{$name}", $timeout)) {
                Logger::get('queue')->error("enqueue faild becouse of lock failure: name = $name, id = $id");
                return false;
            }

            //入队时以当前时间戳作为 score
            $score = microtime(true) + $afterInterval;
            //入队
            foreach((array)$id as $item) {
                //先判断下是否已经存在该id了
                if(false === $this->_redis->zset->getScore("Queue:$name", $item)) {
                    $this->_redis->zset->add("Queue:$name", $score, $item);
                }
            }

            //解锁
            $this->_redis->lock->unlock("Queue:$name");

            return true;

        }

        /**
         * 出队一个Task，需要指定$id 和 $score
         * 如果$score 与队列中的匹配则出队，否则认为该Task已被重新入队过，当前操作按失败处理
         *
         * @param  [type]  $name    队列名称
         * @param  [type]  $id      任务标识
         * @param  [type]  $score   任务对应score，从队列中获取任务时会返回一个score，只有$score和队列中的值匹配时Task才会被出队
         * @param  integer $timeout 超时时间(秒)
         * @return [type]           Task是否成功，返回false可能是redis操作失败，也有可能是$score与队列中的值不匹配（这表示该Task自从获取到本地之后被其他线程入队过）
         */
        public function dequeue($name, $id, $score, $timeout = 10)
        {
            //合法性检测
            if(empty($name) || empty($id) || empty($score)) return false;

            //加锁
            if(!$this->_redis->lock->lock("Queue:$name", $timeout)) {
                Logger:
                get('queue')->error("dequeue faild becouse of lock lailure:name=$name, id = $id");
                return false;
            }

            //出队
            //先取出redis的score
            $serverScore = $this->_redis->zset->getScore("Queue:$name", $id);
            $result      = false;
            //先判断传进来的score和redis的score是否是一样
            if($serverScore == $score) {
                //删掉该$id
                $result = (float)$this->_redis->zset->delete("Queue:$name", $id);
                if($result == false) {
                    Logger::get('queue')->error("dequeue faild because of redis delete failure: name =$name, id = $id");
                }
            }
            //解锁
            $this->_redis->lock->unlock("Queue:$name");

            return $result;
        }

        /**
         * 获取队列顶部若干个Task 并将其出队
         *
         * @param  [type]  $name    队列名称
         * @param  integer $count   数量
         * @param  integer $timeout 超时时间
         * @return [type]           返回数组[0=>['id'=> , 'score'=> ], 1=>['id'=> , 'score'=> ], 2=>['id'=> , 'score'=> ]]
         */
        public function pop($name, $count = 1, $timeout = 10)
        {
            //合法性检测
            if(empty($name) || $count <= 0) return [];

            //加锁
            if(!$this->_redis->lock->lock("Queue:$name")) {
                Logger::get('queue')->error("pop faild because of pop failure: name = $name, count = $count");
                return false;
            }

            //取出若干的Task
            $result = [];
            $array  = $this->_redis->zset->getByScore("Queue:$name", false, microtime(true), true, false, [0, $count]);

            //将其放在$result数组里 并 删除掉redis对应的id
            foreach($array as $id => $score) {
                $result[] = ['id' => $id, 'score' => $score];
                $this->_redis->zset->delete("Queue:$name", $id);
            }

            //解锁
            $this->_redis->lock->unlock("Queue:$name");

            return $count == 1 ? (empty($result) ? false : $result[0]) : $result;
        }

        /**
         * 获取队列顶部的若干个Task
         *
         * @param  [type]  $name  队列名称
         * @param  integer $count 数量
         * @return [type]         返回数组[0=>['id'=> , 'score'=> ], 1=>['id'=> , 'score'=> ], 2=>['id'=> , 'score'=> ]]
         */
        public function top($name, $count = 1)
        {
            //合法性检测
            if(empty($name) || $count < 1) return [];

            //取错若干个Task
            $result = [];
            $array  = $this->_redis->zset->getByScore("Queue:$name", false, microtime(true), true, false, [0, $count]);

            //将Task存放在数组里
            foreach($array as $id => $score) {
                $result[] = ['id' => $id, 'score' => $score];
            }

            //返回数组
            return $count == 1 ? (empty($result) ? false : $result[0]) : $result;
        }
    }