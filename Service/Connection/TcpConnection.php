<?php
/**
 * Created by linzl
 * User: linzl<linzhenlong@smzdm.com>
 * Date: 15/7/13
 * Time: 上午10:58
 */

namespace Service\Connection;

use Service\Events\EventInterface;
use Service\Worker;
use \Exception;

class TcpConnection extends ConnectionInterface{

    /**
     * 当数据可读时,从socket缓冲区读取多少字节数据.
     * @var integer
     */
    const  READ_BUFFER_SIZE = 8192;

    /**
     * 连接状态 连接中.
     * @var integer
     */
    const STATUS_CONNECTING = 1;

    /**
     * 连接状态 已经建立连接
     * @var int
     */
    const STATUS_ESTABLISH = 2;

    /**
     * 连接状态 连接关闭中,标识调用了close方法,但是发送缓冲去仍然有数据
     * 等待发送缓冲区的数据发送完毕(写入到socket写缓冲区) 后执行关闭
     * @var integer
     */
    const STATUS_CLOSING = 4;

    /**
     * 连接状态 已经关闭
     * @var integer
     */
    const STATUS_CLOSED = 8;

    /**
     * 当对端发来数据时 如果设置了$onMessage回调,则执行
     * @var callback
     */
    public $onMessage = null;

    /**
     * 当连接关闭时,如果设置了$onClose回调,则执行
     * @var callback
     */
    public $onClose = null;

    /**
     * 当出现错误时,如果设置了$onError回调,则执行
     * @var callback
     */
    public $onError = null;

    /**
     * 当发送缓冲区满时，如果设置了$onBufferFull回调,则执行
     * @var callback
     */
    public $onBufferFull = null;

    /**
     * 当发送缓冲区被清空时,如果设置了$onBufferDrain回调,则执行.
     * @var callback
     */
    public $onBufferDrain = null;

    /**
     * 使用应用层协议,是协议类的名称
     * 值类似于 Service\\Protocols\\Http
     * @var string
     */
    public $protocol = '';

    /**
     * 属于哪个worker
     * @var Worker
     */
    public $worker = null;

    /**
     * 连接的id,一个自增id
     * @var int
     */
    public $id = 0;

    /**
     * 设置当前连接的最大发送缓冲区大小，默认大小为TcpConnection::$defaultMaxSendBufferSize
     * 当发送缓冲区满时，会尝试触发onBufferFull回调(如果没有设置的话)
     * 如果没设置onBufferFull回调,由于发送缓冲区满,则后续发送的数据将被丢弃
     * 并触发onError回调,直到发送缓冲区有空位
     * 此值可以动态设置
     * @var integer
     */
    public $maxSendBufferSize = 1048576;

    /**
     * 默认发送缓冲区大小,设置此属性会影响所有连接的默认发送缓冲区大小
     * 如果想设置某个连接发送缓冲区的大小,可以单独设置对应连接的$maxSendBufferSize属性
     * @var integer
     */
    public static $defaultMaxSendBufferSize = 1048576;

    /**
     * 能接受的最大数据包,为了防止恶意攻击,当数据包的大小大于此值时执行断开
     * 注意 此值可以动态设置
     * 例如: Service\Connection\TcpConnection::$maxPackageSize = 102400;
     * @var integer
     */
    public static $maxPackageSize = 10485760;

    /**
     * id 记录器.
     * @var integer
     */
    protected static $_idRecorder = 1;

    /**
     * 实际的socket资源.
     * @var null
     */
    protected $_socket = null;

    /**
     * 发送缓冲区.
     * @var string
     */
    protected $_sendBuffer = '';

    /**
     * 接收缓冲区.
     * @var string
     */
    protected $_recvBuffer = '';

    /**
     * 当前正在处理的数据包的包长(此值是intput方法的返回值)
     * @var integer
     */
    protected $_currentPackageLength = 0;

    /**
     * 当前的连接状态
     * @var int
     */
    protected $_status = self::STATUS_ESTABLISH;

    /**
     * 对端ip
     * @var string
     */
    protected $_remoteIp = '';

    /**
     * 对端端口.
     * @var int
     */
    protected $_remotePort = 0;

    /**
     * 对端的地址 ip+port
     * 值类似于 192.168.1.100：3698
     * @var string
     */
    protected $_remoteAddress = '';

    /**
     * 是否是停止接收数据.
     * @var boolean
     */
    protected $_isPaused = false;

    public function __construct($socket)
    {
        // 统计数据
        self::$statistics['connection_count']++;
        $this->id = self::$_idRecorder++;
        $this->_socket = $socket;

        /**
         * bool stream_set_blocking ( resource $stream , int $mode )
         * 为资源流设置阻塞或者阻塞模式
         *  参数
         *  1.$stream 资源流
         *  2.$mode 设置阻塞或是非阻塞模式
         *    如果 mode 为0，资源流将会被转换为非阻塞模式；
         *    如果是1，资源流将会被转换为阻塞模式。
         *    该参数的设置将会影响到像 fgets() 和 fread() 这样的函数从资源流里读取数据。
         *    在非阻塞模式下，调用 fgets() 总是会立即返回；而在阻塞模式下，将会一直等到从资源流里面获取到数据才能返回
         */
        stream_set_blocking($this->_socket, 0);
        Worker::$globalEvent->add($this->_socket, EventInterface::EV_READ, array($this, 'baseRead'));
        $this->maxSendBufferSize = self::$defaultMaxSendBufferSize;
    }

    public function send($send_buffer, $raw = false)
    {
        // 如果没有设置以原始数据发送,并且有设置协议按照协议编码
        if (false === $raw && $this->protocol) {
            $parser = $this->protocol;
            $send_buffer = $parser::encode($send_buffer, $this);
            if ($send_buffer === '') {
                return null;
            }
        }
        // 如果当前的状态是连接中,则把数据放入到发送缓冲区
        if ($this->_status === self::STATUS_CONNECTING) {
            $this->_sendBuffer .= $send_buffer;
            return null;
        }
        // 如果当前连接是关闭,则返回false
        else if($this->_status === self::STATUS_CLOSING || $this->_status === self::STATUS_CLOSED) {
            return false;
        }
        // 如果发送缓冲区为空,尝试直接发送
        if ($this->_sendBuffer === '') {
            // 直接发送
            $len = @fwrite($this->_socket, $send_buffer);
            // 所有数据发送完毕
            if ($len === strlen($send_buffer)) {
                return true;
            }
            // 只有部分数据发送成功.
            if ($len > 0) {
                // 未发送成功部分放入缓冲区
                $this->_sendBuffer = substr($send_buffer, $len);
            } else {
                // 如果连接断开
                // feof() :如果文件指针到了 EOF 或者出错时则返回 TRUE，否则返回一个错误（包括 socket 超时），其它情况则返回FALSE
                if (feof($this->_socket)) {
                    // status 统计发送失败次数
                    self::$statistics['send_fail']++;
                    // 如果设置失败回调,则执行
                    if ($this->onError) {
                        try {
                            call_user_func_array($this->onError, $this, SERVICE_SEND_FAIL, 'client closed');
                        } catch(Exception $e) {
                            echo $e;
                        }
                    }
                    // 销毁连接
                    $this->destroy();
                    return false;
                }
                // 连接未断开,发送失败,则把所有数据放入发送缓冲区
                $this->_sendBuffer = $send_buffer;
            }
        }
        // 监听对端口写事件
        Worker::$globalEvent->add($this->_socket, EventInterface::EV_WRITE, array($this,'baseWrite'));


    }
}