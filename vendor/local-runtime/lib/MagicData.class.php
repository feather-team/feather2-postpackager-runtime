<?php
class MagicData{
	const LIMITER_REG = '/<<<((?:(?!<<<)[\s\S])+?)>>>(?:\{(\d+)(,(\d+)?)?\})?/';
	const RAND_SPLIT = '/(?<!\\\\),/';
	const RANGE_REG = '/(?<!\\\\)~/';
	const MAX_NUMBER = 100;

	private static $stack = array();
	private static $count = 0;
	private static $rand;

	public static function parseByFile($file){
		return self::parseByString(file_get_contents($file));
	}

	public static function parseByString($str = ''){
		$str = self::pushStack($str);

		while(self::$count--){
			$str = self::parse($str);
		}

		return $str;
	}

	public static function pushStack($str){
		self::$rand = '__MAGICDATA' . rand(0, 1000) . 'MAGICDATA__';
		self::$count = 0;

		do{	
			$tmp = $str;
			$str = preg_replace_callback(self::LIMITER_REG, function($match){
				$index = self::$rand . (string)self::$count++ . self::$rand;

				self::$stack[] = array(
					'key' => $index,
					'match' => $match[0]
				);

				return $index;
			}, $str);
		}while($tmp != $str);

		return $str;
	}

	protected static function parse($str){
		$stack = array_pop(self::$stack);
		$str = preg_replace_callback("/{$stack['key']}/", function() use($stack){
			return self::_parse($stack['match']);
		}, $str);

		return $str;
	}

	protected static function _parse($str){
		return preg_replace_callback(self::LIMITER_REG, function($match){
			if(isset($match[2])){
				return self::dealMutil($match[1], $match[2], $match[3] ? $match[4] ? $match[4] : self::MAX_NUMBER : null);
			}else{
				return self::dealSingle($match[1]);
			}
		}, $str);
	}

	protected static function dealMutil($str, $min = 1, $max = null){
		$n = $max ? rand($min, $max) : $min;

		for($r = array(), $i = 0; $i < $n; $i++){
			$r[] = $str;
		}

		return implode(',', $r);
	}

	protected static function dealSingle($str){
		$str = preg_split(self::RAND_SPLIT, $str);
		$str = $str[rand(0, count($str) - 1)];

		$str = preg_split(self::RANGE_REG, $str);

		if(count($str) > 1){
			$str = rand((int)$str[0], $str[1] ? (int)$str[1] : self::MAX_NUMBER);
		}else{
			$str = $str[0];
		}

		return $str;
	}
}