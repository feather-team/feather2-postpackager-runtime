<?php
namespace FeatherResource;

class Helper{
    public static function mkdir($dir, $mod = 0777){
        if(is_dir($dir)){
            return true;
        }else{
            $old = umask(0);

            if(@mkdir($dir, $mod, true) && is_dir($dir)){
                umask($old);
                return true;
            } else {
                umask($old);
            }
        }

        return false;
    }

    public static function readFile($file){
        if(is_file($file)){
            return file_get_contents($file);
        }

        return false;
    }

    public static function writeFile($file, $content){
        self::mkdir(dirname($file));
        file_put_contents($file, $content);
    }

    public static function get($data, $name, $default = null){
        return isset($data[$name]) ? $data[$name] : $default;
    }

    public static function jsonEncode($data){
        return str_replace('\\', '', json_encode($data));
    }
}

final class Maps{
    private $options = array();
    private $map = array(); //all map info
    private $mapFiles = array();  //all map files
    private $loadedMapFiles = array(); //some map files already loaded
    private $commonMap; //common map
    private $useRequire;
    private $combo;
    private $urlCache = array();
    private $pageletCss = array();
    private $templateDir;
    private $cacheDir;
    private $mapFilesMaxModifyTime;
    private static $RESOURCES_TYPE = array('headJs', 'bottomJs', 'css');
    private static $CONCATS_TYPE = array('headJs', 'bottomJs', 'css', 'asyncs', 'deps');   
    const COMBO_MAX_URL_LENGTH = 2000;

    public function __construct($templateDir, $options = array()){
        $this->templateDir = rtrim($templateDir, '/') . '/';
        $this->options = $options;

        $combo = Helper::get($options, 'combo');

        if($combo && Helper::get($combo, 'use', false)){
            $this->combo = $combo;
        }

        if($cacheDir = Helper::get($options, 'cacheDir')){
            $this->cacheDir = rtrim($cacheDir, '/') . '/';
        }

        //collect map files;
        $this->collectMapFiles();
    }

    private function collectMapFiles(){
        $mapDir = $this->templateDir . 'map';
        $files = array();

        if(is_dir($mapDir)){
            clearstatcache();
            $files = (array)glob("{$mapDir}/**.php");

            //if files's count eq 1, remove map.php
            if(count($files) > 1){
                foreach($files as $key => $file){
                    if(preg_match('#/map.php$#', $file)){
                        unset($files[$key]);
                        break;
                    }
                }
            }

            $this->mapFiles = $files;
        }
    }

    private function loadMapFile($id){
        //if id can be found in map
        if(isset($this->map[$id])){
            return true;
        }

        $found = false;
        
        //合并map表
        foreach($this->mapFiles as $key => $file){
            if(isset($this->loadedMapFiles[$file])) continue;

            $info = require($file);
            $map = $info['map'];

            if($commonMap = Helper::get($info, 'commonMap')){
                if(!$this->commonMap){
                    $this->commonMap = $commonMap;
                    $this->useRequire = $info['useRequire'];
                }

                $this->map = array_merge($this->map, $map);
                //remove map file after require current file;
                $this->loadedMapFiles[$file] = true;

                if(!$found && isset($map[$id])){
                    $found = true;
                }
            }else if(!$found && isset($map[$id])){
                $this->map = array_merge($this->map, $map);
                //remove map file after require current file;
                $this->loadedMapFiles[$file] = true;
                $found = true;
            }
                
            if($found && $this->commonMap){
                break;
            }
        }
    }

    //获取页面所有的静态资源
    private function getMapInfo($id){
        $map = Helper::get($this->map, $id, array());

        if($refs = Helper::get($map, 'refs')){
            $refsMap = array();

            foreach($refs as $ref){
                $refMap = $this->getMapInfo($ref);

                //去掉其他的数据
                foreach($refMap as $key => $value){
                    if(array_search($key, self::$CONCATS_TYPE) === false){
                        unset($refMap[$key]);
                    }
                }

                $refsMap = array_merge_recursive($refsMap, $refMap);
            }

            return array_merge_recursive($refsMap, $map);
        }

        return $map;
    }

