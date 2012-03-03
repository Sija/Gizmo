<?php

namespace Gizmo;

class Twig_Extension_Gizmo extends \Twig_Extension {
    public function getName() {
        return 'Gizmo';
    }

    public function getFunctions() {
        return array(
            'get' => new \Twig_Filter_Method($this, 'get'),
        );
    }

    public function get($path) {
        # AssetFactory::get($path)
        return Page::fromPath($path);
    }
}
