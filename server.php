<?php
require_once(__DIR__.'/lib/config.php');
require_once(__DIR__.'/lib/http.php');

/**
 * Created by PhpStorm.
 * User: Liu xiaoquan
 * Date: 2017/10/16
 * Time: 9:46
 */
class Server
{
    protected  $handler;

    public function run()
    {
        $this->singleRun()->run();

//        $this->multiRun()->run();
    }

    protected function singleRun()
    {
        require_once(__DIR__.'/lib/SingleRun.php');
        return new singleRun();
    }

    protected function multiRun()
    {
        require_once(__DIR__.'/lib/MultiRun.php');
        require_once(__DIR__.'/lib/ThreadHandler.php');
        return new MultiRun(1);
    }
}

$run = new Server;
$run->run();