    private function getUrls($resources, $returnHash = false, $includeNotFound = false, &$founds = array(), &$pkgFounds = array()){
        $urls = array();

        foreach($resources as $resource){
            if(!isset($founds[$resource]) && ($info = Helper::get($this->map, $resource))){
                $pkgInfo = null;

                //if pack
                if($pkgName = Helper::get($info, 'pkg')){
                    $url = Helper::get($pkgFounds, $pkgName);
    
                    //if pkg exists but not in pkgFounds
                    if(!$url){
                        $pkgInfo = $this->map[$pkgName];
                        //cache pack info
                        $url = $pkgFounds[$pkgName] = $pkg['url'];
                        $this->urlCache[$url] = $pkgInfo;
                    }
                }else{
                    $url = $info['url'];
                    $this->urlCache[$url] = $info;
                }

                //store id
                $founds[$resource] = $url;

                //anaylse self deps
                if($deps = Helper::get($info, 'deps')){
                    $urls = array_merge($this->getUrls($deps, false, $includeNotFound, $founds, $pkgFounds), $urls);
                }

                //if asyncs, analyse asyncs
                if($asyncs = Helper::get($info, 'asyncs')){
                    $urls = array_merge($this->getUrls($asyncs, false, $includeNotFound, $founds, $pkgFounds), $urls);
                }

                //Requrie all files to prevent call error when all files in pkg don't use jswraper 
                if(isset($pkgInfo) && (isset($pkg['useJsWraper']) || !$this->useRequire)){
                    $noWraperHas = array();

                    foreach($pkgInfo['has'] as $has){
                        //Only analyse which is not analysed
                        if($has = Helper::get($founds, $has)){
                            $noWraperHas = $has;
                        }
                    }

                    if(!empty($noWraperHas)){
                        $urls = array_merge($this->getUrl($noWraperHas, false, $includeNotFound, $founds, $pkgFounds), $urls);
                    }
                }
            }else{
                $url = $resource;

                if($includeNotFound){
                    $founds[$resource] = $resource;
                }
            }

            $urls[] = $url;
        }

        return !$returnHash ? array_unique($urls) : $founds;
    }

    private function getThreeUrls($mapInfo){
        $inJsCss = array();
        $allUrls = array();

        foreach(self::$RESOURCES_TYPE as $type){
            $resources = Helper::get($mapInfo, $type, array());
            $urls = $this->getUrls($resources, false, true);

            if($type != 'css'){
                foreach($urls as $key => $url){
                    if(strrchr($url, '.') == '.css'){
                        $inJsCss[] = $url;
                        unset($urls[$key]);
                    }
                }
            }

            $allUrls[$type] = $urls;
        }

        $allUrls['css'] = array_merge($allUrls['css'], $inJsCss);

        //unique array, and do combo
        $comboCssOnlySameBase = Helper::get($this->combo, 'cssOnlySameBase', false);
        $comboOnlyUnPackFile = Helper::get($this->combo, 'onlyUnPackFile', false);
        $comboMaxUrlLength = Helper::get($this->combo, 'maxUrlLength', self::COMBO_MAX_URL_LENGTH);

        foreach($allUrls as $type => $urls){
            $urls = $allUrls[$type] = array_unique($urls);

            if(!$this->combo){
                continue;
            }

            $finalUrls = array();

            foreach($urls as $url){
                if($info = Helper::get($this->urlCache, $url)){
                    if($comboOnlyUnPackFile && !isset($info['isPkg']) || !$comboOnlyUnPackFile){
                        $combos[] = $url;
                    }else{
                        $finalUrls[] = $url;
                    }
                }else{
                    $finalUrls[] = $url;
                }
            }

            $combosDirGroup = array();

            foreach($combos as $url){
                if($this->urlCache[$url]['type'] == 'css' && $comboCssOnlySameBase){
                    $baseurl = dirname($url) . '/';
                }else{
                    preg_match('#^(?:(?:https?:)?//)?[^/]+/#', $url, $data);
                    $baseurl = $data[0];
                }

                $combosDirGroup[$baseurl][] = $url;
            }

            foreach($combosDirGroup as $dir => $urls){
                if(count($urls) > 1){
                    $baseNames = array();
                    $dirLength = strlen($dir);
                    $len = 0;

                    foreach($urls as $url){
                        $url = substr($url, $dirLength);
                        $baseNames[] = $url;

                        if(strlen($url) + $len >= $comboMaxUrlLength){
                            $len = 0;
                            $resources[] = $dir . '??' . implode(',', $baseNames); 
                            $baseNames = array();
                        }else{
                            $len += strlen($url);
                        }
                    }

                    if(count($baseNames)){
                        $finalUrls[] = $dir . '??' . implode(',', $baseNames); 
                    }
                }else{
                    $finalUrls[] = $urls[0];
                } 
            }

            $allUrls[$type] = $finalUrls;
        }

        return $allUrls;
    }

