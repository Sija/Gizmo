<?php

namespace Gizmo;

abstract class ModelFactory
{
    protected $app = null;
    protected $store = array();
    
    public function __construct(\Silex\Application $app)
    {
        $this->app = $app;
    }
    
    public function get($path)
    {
        if (0 === strpos($path, $this->app['gizmo.content_path'])) {
            return $this->fromFullPath($path);
        }
        return $this->fromPath($path);
    }
    
    public function fromPath($path)
    {
        $full_path = $this->app['gizmo.expand_path']($path);
        if ($full_path) {
            return $this->fromFullPath($full_path);
        }
        return false;
    }
    
    public function fromFullPath($full_path)
    {
        if (0 !== strpos($full_path, $this->app['gizmo.content_path'])) {
            return false;
        }
        if (isset($this->store[$full_path])) {
            return $this->store[$full_path];
        }
        $model = $this->modelFromFullPath($full_path);
        if ($model) {
            $this->store[$full_path] = $model;
        }
        return $model;
    }
    
    abstract public function modelFromFullPath($full_path);
}
