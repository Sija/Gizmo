<?php

namespace Gizmo;

abstract class ModelFactory
{
    protected
        $gizmo = null,
        $store = array();
    
    public function __construct(Gizmo $gizmo)
    {
        $this->gizmo = $gizmo;
    }
    
    public function get($path)
    {
        if (0 === strpos($path, $this->gizmo['content_path'])) {
            return $this->fromFullPath($path);
        }
        return $this->fromPath($path);
    }
    
    public function fromPath($path)
    {
        $fullPath = $this->gizmo['expand_path']($path);
        if ($fullPath) {
            return $this->fromFullPath($fullPath);
        }
        return false;
    }
    
    public function fromFullPath($fullPath)
    {
        if (0 !== strpos($fullPath, $this->gizmo['content_path'])) {
            return false;
        }
        if (isset($this->store[$fullPath])) {
            return $this->store[$fullPath];
        }
        $model = $this->modelFromFullPath($fullPath);
        if ($model) {
            $this->store[$fullPath] = $model;
        }
        return $model;
    }
    
    abstract public function modelFromFullPath($fullPath);
}