    private function getRequireInfo($mapInfo){
        $requireInfo = $this->getUrls(Helper::get($mapInfo, 'asyncs', array()), true);
        $requireMaps = array();
        $requireDeps = array();

        foreach($requireInfo as $id => $url){
            $requireMaps[$url][] = $id;
            $info = $this->map[$id];

            if($deps = Helper::get($info, 'deps')){
                $requireDeps[$id] = $deps;
            }
        }

        foreach($requireMaps as $url => $ids){
            $requireMaps[$url] = array_values(array_unique($ids));
        }

        $result = array(
            'deps' => $requireDeps,
            'map' => $requireMaps
        );

        if($this->combo){
            $result['combo'] = $this->combo;
        }

        return $result;
    }

    public function getResourcesInfo($id){
        $this->loadMapFile($id);
        $mapInfo = $this->getMapInfo($id);

        //如果不是pagelet，并且asyncs和deps不为空时，说明是一个正常，并且需要使用通用js
        // if(isset($selfMap['isPagelet'])){
        //     if(empty($selfMap['asyncs'])){
        //         $selfMap['asyncs'] = array();
        //     }

        //     if(isset($selfMap['css'])){
        //         if(empty($selfMap['asyncs'])){
        //             $selfMap['asyncs'] = $selfMap['css'];
        //         }else{
        //             $selfMap['asyncs'] = array_merge($selfMap['css'], $selfMap['asyncs']);
        //         }
                
        //         $finalResources['pageletCss'] = $selfMap['css'];
        //         unset($selfMap['css']);
        //     }
        // }else{
        //     //if(!empty($selfMap['asyncs']) || !empty($selfMap['deps'])){
        //     if($this->useRequire){
        //         $selfMap = array_merge_recursive($this->commonMap, $selfMap);
        //     }
        // }

        if($this->useRequire){
            $mapInfo = array_merge_recursive($this->commonMap, $mapInfo);
        }

        $threeUrls = $this->getThreeUrls($mapInfo);
        $requireInfo = $this->getRequireInfo($mapInfo);

        return array(
            'threeUrls' => $threeUrls,
            'requires' => $requireInfo
        );
    }

    public function getResourcesData($id){
        $realpath = $this->templateDir . $id;

        if($data = $this->cache($realpath)){
            return $data;
        }

        $info = $this->getResourcesInfo($id);

        if(!empty($info['requires']) && !empty($info['requires']['map']) && $this->useRequire){
            $headJsInline[] = '<script>require.config(' . Helper::jsonEncode($info['requires']) . ')</script>';
        }

        $data = array(
            'FEATHER_USE_HEAD_SCRIPTS' => $info['threeUrls']['headJs'],
            'FEATHER_USE_HEAD_INLINE_SCRIPTS' => $headJsInline,
            'FEATHER_USE_SCRIPTS' => $info['threeUrls']['bottomJs'],
            'FEATHER_USE_STYLES' => $info['threeUrls']['css'],
            'FILE_PATH' => $realpath
        );

        $this->cache($realpath, $data);
        return $data;
    }

    private function getMapFilesMaxModifyTime(){
        if(!$this->mapFilesMaxModifyTime){
            $this->mapFilesMaxModifyTime = 0;

            foreach($this->mapFiles as $file){
                clearstatcache();
                $lastModifyTime = filemtime($file);

                if($lastModifyTime > $this->mapFilesMaxModifyTime){
                    $this->mapFilesMaxModifyTime = $lastModifyTime;
                }
            }
        }

        return $this->mapFilesMaxModifyTime;
    }

    private function cache($file, $data = null){
        if(!$this->cacheDir) return false;

        $cachePath = $this->cacheDir . md5($file) . '.cache';

        if(!$data){
            if($content = Helper::readFile($cachePath)){
                $data = unserialize($content);

                if($data && $data['MAP_FILES_MAX_LAST_MODIFY_TIME'] == $this->getMapFilesMaxModifyTime()){
                    return $data;
                }
            }

            return false;
        }else{
            $data['MAP_FILES_MAX_LAST_MODIFY_TIME'] = $this->getMapFilesMaxModifyTime();
            return Helper::writeFile($cachePath, serialize($data));
        }
    }
}