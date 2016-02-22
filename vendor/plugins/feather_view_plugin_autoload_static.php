<?php
/*
自动加载动态资源插件
*/
class Feather_View_Plugin_Autoload_Static extends Feather_View_Plugin_Abstract{
	private $map = array();	//缓存检查的map
	private $mapSources = array();	//map来源
	private $commonMap;	//common map
	private $useRequire;
	private $mapLoaded = array();	//缓存map source
	private $domain;
	private $caching;
	private $combo;
	private $cache;
	private $pkgUrlCache = array();
	private static $RESOURCES_TYPE = array('headJs', 'bottomJs', 'css');
	private static $CONCATS_TYPE = array('headJs', 'bottomJs', 'css', 'asyncs', 'deps');

	protected function initialize(){
		if($domain = $this->getOption('domain')){
			$this->domain = rtrim($domain, '/');
		}else{
			$this->domain = '';
		}

		$this->caching = $this->getOption('caching');
		$this->combo = $this->getOption('combo');
		$this->initMapSources();
	}

	private function initMapSources(){
		$sources = $this->getOption('maps');

		if(empty($sources) && !empty($this->view->template_dir)){
			$sources = array();

			foreach((array)$this->view->template_dir as $dir){
				if(file_exists("{$dir}/map")){
					$sources = array_merge($sources, glob("{$dir}/map/**.php"));
				}
			}
		}

		$this->mapSources = (array)$sources;
	}

	//获取map表最大修改时间
	private function getMaxMapModifyTime(){
		$maxTime = 0;

		foreach($this->mapSources as $file){
			clearstatcache($file);
			$lastModifyTime = filemtime($file);

			if($lastModifyTime > $maxTime){
				$maxTime = $lastModifyTime;
			}
		}

		return $maxTime;
	}

	private function initSelfMap($path){
		//if path can be find in map
		if(isset($this->map[$path])){
			return true;
		}

		$foundCommon = !empty($this->commonMap);
		$foundPath = false;

		//合并map表
		foreach($this->mapSources as $file){
			if(isset($this->mapLoaded[$file])){
				continue;
			}

			$info = require($file);
			$map = $info['map'];

			if(isset($info['commonMap'])){
				if(!$foundCommon){
					$this->commonMap = $info['commonMap'];
					$this->useRequire = $info['useRequire'];
					$foundCommon = true;
				}
				
				$this->map = array_merge($this->map, $map);

				if(!$foundPath && isset($map[$path])){
					$foundPath = true;
				}

				$this->mapLoaded[$file] = 1;
			}else{
				if(!$foundPath && isset($map[$path])){
					$this->map = array_merge($this->map, $map);
					$foundPath = true;

					$this->mapLoaded[$file] = 1;
				}
			}

			if($foundPath && $foundCommon){
				break;
			}
		}
	}

	//获取页面所有的静态资源
	private function getSelfMap($path){
		$selfMap = isset($this->map[$path]) ? $this->map[$path] : array();

		if(isset($selfMap['refs'])){
			$refsMap = array();

			foreach($selfMap['refs'] as $ref){
				$refMap = $this->getSelfMap($ref);

				//去掉其他的数据
				foreach($refMap as $key => $value){
					if(array_search($key, self::$CONCATS_TYPE) === false){
						unset($refMap[$key]);
					}
				}

				$refsMap = array_merge_recursive($refsMap, $refMap);
			}

			return array_merge_recursive($refsMap, $selfMap);
		}

		return $selfMap;
	}

	private function getSelfResources($path){
		$maps = $this->map;
		$selfMap = $this->getSelfMap($path);

		//如果不是pagelet，并且asyncs和deps不为空时，说明是一个正常，并且需要使用通用js
		if(!isset($selfMap['isPagelet'])){
			if(!empty($selfMap['asyncs']) || !empty($selfMap['deps'])){
				$selfMap = array_merge_recursive($this->commonMap, $selfMap);
			}
		}

		$tmpCss = array();
		$finalResources = array();
		$finalRequires = array();
		$requires = array();

		foreach(self::$RESOURCES_TYPE as $type){
			if(isset($selfMap[$type])){
				$ms = $selfMap[$type];
				$tmp = $this->getUrl($ms, true, true);

				if($type != 'css'){
					$final = array();

					foreach($tmp as $k => $v){
						if(strrchr($v, '.') == '.css'){
							$tmpCss[$k] = $v;
						}else{
							$final[$k] = $v;
						}
					}

					$finalResources[$type] = $final;
				}else{
					$finalResources[$type] = $tmp;
				}
			}else{
				$finalResources[$type] = array();
			}
		}

		$finalResources['css'] = array_merge($finalResources['css'], $tmpCss);

		if(!empty($selfMap['asyncs'])){
			$requires = $selfMap['asyncs'];
			$finalRequires = $this->getUrl($requires, true);
		}

		//get require info
		$finalMap = array();
		$finalDeps = array();

		foreach($finalRequires as $key => $value){
			if(!isset($finalMap[$value])){
				$finalMap[$value] = array();
			}

			$finalMap[$value][] = $key;

			if(isset($maps[$key])){
				$info = $maps[$key];

				if(isset($info['deps'])){
					$finalDeps[$key] = $info['deps'];
				}
			}
		}

		foreach($finalMap as $k => &$v){
			$v = array_values(array_unique($v));
		}

		unset($v);

		//get real url
		foreach($finalResources as &$resources){
			//do combo
			if(!empty($this->combo) && !empty($resources)){
				$combos = array();
				$remotes = array();

				foreach($resources as $id => $url){
					if(isset($maps[$id])){
						if(empty($this->combo['level']) && array_search($url, $this->pkgUrlCache) === false || !empty($this->combo['level'])){
							$combos[] = $url;
						}else{
							$remotes[] = $url;
						}
					}else{
						$remotes[] = $url;
					}
				}

				$resources = $remotes;

				//if same baseurl concat
				$needSameBaseUrl = !empty($this->combo['sameBaseUrl']);
				$combosDirGroup = array();

				foreach($combos as $url){
					if($needSameBaseUrl){
						$baseurl = dirname($url) . '/';
					}else{
						preg_match('#^(?:(?:https?:)?//)?[^/]+/#', $url, $data);
						$baseurl = $data[0];
					}

					$combosDirGroup[$baseurl][] = $url;
				}

				foreach($combosDirGroup as $dir => $urls){
					$urls = array_unique($urls);
					
					if(count($urls) > 1){
						$baseNames = array();
						$len = strlen($dir);

						foreach($urls as $url){
							$baseNames[] = substr($url, $len);
						}

						$resources[] = $dir . '??' . implode(',', $baseNames); 
					}else{
						$resources[] = $urls[0];
					}	
				}	
			}else{
				$resources = array_unique(array_values($resources));
			}
		}

		unset($resources);
		//end
		
		$finalResources['requires'] = array(
			'map' => $finalMap,
			'deps' => $finalDeps
		);

		return $finalResources;
	}

