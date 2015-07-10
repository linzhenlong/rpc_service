<?php
/**
 * Created by linzl
 * User: linzl<linzhenlong@smzdm.com>
 * Date: 15/7/10
 * Time: 上午11:57
 */


/**
 * 自定义常量文件.
 */


/**
 *  如果ini没有设置时区,则设置一个默认的.
 */
if (!ini_get('date.timezone')) {
    date_default_timezone_set('Asia/Shanghai');
}

/**
 *  显示错误到终端.
 */

ini_set("display_errors", 'on');

/**
 *  连接失败.
 */
define('SERVICE_CONNECT_FAIL', 1);

/**
 *  发送失败.
 */
define('SERVICE_SEND_FAIL', 2);

/**
 * workerman 的版本.
 */
define("WORKERMAN_VERSION",'3.1.7');

/**
 *  service 状态 启动中 1.
 */
define("SERVICE_STATUS_STARTING", 1);

/**
 *  service 状态 运行中 2.
 */
define("SERVICE_STATUS_RUNNING", 2);

/**
 *  service 状态 停止 4.
 */
define("SERVICE_STATUS_SHUTDOWN", 4);

/**
 *  service 状态 平滑启动中 8.
 */
define("SERVICE_STATUS_RELOADING", 8);

/**
 *  给子进程发送重启命令 SERVICE_KILL_WORKER_TIMER_TIME 秒后
 */
define("SERVICE_KILL_WORKER_TIMER_TIME", 1);

/**
 *  默认的backlog,即内核中用于存放未被进程认领(accept)的连接队列长度.
 */
define("SERVICE_DEFAULT_BACKLOG", 1024);

echo "this is dev".PHP_EOL;