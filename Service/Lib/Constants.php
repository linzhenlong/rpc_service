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