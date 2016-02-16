<?php
class Feather_View_Loader{     
    protected static $importCache = array();       
    protected static $importPath = array();        
       
    public static function setImportPath($path = array()){     
        foreach((array)$path as $p){       
            self::$importPath[] = rtrim($path, '/');       
        }      
    }      
       
    public static function import($path){      
        $path = '/' . ltrim($path);        
       
        if(isset(self::$importCache[$path])){      
            return self::$importCache[$path];      
        }      
       
        foreach(self::$importPath as $prefix){     
            $realpath = $prefix . $path;       
       
            if(is_file($realpath)){        
                return self::$importCache[$path] = require($realpath);        
            }      
        }      
       
        return self::$importCache[$path] = require($path);        
    }      
}      