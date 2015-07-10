<?php
/**
 * Created by linzl
 * User: linzl<linzhenlong@smzdm.com>
 * Date: 15/7/10
 * Time: 上午11:39
 */

namespace Service;

use Service\Lib\Timer;

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

    /**
     * udp最大包长
     * @var integer SERVICE_MAX_UDP_PACKAGE_SIZE.
     */
    const MAX_UDP_PACKAGE_SIZE = SERVICE_MAX_UDP_PACKAGE_SIZE;

    /**
     * worker 的名称, 用于在运行status命令时标记进程.
     * @var string
     */
    public $name = "none";

    /**
     * 设置当前worker实例的进程数.
     * @var int
     */
    public $count = 1;

    /**
     * 设置当前worker进程的运行用户,启动时需要root超级权限.
     * @var string
     */
    public $user = '';

    /**
     * 当前worker进程是否可以平滑启动.
     * @var boolean
     */
    public $reloadable = true;

    /**
     *  当worker进程启动时,如果设置了$onWorkerStart回调函数，则运行此钩子函数一般用于进程启动后初始化工作.
     * @var callback
     */
    public $onWorkerStart = null;

    /**
     *  当有客户端连接时，如果设置了$onConnect回调函数，则运行.
     * @var callback
     */
    public $onConnect = null;

    /**
     * 当有客户端连接上发来数据时，如果设置了$onMessage回调，则运行.
     * @var callback
     */
    public $onMessage = null;

    /**
     *  当客户端的连接关闭时，如果设置了$onClose回调，则运行.
     * @var callback
     */
    public $onClose = null;

    /**
     * 当客户端的连接发生错误时,如果设置了$onError回调，则运行
     * 错误一般为客户端断开连接导致数据发送失败、服务端的发送缓冲区满导致发送失败等.
     * @var callback
     */
    public $onError = null;

    /**
     * 当连接的发送缓冲区满时，如果设置了$onBufferFull回调，则执行.
     * @var callback
     */
    public $onBufferFull = null;

    /**
     * 当链接的发送缓冲区域被清空，如果设置了$onBufferDrain回调，则执行.
     * @var callback
     */
    public $onBufferDrain = null;

    /**
     * 当进程退出时(由于平滑启动或者服务停止导致),如果设置了此回调，则运行.
     * @var callback
     */
    public $onWorkerStop = null;

    /**
     *  传输层协议.
     * @var string
     */
    public $transport = 'tcp';

    /**
     *  所有的客户端连接.
     * @var array
     */
    public $connections = array();

    /**
     *  应用层协议,由初始化worker时指定
     *  例如: new worker('http://0.0.0.0:8080');指定使用http协议.
     * @var string
     */
    protected $_protocol = '';

    /**
     * 当前worker实例初始化目录位置,用于设置应用自动加载的根目录.
     * @var string
     */
    protected $_appInitPath = '';

    /**
     * 是否以守护进程的方式运行。运行start时加上-d参数会自动以守护进程的方式运行.
     * 例如:php start.php start -d
     * @var boolean
     */
    protected static $daemonize = false;

    /**
     *  重定向标准输出,即所有的echo、var_dump等终端输出写到对应文件中
     *  注意 此参数只有在以守护进程方式运行时有效
     * @var string
     */
    public static $stdoutFile = '/dev/null';

    /**
     * pid文件的路径及名称
     * 例如Worker:$pidFile = '/tmp/workerman.pid';
     * 注意 此属性一般不必手动设置,默认会放到php临时目录中.
     * @var string
     */
    public static $pidFile = '';

    /**
     * 日志目录,默认在workerman根目录下,与applications同级
     * 可以手动设置
     * 例如:Worker::$flogFile = '/tmp/workerman.log';
     * @var string
     */
    public static $logFile = '';

    /**
     * 全局事件轮询库,用于监听所有资源的可读可写事件.
     * @var Select/Libevent
     */
    public static $globalEvent = null;

    /**
     *  主进程pid.
     * @var integer
     */
    protected static $_masterPid = 0;

    /**
     *  监听的socket.
     * @var stream
     */
    protected $_mainSocket = null;

    /**
     * socket名称,包括应用层协议+ip+端口号,在初始worker时设置.
     * 值类似 http://0.0.0.0:80
     * @var string
     */
    protected $_socketName = '';

    /**
     * socket的上下文,具体选项设置可以在初始化worker时传递.
     * @var null
     */
    protected $_context = null;

    /**
     * 所有的worker实例.
     * @var array
     */
    protected static $_workers = array();

    /**
     * 所有worker进程的pid.
     * 格式为[worker_id=>[pid=>pid,pid=>pid,..],..]
     * @var array
     */
    protected static $_pidMap = array();

    /**
     * 所有需要重启的进程pid.
     * 格式为 [pid=>pid, pid=>pid]
     * @var array
     */
    protected static $_pidsToRestart = array();

    /**
     * 当前worker的状态.
     * @var integer
     */
    protected static $_status = self::STATUS_STARTING;

    /**
     * 所有worker名称(name属性)中最大长度,用于运行 status 命令格式化输出.
     * @var integer
     */
    protected static $_maxWorkerNameLength = 12;

    /**
     * 所有socket名称(_socketName属性)中的最大长度,用于 status 命令时格式化输出.
     * @var integer
     */
    protected static $_maxSocketNameLength = 12;

    /**
     *  所有user名称(user属性)中最大长度,用于运行 status 命令时格式化输出.
     * @var integer
     */
    protected static $_maxUserNameLength = 12;

    /**
     * 运行 status 命令时用于保存结果的文件名.
     * @var string
     */
    protected static $_statisticsFile = '';

    /**
     * 启用的全局入口文件.
     * 例如: php start.php start ，则入口文件为start.php
     * @var string
     */
    protected static $_startFile = '';
    /**
     * 全局统计数据,用于在运行 status 命令时展示
     * 统计的内容包括 workerman 启动的时间戳及每组worker进程的退出次数及退出状态码.
     * @var array
     */
    protected static $_globalStatistics = [
        'start_timestamp' => 0,
        'worker_exit_info' =>[]
    ];

    public static function runAll()
    {
        /**
         * 初始化环境变量
         */
        self::init();
        /**
         *  解析命令
         */

    }
    public static function init()
    {
        // 如果没有设置$pidFile,则生成默认值
        if (empty(self::$pidFile)) {
            $backtrace = debug_backtrace(); // 产生一条 php 的回溯跟踪.
            self::$_startFile = $backtrace[count($backtrace)-1]['file'];
            self::$pidFile = sys_get_temp_dir().'/rpc_service'.str_replace('/','_',self::$_startFile).'.pid';
        }

        // 如果没有设置日志文件,则生成一个默认值
        if (empty(self::$logFile)) {
            self::$logFile = __DIR__. '/../rpc_service.log';
        }
        // 标记状态为启动中.
        self::$_status = self::STATUS_STARTING;
        // 启动时间戳
        self::$_globalStatistics['start_timestamp'] = time();
        // 设置status文件位置
        self::$_statisticsFile = sys_get_temp_dir().'/rpc_service.status';

        // 尝试设置进程名称(需要php>=5.5 或是安装了proctitle扩展)
        self::setProcessTitle('rpc_service: master process start_file='.self::$_startFile);

        // 初始化定时器
        Timer::init();
    }

    /**
     *  设置当前进程的名称,在ps -aux 命令中有用.
     *  注意需要php>=5.5 或是安装protitle扩展.
     * @param string $title 进程名称.
     */
    protected static function setProcessTitle($title)
    {
        // PHP_VERSION => 5.5
        if (function_exists('cli_set_process_title')) {
            @cli_set_process_title($title);
        } elseif (extension_loaded('proctitle') && function_exists('setproctitle')) {
            // 需要扩展
            @setproctitle($title);
        }
    }
    static function test()
    {
        echo self::VERSION.PHP_EOL;
        echo self::STATUS_STARTING;
    }

}