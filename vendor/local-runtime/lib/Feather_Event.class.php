<?php
class Feather_Event{
    protected $list = array();

    public function on($name, $callback){
        if(!isset($this->list[$name])){
            $this->list[$name] = array($callback);
        }else{
            $this->list[$name][] = $callback;
        }
    }

    public function off($name){
        unset($this->list[$name]);
    }

    public function trigger($name, $data = null){
        if(isset($this->list[$name])){
            foreach($this->list[$name] as $callback){
                $callback($data);
            }
        }
    }
}