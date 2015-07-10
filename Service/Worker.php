<?php
/**
 * Created by linzl
 * User: linzl<linzhenlong@smzdm.com>
 * Date: 15/7/10
 * Time: 上午11:39
 */

namespace Service;

class Worker
{
    /**
     * 版本号.
     * @var string
     */
    const VERSION = WORKERMAN_VERSION;

    /**
     * 状态 启动中.
     * @var integer SERVICE_STATUS_STARTING
     */
    const STATUS_STARTING = SERVICE_STATUS_STARTING;

    /**
     * 状态 运行中.
     * @var integer SERVICE_STATUS_RUNNING
     */
    const STATUS_RUNNING = SERVICE_STATUS_RUNNING;

    /**
     * 状态 停止.
     * @var integer SERVICE_STATUS_SHUTDOWN
     */
    const STATUS_SHUTDOWN = SERVICE_STATUS_SHUTDOWN;

    /**
     * 状态 平滑启动中.
     * @var integer SERVICE_STATUS_RELOADING
     */
    const STATUS_RELOADING = SERVICE_STATUS_RELOADING;

    /**
     * 给子进程发送重启命令 KILL_WORKER_TIMER_TIME 秒后
     * 如果对应进程仍然未重启则强行杀死
     * @var integer SERVICE_KILL_WORKER_TIMER_TIME
     */
    const KILL_WORKER_TIMER_TIME = SERVICE_KILL_WORKER_TIMER_TIME;

    /**
     * 默认的backlog，即内核中用于存放未被进程认领（accept）的连接队列长度
     * @var integer SERVICE_DEFAULT_BACKLOG
     */
    const DEFAULT_BACKLOG = SERVICE_DEFAULT_BACKLOG;
    
    static function test()
    {
        echo self::VERSION.PHP_EOL;
        echo self::STATUS_STARTING;
    }

}