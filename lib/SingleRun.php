<?php
/**
 * Created by PhpStorm.
 * User: Liu xiaoquan
 * Date: 2017/10/24
 * Time: 17:18
 */
class SingleRun
{
    private $sockets_pool;
    private $isHandShake = false;

    protected $maxConnectNumber = 5;

    public function __construct()
    {
        $this->sev_sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if (!$this->sev_sock) {
            $this->checkErr($this->sev_sock);
        }

        var_dump(config::$host.':'.config::$port);
        if (!socket_bind($this->sev_sock, config::$host, config::$port)) {
            $this->checkErr($this->sev_sock);
        }
        socket_set_option($this->sev_sock, SOL_SOCKET, SO_REUSEADDR, 1) or die("Failed: socket_option()");
        if (!socket_listen($this->sev_sock, 1024)) {
            $this->checkErr($this->sev_sock);
        }

        //连接池
        $this->sockets_pool = array($this->sev_sock);
    }

    public function run()
    {
        while (true) {

            //此变量必须为可引用&变量
            $readfds = $this->sockets_pool;

            //当没有套字节可以读写继续等待， 第四个参数null为阻塞， 0为非阻塞，>0 为socket数目
            if (@socket_select($readfds, $writefds=null, $excepts=null, null) < 1){
                continue;
            }

            $this->poll($readfds, $this->sev_sock);
        }
    }

    //多客户端poll轮徇
    protected function poll($sockets, $sev_sock)
    {
        foreach ($sockets as $socket) {
            if ($sev_sock == $socket) {
                $connect = @socket_accept($socket);
                if ($connect < 0) {
                    //false 0
                    echo 'failed: socket_accept() '.socket_strerror(socket_last_error($connect));
                } else {
                    $this->pushConnects($connect);
                    var_dump($sockets);
                }
            } else {
                $bytes = @socket_recv($socket, $buffer, 2048, 0);
                if ($bytes === false) {
                    $errno = socket_last_error($socket);
                    switch ($errno) {
                        case 102: // ENETRESET    -- Network dropped connection because of reset
                        case 103: // ECONNABORTED -- Software caused connection abort
                        case 104: // ECONNRESET   -- Connection reset by peer
                        case 108: // ESHUTDOWN    -- Cannot send after transport endpoint shutdown -- probably more of an error on our part, if we're trying to write after the socket is closed.  Probably not a critical error, though.
                        case 110: // ETIMEDOUT    -- Connection timed out
                        case 111: // ECONNREFUSED -- Connection refused -- We shouldn't see this one, since we're listening... Still not a critical error.
                        case 112: // EHOSTDOWN    -- Host is down -- Again, we shouldn't see this, and again, not critical because it's just one connection and we still want to listen to/for others.
                        case 113: // EHOSTUNREACH -- No route to host
                        case 121: // EREMOTEIO    -- Rempte I/O error -- Their hard drive just blew up.
                        case 125: // ECANCELED    -- Operation canceled
                            echo 'Unusual disconnect on socket:'.socket_strerror($errno);
                            $this->disconnect($socket);
                            break;
                        default:
                            echo 'socket error:'.socket_strerror($errno);
                            exit('recv false');
                    }
                } elseif ($bytes == 0) {
                    echo "Client disconnected. TCP connection lost: ".'='.strlen($buffer);
                    $this->disconnect($socket);
                } else {
                    //判断是否握手:粘包处理
                    if (!$this->isHandShake) {
                        $this->isHandShake = true;

                        //发送数据
                        echo $response = (new http())->response($buffer);
                        $this->send($socket, $response);
                    } else {
                        //掩码处理
                        var_dump('++++++++++ begin +++++++++++');
                        var_dump(substr(bin2hex($buffer), 0, 40));
                        //解码
                        $this->decode($buffer);
//                        $buffer = $this->deframe($buffer);
                        $buffer = $this->uncode($buffer,'');

                        echo '+++ Recv client data: '.substr(iconv('UTF-8','gbk',$buffer),0,40)."... \r\n\r\n";

//                        $msg = $this->frame($buffer);
                        $msg = $this->encode($buffer,'text');
//                        $msg = $this->code($buffer);
//                        var_dump($msg);
                        var_dump('+++++++++++ end ++++++++++');

                        $this->send($socket, $msg);
                    }
                }
            }
        }
    }

