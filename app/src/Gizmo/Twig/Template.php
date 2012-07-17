<?php

namespace Gizmo;

abstract class Twig_Template extends \Twig_Template
{
    protected function getAttribute($object, $item, array $arguments = array(), $type = 'any', $isDefinedTest = false, $ignoreStrictCheck = false)
    {
        if (is_string($object)) {
            $model = $this->getEnvironment()->getExtension('Gizmo')->get($object);
            if ($model) {
                $object = $model;
            }
        }
        return parent::getAttribute($object, $item, $arguments, $type, $isDefinedTest, $ignoreStrictCheck);
    }
}
