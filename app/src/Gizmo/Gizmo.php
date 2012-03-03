<?php

namespace Gizmo;

class Gizmo implements \ArrayAccess {
    const VERSION = '1.0b';
    
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
    
    public function offsetSet($offset, $value) {
        if ($offset !== null) {
            $this->silex[$offset] = $value;
        }
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
    
    public function setup() {
        $gizmo = $this;
        
        $this->silex->get('/{path}', function ($path) use ($gizmo) {
            return $gizmo->dispatch($path);

        })->assert('path', '.*')->bind('page');
    }
    
    public function dispatch404($path) {
        if ($page404 = Page::fromPath('404')) {
            return $this->silex['twig']->render($page404->view, array('page' => $page404));
        }
        $this->silex->abort(404, sprintf('Sorry, the page "%s" could not be found.', $path));
    }
    
    public function dispatch($path) {
        $path = Page::getNormalizedPath($path);
        $page = Page::fromPath($path);
        if (!$page) {
            return $this->dispatch404($path);
        }
        return $this->silex['twig']->render($page->view, array('page' => $page));
    }
}
