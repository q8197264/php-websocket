<?php
$host = '127.0.0.1';
$port = 8080;
$cli_sock = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

if (!socket_connect($cli_sock, $host, $port)) {
    die(socket_strerror(socket_last_error($cli_sock)));
}

$msg = "CLIENT DATA";

$bytes = socket_write($cli_sock, $msg, strlen($msg));
if ($bytes === false) {
    die(socket_strerror(socket_last_error($cli_sock)));
}

//while($flag = @socket_recv($cli_sock, $buffer, 5, 0)>0) {
//    $asc=ord(substr($buffer, -1));
//    if ($asc==0) {
//        $read.=substr($buffer,0,-1);
//        break;
//    }else{
//        $read.=$buffer;
//    }
//    echo $flag;
//    if ($flag<0){
//        //error
//        return false;
//    }elseif ($flag==0){
//        //Client disconnected
//        return  false;
//    }else{
//        echo $read;
//        return $read;
//    }
//}

$bytes = socket_recv($cli_sock, $buffer, 1024, 0);
if ($bytes===false) {
    echo socket_strerror(socket_last_error($cli_sock));
}
var_dump($buffer);

//echo '<p>';
//$bytes = socket_recv($cli_sock, $buffer, 1024, 0);
//var_dump($buffer);

socket_close($cli_sock);