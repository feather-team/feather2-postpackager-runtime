<?php
namespace FeatherView\Plugin;

class ScriptCollection{
	protected $list = array();

	public function start(){
		ob_start();
	}

	public function end(){
		$this->list[] = ob_get_contents(); 
		ob_end_clean(); 
	}

	public function output(){
		foreach($this->list as $item){
			echo $item;
		}
	}
}