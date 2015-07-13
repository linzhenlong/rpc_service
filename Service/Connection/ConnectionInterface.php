<?php
/**
 * Created by linzl
 * User: linzl<linzhenlong@smzdm.com>
 * Date: 15/7/13
 * Time: 上午10:40
 */

namespace Service\Connection;

/**
 *  connection 类的接口
 */

abstract class ConnectionInterface
{

    /**
     *  status 命令的统计数据.
     * @var array
     */
    public static $statistics = array(
        'connection_count' => 0,
        'total_request' => 0,
        'throw_exception' => 0,
        'send_fail'      => 0,
    );

    /**
     * 当收到数据时,如果有设置$onMessage回调,则执行.
     * @var callback
     */
    public $onMessage = null;

    /**
     * 当连接关闭时，如果设置了onClose回调,则执行
     * @var callback
     */
    public $onClose = null;

    /**
     * 当出现错误时，如果设置了$onError回调,则执行
     * @var callback
     */
    public $onError = null;

    /**
     * 发送数据给对端.
     * @param $send_buffer
     *
     * @return mixed
     */
    abstract public function send($send_buffer);

    /**
     * 获取远端ip
     * @return mixed
     */
    abstract public function getRemoteIp();

    /**
     * 获取远端端口.
     * @return mixed
     */
    abstract public function getRemotePort();

    /**
     *  关闭连接,为了保持接口一致,udp保留此方法,当udp时调用此方法无任何作用
     * @param null $data
     *
     * @return mixed
     */
    abstract public function close($data = null);
}