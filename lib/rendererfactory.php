<?php

namespace Сatalog\Export;

class RendererFactory
{
    private function isAjax() {        
        return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest';    
    }    

    private function isCLI() {        
        return php_sapi_name() == "cli";    
    }

    public function create()
    {
        if ($this->isAjax()) {  
            return new JsonRenderer();
        }        

        if ($this->isCLI()) {            
            return new VoidRenderer();        
        }        

        return new HtmlRenderer(); 
    }
}