    //------------------------ begin 解掩码-------------------------

    // 解析数据帧
    function deframe($buffer)
    {
        $len = $masks = $data = $decoded = null;
        $len = ord($buffer[1]) & 127;

        if ($len === 126)  {
            $masks = substr($buffer, 4, 4);
            $data = substr($buffer, 8);
        } else if ($len === 127)  {
            $masks = substr($buffer, 10, 4);
            $data = substr($buffer, 14);
        } else  {
            $masks = substr($buffer, 2, 4);
            $data = substr($buffer, 6);
        }
        for ($index = 0; $index < strlen($data); $index++) {
            $decoded .= $data[$index] ^ $masks[$index % 4];
        }

        return $decoded;
    }

    //解码函数
    function uncode($str,$key)
    {
        $mask = array();
        $data = '';
        $msg = unpack('H*',$str);

        //头
        $head = substr($msg[1],0,2);//81

        //长度
        if ($head == '81') {
            if (substr($msg[1],2,2) == 'fe') {//126
                $len=substr($msg[1],4,4);
                $len=hexdec($len);
                $msg[1]=substr($msg[1],4);
            } elseif (substr($msg[1],2,2) == 'ff') {//127
                $len=substr($msg[1],4,16);
                $len=hexdec($len);
                $msg[1]=substr($msg[1],16);
            } else {
                $len=substr($msg[1],2,2);//
                $len=hexdec($len);//把十六进制的转换为十进制
            }

            //存在掩码时,所有数据依次掩码处理
            $mask[] = hexdec(substr($msg[1],4,2));
            $mask[] = hexdec(substr($msg[1],6,2));
            $mask[] = hexdec(substr($msg[1],8,2));
            $mask[] = hexdec(substr($msg[1],10,2));

            $s = 12;
            $n = 0;
            $e = strlen($msg[1])-2;
            for ($i=$s; $i<= $e; $i+= 2) {
                $data .= chr($mask[$n%4]^hexdec(substr($msg[1],$i,2)));
                $n++;
            }
        }

        //var_dump($data);
        return $data;
    }

    function decode($message)
    {
        $headers = array(
            //第一字节
            'fin'   => ord($message[0]) >> 7,//取二进制第一位
            'rsv1'  => (ord($message[0]) & 64) >> 6,//取第二位
            'rsv2'  => (ord($message[0]) & 32) >> 5,//取第三位
            'rsv3'  => (ord($message[0]) & 16) >> 4,//取第四位
            'opcode'=> ord($message[0]) & 15,//取二进制最后四位，frame帧类型

            //第二字节
            'mask'  => ord($message[1]) >> 7,//取二进制第一位,判断是否设置掩码
            'payload-len' => ord($message[1]) & 127,//取二进制后7位

            //3-6其它字节
            'masking-key' => '',//4字节掩码或不存在掩码
        );

        if ( 126 == $headers['payload-len']) {//后2位表示长度
            $hexlen = array_reduce(str_split(substr($message, 2, 2)), function($carry, $item) {
                return $carry .= dechex(ord($item));
            });
            $length = hexdec($hexlen);
            $data = substr($message, 4);
        } else if (127 == $headers['payload-len']) {//8位
            $hexlen = array_reduce(str_split(substr($message, 2, 8)), function($carry, $item) {
                return $carry .= hexdec(ord($item));
            });
            $length = hexdec($hexlen);
            $data = substr($message, 10);
        } else {
            $length = $headers['payload-len'];//后7位
            $data = substr($message, 2);
        }
        if (1 == $headers['mask']) {
            $mask = array($data[0], $data[1], $data[2], $data[3]);

            $msg = '';
            for ($i=4; $i < $length; $i++) {
                $msg .= $mask[$i%4] ^ $data[$i];
            }
            $data = $msg;
        }

        return $data;
    }
    //----------------------------------- end -----------------------------------

