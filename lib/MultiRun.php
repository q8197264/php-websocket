<?php
/**
 * Created by PhpStorm.
 * User: Liu xiaoquan
 * Date: 2017/10/24
 * Time: 18:14
 */
class MultiRun
{
    protected $type;

    public function __construct($type = 0)
    {
        $this->type = $type;
    }

    public function run()
    {
        $socket = $this->socket_server(config::$host, config::$port);

        switch ($this->type) {
            case 0://多进程
                $this->multiHandle($socket);
                break;
            case 1://多线程
                $this->multiThread($socket);
                break;
            default:
                break;
        }

    }

    protected function multiThread($socket)
    {
        $threads = [];
        for ($i=0; $i<15; $i++) {
            $threads[] = new ThreadHandler($socket, $i);
        }
        foreach ($threads as $v) {
            $v->start();
//            $v->join();
        }
    }

    protected function multiHandle($socket)
    {
        for ($i=0; $i<50; $i++) {
            if (pcntl_fork() == 0) {
                while (true) {
                    $connect = socket_accept($socket);
                    if ($connect==false) {
                        continue;
                    }

                    $bytes = socket_recv($socket, $buffer, 1024, 0);
                    var_dump($buffer);

                    //0子进程空间
                    echo $msg = 'send msg'.$i;
                    $bytes = @socket_write($connect, $msg, strlen($msg));

                    var_dump($bytes);
                    socket_close($connect);
                }
                exit(0);
            }
        }
    }

    protected function socket_server($host, $port)
    {
        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP) or $this->checkErr($socket);

        if (!socket_bind($socket, $host, $port)) {
            $this->checkErr($socket);
        }

        if (!socket_listen($socket)) {
            $this->checkErr($socket);
        }

        return $socket;
    }

    protected function checkErr($socket)
    {
        die(socket_strerror( socket_last_error( $socket ) ));
    }
}