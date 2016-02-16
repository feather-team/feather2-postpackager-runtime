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
		$sources = $this->getOption('map');

		if(empty($sources)){
			$sources = $this->getOption('maps');

			if(empty($sources)){
				//兼容
				$sources = $this->getOption('resources');
			}	
		}

		if(empty($sources) && !empty($this->view->template_dir)){
			$sources = array();

			foreach((array)$this->view->template_dir as $dir){
				if(file_exists("{$dir}/map")){
					$sources = array_merge($sources, glob("{$dir}/map/**.php"));
				}else{
					$sources = array_merge($sources, glob("{$dir}/../map/**.php"));
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

		if(isset($selfMap['widget'])){
			$widgetMap = array();

			foreach($selfMap['widget'] as $widget){
				$widgetMap = array_merge_recursive($widgetMap, $this->getSelfMap($widget));
			}

			return array_merge_recursive($widgetMap, $selfMap);
		}

		return $selfMap;
	}

	private function getSelfResources($path){
		$maps = $this->map;
		$selfMap = $this->getSelfMap($path);

		if(!isset($selfMap['isPagelet']) && !empty($selfMap['async'])){
			$selfMap = array_merge_recursive($this->commonMap, $selfMap);
		}

		$tmpCss = array();
		$finalResources = array();
		$finalRequires = array();
		$requires = array();

		foreach(self::$RESOURCES_TYPE as $type){
			if(isset($selfMap[$type])){
				$ms = $selfMap[$type];
				$tmp = $this->getUrl($ms, false);

				if($type != 'css'){
					$final = array();

					foreach($tmp as $v){
						if(strrchr($v, '.') == '.css'){
							array_push($tmpCss, $v);
						}else{
							array_push($final, $v);
						}
					}

					$finalResources[$type] = $final;
				}else{
					$finalResources[$type] = array_merge($tmp, $tmpCss);
				}
			}else{
				$finalResources[$type] = array();
			}
		}

		if(isset($selfMap['async'])){
			$requires = array_merge($requires, $selfMap['async']);
		}

		if(!empty($requires)){
			$finalRequires = $this->getUrl($requires, false, true);
		}

		//get require info
		$finalMap = array();
		$finalDeps = array();

		foreach($finalRequires as $key => $value){
			if(!array_search($key, $requires) 
				&& strrchr($key, '.') == '.css' 
				&& isset($maps[$key]) 
				&& (!isset($maps[$key]['isMod']) || isset($maps[$key]['isComponent']))
			){
				array_push($finalResources['css'], $value);
				continue;
			}

			if(!isset($finalMap[$value])){
				$finalMap[$value] = array();
			}

			$finalMap[$value][] = $key;

			if(isset($maps[$key])){
				$info = $maps[$key];

				if(isset($info['deps']) && isset($info['isMod'])){
					if(isset($info['isComponent'])){
						$deps = array();

						foreach($info['deps'] as $dep){
							if(strrchr($dep, '.') != '.css'){
								$deps[] = $dep;
							}
						}

						if(!empty($deps)){
							$finalDeps[$key] = $deps;
						}
					}else{
						$finalDeps[$key] = $info['deps'];
					}
				}
			}
		}

		foreach($finalMap as $k => &$v){
			$v = array_values(array_unique($v));
		}

		unset($v);

		//get real url
		foreach($finalResources as &$resources){
			$resources = array_unique($resources);

			//do combo
			if(!empty($this->combo) && !empty($resources)){
				$combos = array();
				$remotes = array();

				foreach($resources as $v){
					if(!self::isRemoteUrl($v)){
						if(empty($this->combo['level']) && array_search($v, $this->pkgUrlCache) === false || !empty($this->combo['level'])){
							$combos[] = $v;
						}else{
							$remotes[] = $v;
						}
					}else{
						$remotes[] = $v;
					}
				}

				$resources = $remotes;

				//if same baseurl concat
				if(!empty($this->combo['sameBaseUrl'])){
					$combosDirGroup = array();

					foreach($combos as $url){
						$baseurl = dirname($url);
						$combosDirGroup[$baseurl][] = $url;
					}

					foreach($combosDirGroup as $dir => $urls){
						if(count($urls) > 1){
							$baseNames = array();

							foreach($urls as $url){
								$baseNames[] = basename($url);
							}

							$resources[] = (!empty($this->combo['domain']) ? $this->combo['domain'] : $this->domain) . $dir . '??' . implode(',', $baseNames); 
						}else{
							$resources[] = $this->domain . $urls[0];
						}	
					}	
				}else{
					if(count($combos) > 1){
						$combos = (!empty($this->combo['domain']) ? $this->combo['domain'] : $this->domain) . '/??' . implode(',', $combos); 
						$resources[] = $combos;
					}else{
						foreach($combos as $v){
							$resources[] = $this->domain . $v;		
						}
					}		
				}
			}else{
				// foreach($resources as &$v){
				// 	if(!self::isRemoteUrl($v)){
				// 		$v = $this->domain . $v;
				// 	}
				// }

				// unset($v);
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

	private function getUrl($resources, $withDomain = true, $returnHash = false, &$hash = array(), &$pkgHash = array()){
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
							$url = $hash[$v] = $pkgHash[$name] = $withDomain ? $this->domain . $pkg['url'] : $pkg['url'];
							//如果pkg有deps，并且不是mod，说明多个非mod文件合并，需要同时加载他们中所有的文件依赖，防止页面报错
							if(!isset($info['isMod'])){
								if(isset($pkg['deps'])){
									$urls = array_merge($urls, $this->getUrl($pkg['deps'], $withDomain, $returnHash, $hash, $pkgHash));
								}
								
								if(isset($pkg['async'])){
									$urls = array_merge($urls, $this->getUrl($pkg['async'], $withDomain, $returnHash, $hash, $pkgHash));
								}
							}
						}else{
							$url = $hash[$v] = $pkgHash[$name];
							if(array_search($url, $this->pkgUrlCache) === false){
								$this->pkgUrlCache[] = $url;
							}
						}
						//如果自己有deps，并且是mod，则可以不通过pkg加载依赖，只需要加载自己的依赖就可以了，mod为延迟加载。
						if(isset($info['isMod'])){
							if(isset($info['deps'])){
								$urls = array_merge($urls, $this->getUrl($info['deps'], $withDomain, $returnHash, $hash, $pkgHash));
							}

							if(isset($info['async'])){
								$urls = array_merge($urls, $this->getUrl($info['async'], $withDomain, $returnHash, $hash, $pkgHash));
							}	
						}
					}else{
						$url = $hash[$v] = $withDomain ? $this->domain . $info['url'] : $info['url'];
						//如果自己有deps，没打包，直接加载依赖
						if(isset($info['deps'])){
							$urls = array_merge($urls, $this->getUrl($info['deps'], $withDomain, $returnHash, $hash, $pkgHash));
						}

						if(isset($info['async'])){
							$urls = array_merge($urls, $this->getUrl($info['async'], $withDomain, $returnHash, $hash, $pkgHash));
						}	
					}
				}else{
					$url = $hash[$v];
				}
			}else{
				$url = $v;
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
				$config = $resources['requires'];
				$config['domain'] = $this->domain;
				$headJsInline[] = '<script>require.mergeConfig(' . self::jsonEncode($config) . ')</script>';
			}
		
			$cache = array(
				'FEATHER_USE_HEAD_SCRIPTS' => $resources['headJs'],
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
