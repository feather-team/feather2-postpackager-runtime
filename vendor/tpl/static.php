<?php 
switch($type){
	case 'head':
		if(!$this->get('FEATHER_HEAD_RESOURCE_LOADED')){
			$links = $this->get('FEATHER_USE_STYLES');
			$scripts = $this->get('FEATHER_USE_HEAD_SCRIPTS');
			$this->set('FEATHER_HEAD_RESOURCE_LOADED', true);
		}

		break;

	default:
		if(!$this->get('FEATHER_BOTTOM_RESOURCE_LOADED')){
			$scripts = $this->get('FEATHER_USE_SCRIPTS');
			$this->set('FEATHER_BOTTOM_RESOURCE_LOADED', true);
		}
}

$output = array();

if(!empty($links)){
	foreach($links as $key => $value){
		$output[] = '<link rel="stylesheet" href="' . $value . '" type="text/css" />';
	}
}

if(!empty($scripts)){
	foreach($scripts as $key => $value){
		$output[] = '<script src="' . $value . '"></script>';
	}
}

echo $this->evalContent($this->get(), implode('', $output));