<?php
namespace FeatherView\Plugin;

class AutoloadData extends SystemPluginAbstract{
	const DATA_FILE_SUFFIX = '.php';
	const GLOBAL_FILE = '_global_.php';

	private $map = array();

	private function initMap(){
		//合并map表
		foreach((array)$this->getOption('maps') as $resource){
			$resource = require($resource);
			$this->map = array_merge($this->map, $resource['map']);
		}
	}

	//获取页面所有用到的refs
	private function getRefs($path){
		$selfMap = isset($this->map[$path]) ? $this->map[$path] : array();

		if(isset($selfMap['refs'])){
			$selfRefs = $selfMap['refs'];

			foreach($selfRefs as $ref){
				$selfRef = array_merge($selfRef, $this->getRefs($ref));
			}
		}else{
			$selfRefs = array();
		}

		return $selfRefs;
	}

	public function exec($content, $info){
		if($info['method'] == 'load') return $content;

		$this->initMap();

		$dataRoot = rtrim($this->getOption('data_dir'), '/') . '/';
		$fData = array();

		$path = $info['path'];
		$refs = $this->getRefs($path);
		array_push($refs, $path);
		array_unshift($refs, self::GLOBAL_FILE);

		foreach($refs as $path){
			$info = pathinfo($path);
			$path = "{$dataRoot}{$info['dirname']}/{$info['filename']}" . self::DATA_FILE_SUFFIX;

			if(file_exists($path)){
				if($data = include($path)){
					$fData = array_merge($fData, $data);
				}
			}
		}

		$this->view->set($fData);

		return $content;
	}
}