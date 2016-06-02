<?php 
$output = array();

if($type == 'head'){
    if(!isset($FEATHER_HEAD_RESOURCE_LOADED)){
        foreach($FEATHER_USE_STYLES as $value){
            $output[] = '<link rel="stylesheet" href="' . $value . '" type="text/css" />';
        }

        foreach($FEATHER_USE_HEAD_SCRIPTS as $value){
            $output[] = '<script src="' . $value . '"></script>';
        }

        foreach($FEATHER_USE_HEAD_INLINE_SCRIPTS as $key => $value){
            $output[] = $value;
        }

        $__env->share('FEATHER_HEAD_RESOURCE_LOADED', true);
    }
}else{
    if(!isset($FEATHER_BOTTOM_RESOURCE_LOADED)){
        foreach($FEATHER_USE_SCRIPTS as $value){
            $output[] = '<script src="' . $value . '"></script>';
        }

        $__env->share('FEATHER_BOTTOM_RESOURCE_LOADED', true);
    }
}

echo implode('', $output);