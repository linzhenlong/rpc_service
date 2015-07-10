<?php
/**
 *
 * Created by linzl
 * User: linzl<linzhenlong@smzdm.com>
 * Date: 15/7/10
 * Time: 上午11:17
 */

/**
 *  服务启动文件.
 *  run with cmd.
 *  php start.php start
 */

ini_set("display_errors", 'on');

/**
 *  命名空间.
 */
use Service\Worker;


/**
 *  检查pcntl 扩展.
 */
if(!extension_loaded('pcntl'))
{
    exit("Please install pcntl extension. See http://doc3.workerman.net/install/install.html\n");
}

/**
 * 检查 posix 扩展.
 */
if(!extension_loaded('posix'))
{
    exit("Please install posix extension. See http://doc3.workerman.net/install/install.html\n");
}

/**
 *  标记是否全局启动.
 */
define("GLOBAL_START", 1);

/**
 *  包含自动加载类
 */
require_once __DIR__. '/Service/Autoloader.php';

Worker::test();