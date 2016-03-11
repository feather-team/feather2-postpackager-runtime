<?php
namespace FeatherView;
use FeatherView\Plugin as Plugin;

class Engine{
    protected $suffix = '.tpl';
    protected $templateDir = array();
    protected $tempDir;
    protected $data = array();
    protected $pluginManager;

    public function __construct($config = array()){
        $this->pluginManager = new Plugin\Manager($this);
        $this->initConfig($config);
    }

    protected function initConfig($config){
        foreach($config as $name => $value){
            switch($name){
                case 'templateDir':
                    $this->setTemplateDir($value);
                    break;

                case 'suffix':
                    $this->setTemplateSuffix($value);
                    break;

                case 'plugins':
                    $this->registerSystemPlugin($value);
                    break;

                case 'tempDir':
                    $this->setTempDir($value);
                    break;

                default: ;
            }
        }
    }

    public function registerSystemPlugin($name, $opt = null){
        if(is_array($name)){
            $plugins = $name;

            foreach($plugins as $name => $opt){
                if(is_numeric($name)){
                    $this->pluginManager->registerSystemPlugin($opt);
                }else{
                    $this->pluginManager->registerSystemPlugin($name, $opt);
                }
            }
        }else{
            $this->pluginManager->registerSystemPlugin($name, $opt);
        }
    }

    public function setTemplateDir($dirs = ''){
        $this->templateDir = array();
        $this->addTemplateDir($dirs);
    }

    public function addTemplateDir($dirs = ''){
        $this->templateDir = array_merge($this->templateDir, (array)$dirs);

        foreach($this->templateDir as $dir){
            $this->pluginManager->addPluginDir($dir . '/plugins');
        }

        $this->pluginManager->addPluginDir(__DIR__ . '/plugins');
    }

    public function setTemplateSuffix($suffix){
        $this->suffix = '.' . ltrim($suffix, '.');
    }

    public function setTempDir($dir){
        $this->tempDir = $dir;
    }

    public function plugin($name, $opt = null){
        return $this->pluginManager->instance($name, $opt);
    }

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
        if($realpath = Helper::findFile($this->templateDir, $path, $this->suffix)){
            if($data){
                $data = array_merge($this->data, $data);
            }else{
                $data = $this->data;
            }

            $content = Helper::readFile($realpath);
            $content = $this->pluginManager->callSystemPlugins($content, array(
                'method' => $method ? $method : __METHOD__,
                'path' => $path,
                'realpath' => $realpath,
                'data' => $data
            ));

            return $this->evalContent($data, $content, $path);
        }else{
            throw new \Exception("template [{$path}] is not exists!");
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
        echo $this->fetch($path, $data, __METHOD__);
    }

    //evaluate content
    protected function evalContent($data489bc39ff0, $content489bc39ff0, $path489bc39ff0 = '__anonymous__'){
        ob_start();
        //extract data
        extract($data489bc39ff0);

        //if tmp dir exists, write tmp file and include;
        if($this->tmpDir){
            $filename489bc39ff0 = $this->tmpDir . '/' . str_replace('/', '_', $path489bc39ff0) . uniqid() . '.php';
            file_put_contents($filename489bc39ff0, $content489bc39ff0);
            include $filename489bc39ff0;
            unlink($filename489bc39ff0);
        }else{
            //evaluate code
            eval("?> {$content489bc39ff0}");
        }

        return ob_get_clean();
    }

    public static function sendHeader($charset, $type){
        !headers_sent() && header("Content-type: {$type}; charset={$charset}");
    }
}