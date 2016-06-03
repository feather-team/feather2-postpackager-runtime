<?php 
$output = array();

if($type){
    if(!$this->get('FEATHER_HEAD_RESOURCE_LOADED')){
        foreach($this->get('FEATHER_USE_STYLES') as $value){
            $output[] = '<link rel="stylesheet" href="' . $value . '" type="text/css" />';
        }

        foreach($this->get('FEATHER_USE_HEAD_SCRIPTS') as $value){
            $output[] = '<script src="' . $value . '"></script>';
        }

        foreach($this->get('FEATHER_USE_HEAD_INLINE_SCRIPTS') as $value){
            $output[] = $value;
        }

        $this->set('FEATHER_HEAD_RESOURCE_LOADED', true);
    }
}else{
    if(!$this->get('FEATHER_BOTTOM_RESOURCE_LOADED')){
        foreach($this->get('FEATHER_USE_SCRIPTS') as $value){
            $output[] = '<script src="' . $value . '"></script>';
        }

        $this->set('FEATHER_BOTTOM_RESOURCE_LOADED', true);
    }
}

echo $this->evalContent($this->get(), implode('', $output));