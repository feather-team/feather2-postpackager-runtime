<?php
error_reporting(E_ALL);
ini_set('display_errors', 'On');

define('ROOT', __DIR__);

$ns = load(ROOT . '/c_proj', true);

define('PROJ_PATH', ROOT . '/../proj/' . $ns);
define('LIB_PATH', PROJ_PATH . '/lib');
define('STATIC_PATH', PROJ_PATH . '/static/');
define('TMP_PATH', PROJ_PATH . '/tmp/');
define('VIEW_PATH', PROJ_PATH . '/view/');
define('TEST_PATH', PROJ_PATH . '/test/');
define('CACHE_PATH', PROJ_PATH . '/cache/');

$conf = load(PROJ_PATH . '/conf.php', true);
$conf = json_decode($conf, true);

if(empty($conf)){
    throw new Exception("project [{$ns}] is not exists！");
}

if(is_dir(TMP_PATH . '/rewrite')){
    $rewriteFiles = scandir(TMP_PATH . '/rewrite/');
}else{
    $rewriteFiles = array();
}

$rewrite = array();

foreach($rewriteFiles as $file){
    if($file == '.' || $file == '..') continue;
    $r = load(TMP_PATH . '/rewrite/' . $file);

    if(!is_array($r)){
        $r = array();
    }

    $rewrite = array_merge($rewrite, $r);
}

$suffix = '.' . $conf['template']['suffix'];
$uri = $_SERVER['REQUEST_URI'];

$comboSplit = explode('??', $uri);

if(!empty($conf['combo']) && count($comboSplit) > 1){
    //is combo
    $tmp_files = explode(',', $comboSplit[1]);
    $content = array();

    foreach($tmp_files as $v){
        $type = getMime($v);
        $content[] = file_get_contents(STATIC_PATH . $comboSplit[0] . '/' . $v);
    }

    header("Content-type: {$type};");
    echo implode("\r\n", $content);
    exit;
}

$path = null;

foreach ($rewrite as $key => $value){
    preg_match($key, $uri, $match);

    if(!empty($match)){
        $value = (array)$value;
        $value = $value[rand(0, count($value) - 1)];
        $path = $value;
        break;  
    }
}

$path = $path ? $path : preg_replace('/[\?#].*/', '', $uri);
$path = explode('/', trim($path, '/'));

if(empty($path[0])) $path = array('index');
if($path[0] == 'page' && empty($path[1])) $path[1] = 'index' . $suffix;

$tmpPath = implode('/', $path);
$s = strrchr($tmpPath, '.');

if($s === false || $s == $suffix){
    require LIB_PATH . '/engine/vendor/autoload.php';

    load(TMP_PATH . '/compatible.php');

    $engineConfig = load(VIEW_PATH . '/engine.config.php');

    if(!$engineConfig){
        $engineConfig = array(
            'templateDir' => VIEW_PATH,
            'suffix' => $suffix,
            'tempDir' => CACHE_PATH,
            'systemPlugins' => array()
        );
    }   

    $engineConfig['systemPlugins']['autoload_static']['combo'] = $conf['combo'];
    $engineConfig['systemPlugins'] = array_merge($engineConfig['systemPlugins'], array(
        'autoload_static' => array(
           'domain' => "http://{$_SERVER['HTTP_HOST']}",
           'combo' => $conf['combo'] 
        ),

        'autoload_test_data' => array(
            'maps' => glob(VIEW_PATH . '/map/**'),
            'data_dir' => TEST_PATH
        ),

        'static_position'
    ));

    //依赖map表测试的版本
    $view = new FeatherView\Engine($engineConfig);

    $path = '/' . preg_replace('/\..+$/', '', implode('/', $path));
    $data = load(TEST_PATH . $path . '.php');

    is_array($data) && $view->set($data);
    $view->display($path);
}else{
    if($path[0] == 'test'){
        require LIB_PATH . '/MagicData.class.php';
        $_path = TEST_PATH . '/' . implode('/', array_slice($path, 1));

        if(!is_file($_path)){
            header("{$_SERVER['SERVER_PROTOCOL']} 404 Not Found");
            exit;
        }

        $output = MagicData::parseByFile($_path);
        $type = getMime($tmpPath);
        header("Content-type: {$type};");
        echo eval("?>{$output}");
        exit;
    }else{
        $_path = STATIC_PATH . '/' . implode('/', $path);

        if(!is_file($_path)){
            header("{$_SERVER['SERVER_PROTOCOL']} 404 Not Found");
            header("status: 404 not found"); 
            exit;
        }

        $output = file_get_contents($_path);
        $type = getMime($tmpPath);
        header("Content-type: {$type};");
        echo $output;
    }
}

//加载一个文件
function load($file, $read = false){
    if(is_file($file)){
        return $read ? file_get_contents($file) : require $file; 
    }
}

function getMime($path){
    $type = array(
        'bmp' => 'image/bmp',
        'css' => 'text/css',
        'doc' => 'application/msword',
        'dtd' => 'text/xml',
        'gif' => 'image/gif',
        'hta' => 'application/hta',
        'htc' => 'text/x-component',
        'htm' => 'text/html',
        'html' => 'text/html',
        'xhtml' => 'text/html',
        'phtml' => 'text/html',
        'ico' => 'image/x-icon',
        'jpe' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'jpg' => 'image/jpeg',
        'js' => 'text/javascript',
        'json' => 'application/json',
        'mocha' => 'text/javascript',
        'mp3' => 'audio/mp3',
        'flv' => 'video/flv',
        'swf' => 'video/swf',
        'mp4' => 'video/mpeg4',
        'mpeg' => 'video/mpg',
        'mpg' => 'video/mpg',
        'manifest' => 'text/cache-manifest',
        'pdf' => 'application/pdf',
        'png' => 'image/png',
        'ppt' => 'application/vnd.ms-powerpoint',
        'rmvb' => 'application/vnd.rn-realmedia-vbr',
        'rm' => 'application/vnd.rn-realmedia',
        'rtf' => 'application/msword',
        'svg' => 'image/svg+xml',
        'swf' => 'application/x-shockwave-flash',
        'tif' => 'image/tiff',
        'tiff' => 'image/tiff',
        'txt' => 'text/plain',
        'vml' => 'text/xml',
        'vxml' => 'text/xml',
        'wav' => 'audio/wav',
        'wma' => 'audio/x-ms-wma',
        'wmv' => 'video/x-ms-wmv',
        'woff' => 'image/woff',
        'xml' => 'text/xml',
        'xls' => 'application/vnd.ms-excel',
        'xq' => 'text/xml',
        'xql' => 'text/xml',
        'xquery' => 'text/xml',
        'xsd' => 'text/xml',
        'xsl' => 'text/xml',
        'xslt' => 'text/xml'
    );

    $info = pathinfo($path);

    if(isset($type[$info['extension']])){
        return $type[$info['extension']];
    }else{
        return 'text/plain';
    }
}
