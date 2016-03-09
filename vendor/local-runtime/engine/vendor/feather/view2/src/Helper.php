<?php
namespace FeatherView;

class Helper{
	/**
	 * 遍历查找某一个文件的绝对路径
	 * @param  array|string dirs:需要遍历的目录
	 * @param  string path:查找文件名
	 * @param  string suffix:如果path没有后缀，则使用此后缀
	 * @return string|bool
	 */
	public static function foundPath($dirs, $path, $suffix = '.php'){
		if(!self::getFileSuffix($path)){
    		$path = $path . $suffix;
    	}

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

    //require某个文件，参数同foundpath
    public static function loadFile($dirs, $path, $suffix = '.php'){
    	if($file = self::foundPath($dirs, $path, $suffix)){
    		return require $file;
    	}

    	return false;
    }

    //获取文件后缀
    public static function getFileSuffix($path){
    	if(preg_match('/\.[^\.]+$/', $path, $match)){
    		return $match[0];
    	}

    	return null;
    }

    /**
     * 下划线转驼峰
     * @param  string 需要转义的字符串
     * @param  boolean 是否保留下划线
     * @return string
     */
    public static function ul2camel($str, $saveUl = false){
    	return preg_replace_callback('/(^|_)(\w)/', function($match){
    		$m = strtoupper($match[2]);

    		if(!empty($saveUl)){
    			return $match[1] . $m;
    		}

    		return $m;
    	}, $str);
    }
}