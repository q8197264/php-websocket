<?php
class ThreadHandler extends Thread
{
    public function __construct($socket, $index)
    {
        $this->socket = $socket;
        $this->index = $index;
    }

    public function run()
    {
        //多线程测试
        sleep(mt_rand(2,3));echo $this->index.':'.$this->isRunning()."\r\n";

        $event_base = event_base_new();
        $event = event_new();

        event_set($event, $this->socket, EV_READ|EV_PERSIST, array($this,'ev_accept'));
        event_base_set($event, $event_base);
        event_add($event);
        event_base_loop($event_base);

        exit(0);
    }

    protected function ev_accept()
    {
        while (true) {
            $connect = socket_accept($this->socket);
            if ($connect === false) {
                continue;
            }
            var_dump($connect);

            $notice = 'notice server data';
            $bytes = @socket_write($connect, $notice, strlen($notice));

            $bytes = @socket_recv($connect, $buffer, 1024, 0);
            var_dump($buffer);

            socket_close($connect);
        }
    }
}