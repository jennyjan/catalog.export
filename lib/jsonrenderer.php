<?php

namespace Сatalog\Export;

class JsonRenderer implements IRenderer {  
    public function render($data) {        
        echo json_encode($data);
    }
}