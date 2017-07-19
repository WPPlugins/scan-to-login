<?php

class s2l_language{
    
    public $language;
    
    function __construct($lang = null) {
    	// until we support new languages
        $this->language = json_decode(file_get_contents('lang/en_US.json', true));
        // if(!is_null($lang))
        //     $this->language = json_decode(file_get_contents('lang/'.$lang.'.json', true));
        // else
        //     $this->language = json_decode(file_get_contents('lang/'.get_locale().'.json', true));
    }
    
    public function getString($stringName){
        foreach($this->language->strings->string as $string){
            if($string->name == $stringName){
                return $string->value;
            }
        } 
    }
}

?>
