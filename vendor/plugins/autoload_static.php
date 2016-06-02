<?php
namespace FeatherView\Plugin;
use FeatherView;

require __DIR__ . '/FeatherResource.php';
use FeatherResource;

class AutoloadStatic extends SystemPluginAbstract{
	//执行主程
	public function exec($content, $info){
		if($info['method'] == 'load') return $content;

		$view = $this->view;
		$cacheDir = $this->view->getTempDir();

		if(!$cacheDir){
			throw new \Exception('AutoStatic Load Plugin need view engine\'s tempDir as cacheDir, Please set view engine\'s tempDir first!');
		}

		$dirs = $this->view->getTemplateDir();
		$id = ltrim($info['path'], '/');

		$mapsInstance = new FeatherResource\Maps($dirs[0], array(
			'cacheDir' => $cacheDir . '/resources'
		));
		
		$view->set($mapsInstance->getResourcesData($id));

		return $content;
	}
}