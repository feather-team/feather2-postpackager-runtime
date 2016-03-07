<?php
require dirname(__FILE__) . '/Feather_View_Loader.class.php';

Feather_View_Loader::setImportPath(dirname(__FILE__));     
Feather_View_Loader::import('Feather_View_Cache.class.php');
Feather_View_Loader::import('Feather_View_Plugin_Abstract.class.php');

class Feather_View{
    //默认后缀
    const DEFAULT_SUFFIX = '.tpl';

    //模版目录，可为数组
    public $template_dir = '';
    //插件目录，不可为数组
    public $plugins_dir = '';
    public $tmp_dir = '';   //模版引擎的临时文件存放目录，可不设置
    public $suffix = self::DEFAULT_SUFFIX;

    protected $data = array();
    protected $plugins = array();
    protected $pluginsObject = array();

    //设置值
    public function set($name, $value = ''){
        if(is_array($name)){
            foreach($name as $key => $value){
                $this->data[$key] = $value;
            }
        }else{
            $this->data[$name] = $value;
        }
    }

    //获取值
    public function get($name = null){
        return $name ? isset($this->data[$name]) ? $this->data[$name] : null : $this->data;
    }

    public function __set($name, $value = ''){
        $this->set($name, $value);
    }

    public function __get($name){
        return $this->get($name);
    }

    //执行模版返回
    public function fetch($path, $data = null, $method = null){
        if(!self::checkHasSuffix($path)){
            $path = $path . $this->suffix;
        }

        if($realpath = self::foundRealPath($this->template_dir, $path)){
            if($data){
                $data = array_merge($this->data, $data);
            }else{
                $data = $this->data;
            }

            $opts = array(
                'method' => $method ? $method : __METHOD__,
                'path' => $path,
                'realpath' => $realpath
            );

            return $this->evalContent($data, $content, $path);
        }else{
            throw new Exception($path . ' is not exists!');
        }
    }

    //显示模版
    public function display($path, $charset = 'utf-8', $type = 'text/html'){
        self::sendHeader($charset, $type);
        echo $this->fetch($path, null, __METHOD__);
    }

    public function flush($path, $charset = 'utf-8', $type = 'text/html'){
        self::sendHeader($charset, $type);
        $content = $this->fetch($path, null, __METHOD__);
        
        ob_start();
        echo $content;
        ob_end_flush();
        flush();
    }

    //内嵌加载一个文件
    public function load($path, $data = null){
        echo $this->fetch("{$path}", $data, __METHOD__);
    }

    public function require($path, $data = null){
        return $this->load($path, $data);
    }

    public function include($path, $data = null){
        return $this->load($path, $data);
    }

    //注册一个系统级插件，该插件会在display或者fetch时，自动调用
    public function registerPlugin($name, $opt = array()){
        $this->plugins[] = array($name, $opt);
    }

    //调用被注册的插件
    protected function callPlugins($content, $info = array()){
        foreach($this->plugins as $key => $plugin){
            $content = $this->plugin($plugin[0], isset($plugin[1]) ? $plugin[1] : null)->exec($content, $info);
        }

        return $content;
    }

    //获取plugin实例
    public function plugin($name, $opt = null){
        $classname = __CLASS__ . '_Plugin_' . preg_replace_callback('/(?:^|_)\w/', 'self::toUpperCase', $name);

        if(!class_exists($classname)){
            $classfile = strtolower($classname) . '.php';

            foreach($this->getPluginsDir() as $dir){
                $pluginRealPath = $dir . '/' . $classfile;

                if(is_file($pluginRealPath)){
                    require $pluginRealPath;
                    break;
                }
            }
        }

        if(!isset($this->pluginsObject[$name])){
            $obj = $this->pluginsObject[$name] = new $classname($opt, $this);
        }else{
            $obj = $this->pluginsObject[$name];
        }

        return $obj;
    }

    protected function getPluginsDir(){
        $dirs = (array)$this->plugins_dir;

        foreach((array)$this->template_dir as $dir){
            $dirs[] = $dir . '/plugins';
        }

        $dirs[] = dirname(__FILE__) . '/plugins';

        return $dirs;
    }

    //evaluate content
    protected function evalContent($data489bc39ff0, $content489bc39ff0, $path489bc39ff0 = '__anonymous__'){
        ob_start();
        //extract data
        extract($data489bc39ff0);

        //if tmp dir exists, write tmp file and include;
        if($this->tmp_dir){
            $filename489bc39ff0 = str_replace('/', '_', $path489bc39ff0);
            file_put_contents($filename489bc39ff0, $content489bc39ff0);
            include $filename489bc39ff0;
            //@unlink($filename489bc39ff0);
        }else{
            //evaluate code
            eval("?> {$content489bc39ff0}");
        }

        //return ob content
        $content489bc39ff0 = ob_get_contents();
        //clean buffer
        ob_end_clean();
        
        return $content489bc39ff0;
    }

    public static function sendHeader($charset, $type){
        !headers_sent() && header("Content-type: {$type}; charset={$charset}");
    }

    protected static function foundRealPath($dirs, $path){
        foreach((array)$dirs as $dir){
            $realpath = $dir . '/' . $path;

            if(is_file($realpath)){
                return $realpath;
            }
        }

        if(is_file($path)){
            return $path;
        }

        return false;
    }

    protected static function checkHasSuffix($str){
        return !!preg_match('/\.[^\.]+$/', $str);
    }

    protected static function toUpperCase($match){
        return strtoupper($match[0]);
    }
}