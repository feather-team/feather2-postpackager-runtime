<?php
namespace FeatherView\Plugin;
use FeatherView;

/**
 * 系统级插件基类
 */
abstract class SystemPluginAbstract{
	protected $options = array();
	protected $view;

	public function __construct($opt = array(), FeatherView\Engine $view){
		$this->options = (array)$opt;
		$this->view = $view;
		$this->initialize();
	}

	protected function initialize(){}

	public function getOption($name = null, $default = null){
		return isset($this->options[$name]) ? $this->options[$name] : $default;
	}

	public function setOption($name, $value = null){
		$this->options[$name] = $value;
	}

	abstract public function exec($content, $info);
}