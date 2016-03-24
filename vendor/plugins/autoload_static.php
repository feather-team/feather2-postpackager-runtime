<?php
namespace FeatherView\Plugin;
use FeatherView;

/*
自动加载动态资源插件
*/
class AutoloadStatic extends SystemPluginAbstract{
	private $map = array();	//缓存检查的map
	private $mapSources = array();	//map来源
	private $commonMap;	//common map
	private $useRequire;
	private $mapLoaded = array();	//缓存map source
	private $domain;
	private $combo;
	private $urlCache = array();
	private $pkgUrlCache = array();
	private $pageletCss = array();
	private static $RESOURCES_TYPE = array('headJs', 'bottomJs', 'css');
	private static $CONCATS_TYPE = array('headJs', 'bottomJs', 'css', 'asyncs', 'deps');

	protected function initialize(){
		if($domain = $this->getOption('domain')){
			$this->domain = rtrim($domain, '/');
		}else{
			$this->domain = '';
		}

		$this->combo = $this->getOption('combo');
		$this->initMapSources();
	}

	private function initMapSources(){
		$sources = array();
		$dirs = $this->view->getTemplateDir();

		if(!empty($dirs)){
			$sources = array();

			foreach((array)$this->view->getTemplateDir() as $dir){
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

		$tmpCss = array();
		$finalResources = array();
		$finalRequires = array();
		$requires = array();

		//如果不是pagelet，并且asyncs和deps不为空时，说明是一个正常，并且需要使用通用js
		if(isset($selfMap['isPagelet'])){
			if(empty($selfMap['asyncs'])){
				$selfMap['asyncs'] = array();
			}

			if(isset($selfMap['css'])){
				if(empty($selfMap['asyncs'])){
					$selfMap['asyncs'] = $selfMap['css'];
				}else{
					$selfMap['asyncs'] = array_merge($selfMap['css'], $selfMap['asyncs']);
				}
				
				$finalResources['pageletCss'] = $selfMap['css'];
				unset($selfMap['css']);
			}
		}else{
			//if(!empty($selfMap['asyncs']) || !empty($selfMap['deps'])){
			if($this->useRequire){
				$selfMap = array_merge_recursive($this->commonMap, $selfMap);
			}
		}

		foreach(self::$RESOURCES_TYPE as $type){
			if(isset($selfMap[$type])){
				$ms = $selfMap[$type];
				$tmp = $this->getUrl($ms, false, true);

				if($type != 'css'){
					$final = array();

					foreach($tmp as $v){
						if(strrchr($v, '.') == '.css'){
							$tmpCss[] = $v;
						}else{
							$final[] = $v;
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
		$comboOption = $this->getOption('combo');
		$comboCssOnlySameBase = $comboOption['cssOnlySameBase'];
		$comboLevel = $comboOption['level'];
		$comboMaxUrlLength = $comboOption['maxUrlLength'];

		foreach($finalResources as &$resources){
			//do comboLevel
			if($comboLevel > -1 && !empty($resources)){
				$combos = array();
				$remotes = array();

				foreach($resources as $url){
					if(isset($this->urlCache[$url])){
						if($comboLevel == 0 && !isset($this->pkgUrlCache[$url]) || $comboLevel > 0){
							$combos[] = $url;
						}else{
							$remotes[] = $url;
						}
					}else{
						$remotes[] = $url;
					}
				}

				$resources = $remotes;
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
					$urls = array_unique($urls);
					
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
							$resources[] = $dir . '??' . implode(',', $baseNames); 
						}
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
		
		$requires = array(
			'map' => $finalMap,
			'deps' => $finalDeps
		);

		if($comboLevel > -1){
			$requires['combo'] = $comboOption;
		}

		$finalResources['requires'] = $requires;

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
							$this->pkgUrlCache[$pkgHash[$name] = $pkg['url']] = $name;
							$this->urlCache[$pkg['url']] = $pkg;
						}

						$url = $hash[$v] = $pkgHash[$name];
					}else{
						$url = $hash[$v] = $info['url'];
						$this->urlCache[$url] = $info;
					}

					//如果自己有deps，没打包，直接加载依赖
					if(isset($info['deps'])){
						$urls = array_merge($this->getUrl($info['deps'], $returnHash, $includeNotFound, $hash, $pkgHash), $urls);
					}

					if(isset($info['asyncs'])){
						$urls = array_merge($this->getUrl($info['asyncs'], $returnHash, $includeNotFound, $hash, $pkgHash), $urls);
					}	

					//这边需要做处理的，pkg中所有的文件都没有使用jswraper时，为了避免其他文件报错，则避免引入所有文件的依赖。
					if(isset($pkg) && (isset($pkg['useJsWraper']) || !$this->useRequire)){
						$noWraperHas = array();

						foreach($pkg['has'] as $has){
							//只分析，当前没有分析过的文件
							if(!isset($hash[$has])){
								$noWraperHas[] = $has;
							}
						}

						if(!empty($noWraperHas)){
							$urls = array_merge($this->getUrl($noWraperHas, $returnHash, $includeNotFound, $hash, $pkgHash), $urls);
						}
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

	//执行主程
	public function exec($content, $info){
		if($info['method'] == 'load') return $content;

		$view = $this->view;
		$cacheDir = $this->view->getTempDir();

		if(!$cacheDir){
			throw new \Exception('AutoStatic Load Plugin need view engine\'s tempDir as cacheDir, Please set view engine\'s tempDir first!');
		}

		if(isset($this->domain)){
			$view->set('FEATHER_STATIC_DOMAIN', $this->domain);
		}

		$lastModifyTime = $this->getMaxMapModifyTime();
		$cacheFileName = md5($info['realpath']);
		$cache = null;

		$cacheDir = $this->view->getTempDir();
		$cachePath = $cacheDir . '/' . $cacheFileName;

		if($cache = FeatherView\Helper::readFile($cachePath)){
			$cache = unserialize($cache);
		}

		if(!$cache 
			|| !isset($cache['DOMAIN'])
			|| $this->domain != $cache['DOMAIN'] 
			|| $lastModifyTime > $cache['MAX_LAST_MODIFY_TIME']
		){
			$path = ltrim($info['path'], '/');
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

			if(isset($resources['pageletCss'])){
				$cache['FEATHER_PAGELET_CSS_JSON'] = self::jsonEncode($resources['pageletCss']);
			}

			FeatherView\Helper::writeFile($cachePath, serialize($cache));
		}

		//设置模版值
		$view->set($cache);
		$view->set('FEATHER_HEAD_RESOURCE_LOADED', false);

		return $content;
	}

	private static function jsonEncode($v){
    	return str_replace('\\', '', json_encode($v));
	}

	private static function isRemoteUrl($url){
		return !!preg_match('#^//|://#', $url);
	}
}