<?php

namespace Сatalog\Export;

class VoidRenderer implements IRenderer {    
    public function render($data) {        
        return;
    }
}