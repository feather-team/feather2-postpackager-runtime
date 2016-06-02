<?php 
$output = array();

if($type){
	foreach($FEATHER_USE_STYLES as $key => $value){
		$output[] = '<link rel="stylesheet" href="' . $value . '" type="text/css" />';
	}

	foreach($FEATHER_USE_HEAD_SCRIPTS as $key => $value){
		$output[] = '<script src="' . $value . '"></script>';
	}

	foreach($FEATHER_USE_HEAD_INLINE_SCRIPTS as $key => $value){
		$output[] = $value;
	}
}else{
	foreach($FEATHER_USE_SCRIPTS as $key => $value){
		$output[] = '<script src="' . $value . '"></script>';
	}
}

echo $this->evalContent($this->get(), implode('', $output));