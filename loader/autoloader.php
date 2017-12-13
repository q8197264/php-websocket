<?php
namespace socket\loader;
/**
 * Created by PhpStorm.
 * User: Liu xiaoquan
 * Date: 2017/11/20
 * Time: 9:48
 */
class autoloader
{
    protected $dir;

    function __construct($dir=null)
    {
        $this->dir = empty($dir) ? dirname(dirname(__FILE__)) : $dir;
    }

    public static function register($dir=null)
    {
        ini_set('unserialize_autoload', 'spl_autoload_call');
        spl_autoload_register(array(new self, 'loader'), $dir);
    }

    protected function loader($classname)
    {
        $list = explode('\\',__NAMESPACE__);
        if (stripos($classname, $list[0]) != 0) {
            return false;
        }

        $file = $this->dir.ltrim($classname, $list[0]).'.php';
        if (is_file($file)) {
            require_once($file);
        }
    }
}