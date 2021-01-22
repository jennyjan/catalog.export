<?php

namespace Сatalog\Export;

class HtmlRenderer implements IRenderer {
    public function render($data) {        
        foreach ($data as $k => $v) {   
            $html .= "{$k}: {$v}";
        }
        echo $html;
    }
}