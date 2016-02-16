<?php
class Feather_View_Plugin_Static_Position extends Feather_View_Plugin_Abstract{
    public function exec($content, $info){
        if(!$info['isLoad'] && strpos($info['path'], '/component/') === 0){
            if(!preg_match('#<!--FEATHER STATIC POSITION:HEAD-->#', $content)){
                $content = '
                <?php  
                $this->set("FEATHER_HEAD_RESOURCE_LOADED", true);
                $this->load("/component/resource/usestyle", $this->get("FEATHER_USE_STYLES")); 
                $this->load("/component/resource/usescript", $this->get("FEATHER_USE_HEAD_SCRIPTS"));
                ?>' . $content;
            }

            if(!preg_match('#<!--FEATHER STATIC POSITION:BOTTOM-->#', $content)){
                $content .= '
                <?php
                $this->set("FEATHER_BOTTOM_RESOURCE_LOADED", true);
                $this->load("/component/resource/usescript", $this->get("FEATHER_USE_BOTTOM_SCRIPTS"));
                $this->set("FEATHER_SCRIPT2BOTTOMS_LOADED", true);
                $this->load("/component/resource/usescript", array("inline" => $this->get("FEATHER_SCRIPT2BOTTOMS")));
                ?>';
            }
        }

        return $content;
    }
}