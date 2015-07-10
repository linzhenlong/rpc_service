<?php
/**
 * Created by linzl
 * User: linzl<linzhenlong@smzdm.com>
 * Date: 15/7/10
 * Time: 上午11:39
 */

namespace Service;

use Service\Lib\Timer;
use Service\Events\EventInterface;

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
    public $workerId = '';

    public function __construct($socket_name = '', $context_option = array())
    {
        $this->workerId = spl_object_hash($this);
        self::$_workers[$this->workerId] = $this;
        self::$_pidMap[$this->workerId] = array();

        // 获取实例化文件路径,用于自动加载设置根目录
        $backtrace = debug_backtrace();
        $this->_appInitPath = dirname($backtrace[0]['file']);

        // 设置socket上下文.
        if ($socket_name) {
            $this->_socketName = $socket_name;
            if (!isset($context_option['socket']['backlog'])) {
                $context_option['socket']['backlog'] = self::DEFAULT_BACKLOG;
            }
            $this->_context = stream_context_create($context_option);
        }
    }

    public static function runAll()
    {
        /**
         * 初始化环境变量
         */
        self::init();
        /**
         *  解析命令
         */
        self::parseCommand();
        // 尝试以守护进程模式运行
        self::daemonize();
        // 初始化所有worker实例，主要是监听端口.
        self::initWorkers();
        // 初始化信号处理函数.
        self::installSignal();
    }

    protected static function installSignal()
    {
        // stop
        // 安装一个信号处理器
        pcntl_signal(SIGINT, array('Service\Worker', 'signalHandler'),false);
        $a = debug_backtrace();
        var_dump($a);
    }

    public static function signalHandler($signal)
    {
        switch($signal)
        {
            case SIGINT:
                self::log("callback Service\Worker::signalHandler stop signal start...");
                self::stopAll();
                self::log("callback Service\Worker::signalHandler stop signal end...");
                break;
        }
    }

    public static function stopAll()
    {
        self::$_status = self::STATUS_SHUTDOWN;
        // 主进程部分.
        if (self::$_masterPid === posix_getpid()) {
            self::log("rpc_service[".basename(self::$_startFile)."] Stopping ...");
            $worker_pid_array = self::getAllWorkerPids();
        }
    }
    
    /**
     *  初始化所有worker实例.
     */
    protected static function initWorkers()
    {
        foreach(self::$_workers as $worker) {
            if (empty($worker->name)) {
                $worker->name = 'none';
            }
             // 获取所有worker名称中最大长度
            $worker_name_length = strlen($worker->name);
            if (self::$_maxWorkerNameLength < $worker_name_length) {
                self::$_maxWorkerNameLength = $worker_name_length;
            }
            $socket_name_length = strlen($worker->getSocketName());
            if (self::$_maxSocketNameLength < $socket_name_length) {
                self::$_maxSocketNameLength = $socket_name_length;
            }
            // 获得运行用户名的最大长度.
            if (empty($worker->user) || posix_getuid() !== 0) {
                $worker->user = self::getCurrentUser();
            }
            $user_name_length = strlen($worker->user);
            if (self::$_maxUserNameLength < $user_name_length) {
                self::$_maxUserNameLength = $user_name_length;
            }
            // 监听端口.
            $worker->listen();
        }
    }


    public function listen()
    {
        Autoloader::setRootPath($this->_appInitPath);
        if (!$this->_socketName) {
            return;
        }
        // 获取应用层通讯地址及监听端口.
        list($scheme, $address) = explode(":", $this->_socketName, 2);
        if ("tcp" != $scheme && "udp" != $scheme) {
            $scheme = ucfirst($scheme);
            $this->_protocol = '\\Protocols\\'.$scheme;
            if(!class_exists($this->_protocol)) {
                $this->_protocol = "\\Service\\Protocols\\".$scheme;
                if (!class_exists($this->_protocol)) {
                    throw new \Exception("class".$this->_protocol." not exists");
                }
            }
        } else if("udp" === $scheme) {
            $this->transport = $scheme;
        }
        // stream_socket_server
        // Create an Internet or Unix domain server socket
        // resource stream_socket_server ( string $local_socket [, int &$errno [, string &$errstr [, int $flags = STREAM_SERVER_BIND | STREAM_SERVER_LISTEN [, resource $context ]]]] )

        // flags
        // 如果传输协议是 udp 的话flags 使用STREAM_SERVER_BIND
        // 因为For UDP sockets, you must use STREAM_SERVER_BIND as the flags parameter.
        $flags = $this->transport === "udp" ? STREAM_SERVER_BIND : STREAM_SERVER_BIND | STREAM_SERVER_LISTEN;

        // errno
        $errno = 0;

        // errstr
        $errmsg = '';
        $this->_mainSocket = stream_socket_server($this->transport.":".$address, $errno, $errmsg,$flags, $this->_context);
        if (!$this->_mainSocket) {
            throw new \Exception($errmsg);
        }
        // 尝试打开tcp的keepalive,关闭TCP Nagle算法
        if(function_exists('socket_import_stream')) {
            $socket = socket_import_stream($this->_mainSocket);
            @socket_set_option($socket, SOL_SOCKET, SO_KEEPALIVE, 1);
            @socket_set_option($socket, SOL_SOCKET, TCP_NODELAY, 1);
        }
        // 设置非阻塞.
        stream_set_blocking($this->_mainSocket, 0);

        // 放到全局事件轮询中监听_mainSocket可读事件（客户端连接事件）
        if (self::$globalEvent) {
            if ($this->transport !== "udp") {
                self::$globalEvent->add($this->_mainSocket, EventInterface::EV_READ, array($this,'acceptConnection'));
            } else {
                self::$globalEvent->add($this->_mainSocket, EventInterface::EV_READ, array($this, 'acceptUdpConnection'));
            }
        }
    }
    /**
     *  获取当前用户名.
     * @return string
     */
    public static function getCurrentUser()
    {
        // 获取当前进程的用户id.
        $uid = posix_getuid();
        $user_info = posix_getpwuid($uid); //通过用户id 查询出来用户信息.
        $user_name = isset($user_info['name']) ? $user_info['name'] : "none";
        return $user_name;
    }
    /**
     * 获取socket名称.
     * @return string
     */
    public function getSocketName()
    {
        return $this->_socketName ? $this->_socketName: 'none';
    }
    /**
     * 初始化环境变量.
     */
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
     *  解析命令.
     *  php start.php start|stop|restart|reload|status
     */
    public static function parseCommand()
    {
        // 检查运行命令行参数.
        global $argv;
        $start_file = $argv[0];
        if (!isset($argv[1])) {
            exit("Usage: php {$start_file} {start|stop|restart|reload|status} \n");
        }
        // 命令
        $command = trim($argv[1]);
        // 子命令, 目前只支持-d
        $command2 = isset($argv[2]) ? $argv[2] : '';
        // 记录日志
        $mode = '';
        if ($command === 'start') {
            if ($command2 === '-d') {
                $mode = "in DAEMON mode";
            } else {
                $mode = "in DEBUG mode";
            }
        }
        self::log("rpc_service[$start_file] $command $mode");
        // 检查主进程是否在运行
        $master_pid = @file_get_contents(self::$pidFile);
        $master_is_alive = $master_pid && @posix_kill($master_pid, 0);
        if ($master_is_alive) {
            if ($command === 'start') {
                self::log("rpc_service[$start_file] is running");
            }
        } else if($command !== 'start' && $command !== "restart") {
            self::log("rpc_service[$start_file] not run");
        }
        // 根据命令做相应的处理
        switch ($command)
        {
            case 'start':
                if ($command2 === '-d') {
                     // 守护进程的方式.
                    self::$daemonize = true;
                }
            break;
            case 'status':
                // 尝试删除统计数据，避免脏数据
                if (is_file(self::$_statisticsFile)) {
                    @unlink(self::$_statisticsFile);
                }
                // 向主进程发送 SIGUSER2 信号,然后主进程会向所有子进程发送 SIGUSER2 信号
                // 所有进程收到 SIGUSR2 信号后会向 $_statisticsFile 写入自己的状态
                posix_kill($master_pid, SIGUSR2);
                // 睡眠100毫秒,等待子进程将自己的状态写入到$_statisticsFile 指定文件中去.
                usleep(100000);
                readfile(self::$_statisticsFile);
                exit(0);
            break;
            case 'restart': // 重启服务.
                // todo 重启操作  先stop 然后start
            case 'stop':
                self::log("rpc_service[$start_file] is stoping ...");
                // 向主进程发送SIGINT 信号, 主进程会向所有子进程发送SIGINT信号.
                $master_pid && posix_kill($master_pid, SIGINT);
                // 如果 $timeout 秒后主进程没有退出则展示失败界面.
                $timeout = 5;
                $start_time = time();
                while(1) {
                    // 检查主进程是否存活
                    $master_is_alive = $master_pid && posix_kill($master_pid, 0);
                    if ($master_is_alive) {
                        if (time() - $start_time >= $timeout) {
                            self::log("rpc_service[$start_file] stop fail...");
                            exit;
                        }
                        usleep(100000);
                        continue;
                    }
                    self::log("rpc_service[$start_file] stop success");
                    break;
                }
            break;
            case 'reload': // 平滑重启
                posix_kill($master_pid, SIGUSR1);
                self::log("rpc_service[$start_file] reload");
            break;
            // 未知命令
            default:
                exit("Usage: php {$start_file} {start|stop|restart|reload|status} \n");
        }
    }

    /**
     * 尝试以守护进程模式运行.
     *
     * @throws \Exception
     */
    public static function daemonize()
    {
        if (!self::$daemonize) {
            return;
        }
        umask(0);
        $pid = pcntl_fork(); // 在当前进程的当前位置产生分支(子进程).
        if (-1 === $pid) {
            throw new \Exception('fork fail');
        } elseif($pid > 0) {
            exit(0);
        }
        if (-1 === posix_setsid()) {
            throw new \Exception("setsid fail");
        }
        // fork again avoid SVR4 system regain the control of terminal
        $pid = pcntl_fork();
        if (-1 === $pid) {
            throw new \Exception("fork fail");
        } elseif(0 !== $pid) {
            exit(0);
        }
    }
    /**
     * 写日志.
     * @param string $msg 日志信息.
     */
    public static function log($msg = "")
    {
        $msg = $msg."\n";
        if (self::$_status === self::STATUS_STARTING || !self::$daemonize) {
            echo $msg;
        }
        @file_put_contents(self::$logFile, date("Y-m-d H:i:s")."".$msg, FILE_APPEND | LOCK_EX);
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
