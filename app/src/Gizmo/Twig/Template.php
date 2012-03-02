<?php

namespace Gizmo;

abstract class Twig_Template extends \Twig_Template {
    protected function getAttribute($object, $item, array $arguments = array(), $type = 'any', $isDefinedTest = false, $ignoreStrictCheck = false) {
        
        if (is_string($object)) {
            #$object = AssetFactory::get($object);
            $page = Page::fromFilePath($object);
            if ($page) {
                $object = $page;
            }
        }
        return parent::getAttribute($object, $item, $arguments, $type, $isDefinedTest, $ignoreStrictCheck);
    }
}
