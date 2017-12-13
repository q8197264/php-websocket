<?php
namespace socket\frame;

/**
 * Created by PhpStorm.
 * User: Liu xiaoquan
 * Date: 2017/11/17
 * Time: 18:13
 */
class frame
{
    public static function encode($message, $type)
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
                break;
        }
        $frame[0] = chr(128+$h);
        $length = strlen($message);
        $hexlen = dechex($length);
        if ($hexlen < 126) {
            $frame[1] = chr($length);
        } else if ($hexlen < 65535) {
            $frame[1] = chr(126);
        } else {
            $frame[1] = chr(127);
        }
        $frame[2] = $message;


        return implode($frame);
    }

    public static function decode($buffer)
    {
        $header = array(
            'fin' => ord($buffer[0]) >> 7,
            'srv1' => (ord($buffer[1]) >> 6) & 1,
            'srv2' => (ord($buffer[1])>>5) & 1
        );

        var_dump($header);
    }
}