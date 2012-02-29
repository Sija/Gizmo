<?php

namespace Gizmo;

class Gizmo implements \ArrayAccess {
    static private $instance = null;
    private $silex = null;
    
    static public function getInstance() {
        return self::$instance;
    }
    
    public function __construct(\Silex\Application $silex) {
        self::$instance = $this;
        $this->silex = $silex;
        
        $this->setup();
    }
    
    public function setup() {
        $gizmo = $this;
        $this->silex->get('/{path}', function ($path) use ($gizmo) {
            return $gizmo->dispatch($path);
        })->assert('path', '.*');
    }
    
    public function getSilex() {
        return $this->silex;
    }
    
    public function offsetSet($offset, $value) {
        $this->silex[$offset] = $value;
    }
    
    public function offsetExists($offset) {
        return isset($this->silex[$offset]);
    }
        
    public function offsetUnset($offset) {
        unset($this->silex[$offset]);
    }
        
    public function offsetGet($offset) {
        return isset($this->silex[$offset]) ? $this->silex[$offset] : null;
    }
    
    public function dispatch404($path) {
        $this->silex->abort(404, "Sorry, the page {$path} could not be found.");
    }
    
    public function dispatch($path) {
        $page = Page::fromPath($path);
        if (!$page) {
            $this->dispatch404($path);
        }
        return $page->doRender();
    }
}
