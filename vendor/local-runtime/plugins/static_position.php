<?php
namespace FeatherView\Plugin;

class StaticPosition extends SystemPluginAbstract{
    public function exec($content, $info){
        if(!$info['method'] == 'load' && strpos($info['path'], '/widget/') === 0){
            if(!preg_match('#<!--FEATHER STATIC POSITION:HEAD-->#', $content)){
                $content = '<?php $this->load("_static_", array("type" => "head"));?>' . $content;
            }

            if(!preg_match('#<!--FEATHER STATIC POSITION:BOTTOM-->#', $content)){
                $content .= '
                <?php $this->load("_static_", array("type" => "bottom"));?>
                <?php $this->plugin("script_collection")->output();?>
                ';
            }
        }

        return $content;
    }
}