    //----------------------------------- begin 编码--------------------------------
    protected function tcode($msg)
    {
        $len = strlen($msg);
        $hex = dechex($len);

        $head = chr(129);//text

        if ($len < 126) {
            $length = $len;
        } elseif ($len <= 65535) {
            $hexlen = array_reduce(str_split(str_pad($hex, 4, 0, STR_PAD_LEFT), 2), function($carry, $item){
                return $carry .= hexdec($item);
            });
            $msg = pack('CC', 129, $len);
        } else {

        }
var_dump($len, ord($msg[0]), $msg[1], ord($msg[2]));
        $data = $msg;

        return $data;
    }

    //返回：ascii字符
    protected function frame($msg, $type='text')
    {
        switch ($type) {
            case 'continuous':
                $h = 0;
                break;
            case 'text':
                $h = 1;
                break;
            case 'binary':
                $h = 2;
                break;
            case 'close':
                $h = 8;
                break;
            case 'ping':
                $h = 9;
                break;
            case 'pong':
                $h = 10;
                break;
            default:
                $h = 8;
        }
        $frame[0] = chr($h+128);

        $len = strlen($msg);
        $hex = dechex($len);
        if ($len < 126) { //125长度直接发送, 不用前置dechex(126)
            $frame[1] = chr($len);
        } elseif ($len <= 65535) {//ip包最长65535字节 40kb
            $hexArr = str_split(str_pad($hex, 4, 0, STR_PAD_LEFT), 2);
            $frame[1] = chr(126).array_reduce($hexArr, function($carry, $item){
                    return $carry .= chr(hexdec($item));
                });
        } else {
            $hexArr = str_split(str_pad($hex, 16, 0, STR_PAD_LEFT), 2);
            $frame[1] = chr(127).array_reduce($hexArr, function($carry, $item){
                    return $carry .= chr(hexdec($item));
                });
        }
        var_dump($frame);
        $frame[2] = $msg;
        $data = implode($frame);

        return $data;
    }

    //返回值：十六进制
    function encode($msg, $type='text')
    {
        switch ($type) {
            case 'continuous':
                $h = 0;
                break;
            case 'text':
                $h = 1;
                break;
            case 'binary':
                $h = 2;
                break;
            case 'close':
                $h = 8;
                break;
            case 'ping':
                $h = 9;
                break;
            case 'pong':
                $h = 10;
                break;
            default:
                $h = 8;
        }
        $frame = array();
        $frame[0] = dechex(128+$h);//81
        $len = strlen($msg);
        $s=dechex($len);
        if($len < 126){
            $frame[1] = str_repeat('0',2-strlen($s)).$s;
        }else if($len < 65025){//64k
            $frame[1]=dechex(126).str_repeat('0',4-strlen($s)).$s;
        }else{
            $frame[1]=dechex(127).str_repeat('0',16-strlen($s)).$s;
        }
        $frame[2] = array_reduce(str_split($msg), function($carry, $item){return $carry .= dechex(ord($item));});
        $data = implode('',$frame);

        return pack("H*", $data);//把十六进制保存为二进制字符串
    }

    //------------------------------ end ----------------------------------



    protected function pushConnects($connect)
    {
        //入栈连接池
//        if ($this->maxConnectNumber > count($this->sockets_pool)) {
            $this->sockets_pool[] = $connect;
//        } else {
            //依次关闭除主连接外的旧连接
//            socket_close(array_splice($this->sockets_pool,1,1)[0]);
//        }
    }

    protected function send($connect, $msg)
    {
        if (($bytes = socket_write($connect, $msg, strlen($msg))) === false) {
            echo 'send error:'.socket_strerror(socket_last_error($connect));
        }
    }

    protected function checkErr($socket)
    {
        die(socket_strerror(socket_last_error($socket)));
    }

    //关闭连接
    protected function disconnect($socket)
    {
        socket_close($socket);
        $this->isHandShake = false;
        //删除连接池中相应连接
        $key = array_search($socket, $this->sockets_pool);
        $key>0 AND array_splice($this->sockets_pool, $key, 1);
    }
}