<?php
/**
 * Created by linzl
 * User: linzl<linzhenlong@smzdm.com>
 * Date: 15/7/13
 * Time: 上午10:58
 */

namespace Service\Connection;

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
    
}