	private function getUrl($resources, $returnHash = false, $includeNotFound = false, &$hash = array(), &$pkgHash = array()){
		$urls = array();
		$maps = $this->map;

		foreach($resources as $v){
			//如果存在
			if(isset($maps[$v])){
				$info = $maps[$v];
				//如果未查找过
				if(!isset($hash[$v])){
					//如果pack
					if(isset($info['pkg'])){
						$name = $info['pkg'];
						
						//如果pkg未查找过
						if(!isset($pkgHash[$name])){
							$pkg = $maps[$name];
							//缓存
							$url = $hash[$v] = $pkgHash[$name] = $pkg['url'];
						}else{
							$url = $hash[$v] = $pkgHash[$name];
							if(array_search($url, $this->pkgUrlCache) === false){
								$this->pkgUrlCache[] = $url;
							}
						}
					}else{
						$url = $hash[$v] = $info['url'];
					}

					//如果自己有deps，没打包，直接加载依赖
					if(isset($info['deps'])){
						$urls = array_merge($urls, $this->getUrl($info['deps'], $returnHash, $includeNotFound, $hash, $pkgHash));
					}

					if(isset($info['async'])){
						$urls = array_merge($urls, $this->getUrl($info['async'], $returnHash, $includeNotFound, $hash, $pkgHash));
					}	
				}else{
					$url = $hash[$v];
				}
			}else{
				$url = $v;

				if($includeNotFound){
					$hash[$v] = $v;
				}
			}
			
			$urls[] = $url;
		}

		return !$returnHash ? array_unique($urls) : $hash;
	}

	private function getCache(){
		if(!$this->cache){
			$cache = $this->getOption('cache');

			if(!$cache){
				//默认使用file 缓存
				Feather_View_Loader::import('Feather_View_Plugin_Cache_File.class.php');

			    $this->cache = new Feather_View_Plugin_Cache_File(array(
			        'cache_dir' => $this->getOption('cache_dir')
			    ));
			}else if(is_object($cache) && is_a($cache, 'Feather_View_Plugin_Cache_Abstract')){
				$this->cache = $cache;
			}else{
				$this->cache = new $cache;
			}
		}

		return $this->cache;
	}

	//执行主程
	public function exec($content, $info){
		if($info['isLoad']) return $content;

		$view = $this->view;
		$view->set('FEATHER_STATIC_DOMAIN', $this->domain);

		$lastModifyTime = $this->getMaxMapModifyTime();

		$path = ltrim($info['path'], '/');
		$rpath = md5($content) . $path;
		$cache = $this->caching ? $this->getCache()->read($rpath) : null;

		if(!$cache 
			|| !isset($cache['DOMAIN'])
			|| $this->domain != $cache['DOMAIN'] 
			|| $lastModifyTime > $cache['MAX_LAST_MODIFY_TIME']
		){
			$this->initSelfMap($path);
			$resources = $this->getSelfResources($path);

			//拿到当前文件所有的map信息
			$headJsInline = array();

			if(!empty($resources['requires']) && !empty($resources['requires']['map']) && $this->useRequire){
				$headJsInline[] = '<script>require.mergeConfig(' . self::jsonEncode($resources['requires']) . ')</script>';
			}
		
			$cache = array(
				'FEATHER_USE_HEAD_SCRIPTS' => $resources['headJs'],
				'FEATHER_USE_HEAD_INLINE_SCRIPTS' => $headJsInline,
		        'FEATHER_USE_SCRIPTS' => $resources['bottomJs'],
				'FEATHER_USE_STYLES' => $resources['css'],
				'MAX_LAST_MODIFY_TIME' => $lastModifyTime,
				'DOMAIN' => $this->domain,
				'FILE_PATH' => $info['path']
			);


			//如果需要设置缓存
			$this->caching && $this->getCache()->write($rpath, $cache);
		}

		//设置模版值
		$view->set($cache);

		return $content;
	}

	private static function jsonEncode($v){
    	return str_replace('\\', '', json_encode($v));
	}

	private static function isRemoteUrl($url){
		return !!preg_match('#^//|://#', $url);
	}
}