<?php
/**
 * Created by PhpStorm.
 * User: Liu xiaoquan
 * Date: 2017/10/24
 * Time: 17:01
 */
class http
{

    protected static $magicGUI = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    //握手
    public static function response($buffer)
    {
        var_dump($buffer);
        list($resource, $host, $origin, $key) = self::getHttp($buffer);
        $response = self::setHttp($key);

        return $response;
    }

    protected static function getHttp($request)
    {
        $r = $h = $o = $key = null;
        if (preg_match("/GET (.*) HTTP/i", $request, $match)) { $r = $match[1];}
        if (preg_match("/Host: (.*)\r\n/", $request, $match)) { $h = $match[1];}
        if (preg_match("/Origin: (.*)\r\n/", $request, $match)) { $o = $match[1];}
        if (preg_match("/Sec-WebSocket-Key: (.*)\r\n/", $request, $match)) { $key = $match[1];}

        return array($r, $h, $o, $key);
    }

    //响应握手
    protected static function setHttp($key)
    {
        /*
            HTTP/1.1 101 Switching Protocols
            Upgrade: websocket //依然是固定的，告诉客户端即将升级的是Websocket协议，而不是mozillasocket，lurnarsocket或者shitsocket
            Connection: Upgrade
            Sec-WebSocket-Accept: HSmrc0sMlYUkAGmm5OPpG2HaGWk= //这个则是经过服务器确认，并且加密过后的 Sec-WebSocket-Key,也就是client要求建立WebSocket验证的凭证
            Sec-WebSocket-Protocol: chat
         */
        $upgrade = "HTTP/1.1 101 Switching Protocols\r\n".
            "Upgrade: websocket\r\n".
            "Connection: Upgrade\r\n".
            "Sec-WebSocket-Accept:".self::getKey($key)."\r\n\r\n";

        return $upgrade;
    }

    protected static function getKey($key)
    {
        $handshake = sha1($key.self::$magicGUI);
        $char = array_reduce(str_split($handshake, 2), function($carry, $item){
            return $carry .= chr(hexdec($item));//十六进制转化ascii码
        });

        return base64_encode($char);
    }
}