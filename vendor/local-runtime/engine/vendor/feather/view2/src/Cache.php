<?php
namespace FeatherView;

class Cache{
	const CACHE_SUFFIX = '.php';

	protected $file;
	protected $cacheDir;
	protected $cacheFile;
	protected $cacheContent = '';

	public function __construct($file, $dir){	
		$this->file = $file;
		$this->cacheDir = rtrim($dir, '/') . '/';

		if(!is_file($this->file)){
			throw new Exception('unable to cache file[' . $this->file . ']: No such file.');
		}

		$this->cacheFile = $this->cacheDir . md5($this->file) . self::CACHE_SUFFIX;
	}

	public function save($content = ''){
		if(!self::mkdir($this->cacheDir)){
			throw new Exception('unable to create dir[' . $this->dir . '].');
		}

		return file_put_contents($this->cacheFile, $this->cacheContent = $content);
	}

	public function read(){
		if(!$this->cacheContent && $this->cacheExists()){
			$this->cacheContent = file_get_contents($this->cacheFile);
		}

		return $this->cacheContent;
	}

	public function loadCacheFile(){
		if($this->cacheExists()){
			return require $this->cacheFile;
		}

		return false;
	}

	public function isExpires(){
		if($this->cacheExists()){
			return filemtime($this->cacheFile) < filemtime($this->file);
		}

		return true;
	}

	public function cacheExists(){
		return is_file($this->cacheFile);
	}

	public static function mkdir($dir, $mod = 0777){
	    if(is_dir($dir)){
	        return true;
	    }else{
	        $old = umask(0);

	        if(mkdir($dir, $mod, true) && is_dir($dir)){
	            umask($old);
	            return true;
	        } else {
	            umask($old);
	        }
	    }

	    return false;
	}
}