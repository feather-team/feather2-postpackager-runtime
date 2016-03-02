<?php
class Feather_View_Plugin_Static_Position extends Feather_View_Plugin_Abstract{
    public function exec($content, $info){
        if(!$info['isLoad'] && strpos($info['path'], '/widget/') === 0){
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