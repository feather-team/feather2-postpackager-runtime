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
	private $caching;
	private $urlCache = array();
	private $pkgUrlCache = array();
	private static $RESOURCES_TYPE = array('headJs', 'bottomJs', 'css');
	private static $CONCATS_TYPE = array('headJs', 'bottomJs', 'css', 'asyncs', 'deps');

	protected function initialize(){
		if($domain = $this->getOption('domain')){
			$this->domain = rtrim($domain, '/');
		}else{
			$this->domain = '';
		}

		$this->combo = $this->getOption('combo', array());
		$this->caching = $this->getOption('caching', false);
		$this->initMapSources();
	}

	private function initMapSources(){
		$sources = $this->getOption('maps');

		if(empty($sources)){
			$dirs = $this->view->getTemplateDir();

			if(!empty($dirs)){
				$sources = array();

				foreach((array)$this->view->getTemplateDir() as $dir){
					if(file_exists("{$dir}/map")){
						$sources = array_merge($sources, glob("{$dir}/map/**.php"));
					}
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
		foreach($finalResources as &$resources){
			//do combo
			if(isset($this->combo['level']) && $this->combo['level'] > -1 && !empty($resources)){
				$combos = array();
				$remotes = array();

				foreach($resources as $url){
					if(isset($this->urlCache[$url])){
						if($this->combo['level'] == '0' && !isset($this->pkgUrlCache[$url]) || $this->combo['level'] > 0){
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
				$needSameBaseUrl = !empty($this->combo['onlySameBaseUrl']);
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
							$this->pkgUrlCache[$pkgHash[$name] = $pkg['url']] = true;
						}

						$url = $hash[$v] = $pkgHash[$name];
					}else{
						$url = $hash[$v] = $info['url'];
					}

					$this->urlCache[$url] = true;

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
		$this->domain && $view->set('FEATHER_STATIC_DOMAIN', $this->domain);

		$lastModifyTime = $this->getMaxMapModifyTime();
		$cacheFileName = md5($info['realpath']);
		$cache = null;

		if($this->caching){
			$cacheDir = $this->getOption('cacheDir', $this->view->getTempDir());
			$cachePath = $cacheDir . '/' . $cacheFileName;

			if($cache = FeatherView\Helper::readFile($cachePath)){
				$cache = unserialize($cache);
			}
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

			//如果需要设置缓存
			$this->caching && FeatherView\Helper::writeFile($cachePath, serialize($cache));
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