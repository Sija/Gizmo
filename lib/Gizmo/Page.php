<?php

namespace Gizmo;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class Page implements \ArrayAccess {
    static public function fromFilePath($path) {
        $app = Gizmo::getInstance();
        if (0 !== strpos($path, $app['gizmo.content_path'])) {
            return false;
        }
        $path = preg_replace("#^{$app['gizmo.content_path']}/#", '', $path);
        $path = preg_replace(array('/^\d+?\./', '/(\/)\d+?\./'), '\\1', $path);
        
        return self::fromPath($path, $app);
    }
    
    static public function fromPath($path) {
        $app = Gizmo::getInstance();
        $full_path = $app['gizmo.content_path'];

        $path = trim($path, '/');
        $path = $path ?: 'index';

        # Split the url and recursively unclean the parts into folder names
        $path_parts = explode('/', $path);
        foreach ($path_parts as $part) {
            if ('_' === $part{0}) return false;

            $it = Finder::create()
              ->directories()
              ->depth(0)
              ->name("/^(\d+?\.)?{$part}$/")
              ->in($full_path);
            if (!iterator_count($it)) return false;

            foreach ($it as $dir) {
                $full_path .= '/' . $dir->getRelativePathname();
            }
        }
        
        $it = Finder::create()
          ->files()
          ->depth(0)
          ->name('*.yml')
          ->notName('_*')
          ->in($full_path);

        if (!iterator_count($it)) {
            return false;
        }

        $meta_files = iterator_to_array($it, false);
        $meta_file = $meta_files[0];
        $meta_data = Yaml::parse((string) $meta_file);

        $it = Finder::create()
          ->files()
          ->depth(0)
          ->name('_shared.yml');
        
        for ($i = count($path_parts); $i >= 0; --$i) {
            $it->in(realpath($full_path . str_repeat('/..', $i)));
        }
        
        $data = array();
        foreach ($it as $file) {
            $data = array_merge($data, Yaml::parse((string) $file));
        }
        if (!empty($meta_data)) {
            $data = array_merge($data, $meta_data);
        }
        $view_name = preg_replace('/\.yml$/', '', $meta_file->getRelativePathname());
        $view_file = preg_replace('/([^.]+\.)?([^.]+)$/', '\\2', $view_name);

        $it = Finder::create()
          ->files()
          ->depth(0)
          ->name($view_file . '*.twig')
          ->in($app['twig.path']);

        if (!iterator_count($it)) {
            $view = $app['gizmo.default_layout'];
        } else {    
            $views = iterator_to_array($it, false);
            $view = $views[0]->getRelativePathname();
        }
        
        $self = new self;
        $self->url = $app['request']->getBaseURL() . '/' . $path;
        $self->full_path = $full_path;
        $self->relative_path = preg_replace("#^{$app['gizmo.content_path']}/#", '', $full_path);
        $self->path = $path;
        $self->slug = preg_replace('#(.*?)/([^/]+)$#', '\\2', $path);
        $self->view = $view;
        $self->data = $data;
        
        return $self;
    }
    
    public function __construct() {
        $this->app = Gizmo::getInstance();
    }
    
    public function __toString() {
        return sprintf('#<%s: %s>', get_class($this), $this->path);
    }
    
    public function offsetSet($offset, $value) {
        if ($offset !== null) {
            $this->data[$offset] = $value;
        }
    }
    
    public function offsetExists($offset) {
        return isset($this->data[$offset]);
    }
        
    public function offsetUnset($offset) {
        unset($this->data[$offset]);
    }
        
    public function offsetGet($offset) {
        return isset($this->data[$offset]) ? $this->data[$offset] : null;
    }
    
    public function isCurrent() {
        $requestUri = $this->app['request']->getRequestUri();
        $baseUrl = $this->app['request']->getBaseUrl();
        
        if ('index' == $this->path) {
            return $requestUri == $baseUrl || $requestUri == $baseUrl . '/index';
        } else {
            return $requestUri == $baseUrl . '/' . $this->path;
        }
    }
    
    public function isInPath() {
        $requestUri = $this->app['request']->getRequestUri();
        $baseUrl = $this->app['request']->getBaseUrl();
        
        if ('index' === $this->path) {
            return $requestUri == $baseUrl || $requestUri == $baseUrl . '/index';
        } else {    
            return preg_match("#(/{$this->slug}|{$this->slug}/)#", $requestUri);
        }
    }
    
    public function getIndex() {
        $i = 0;
        $siblings = $this->getSiblings(true);
        foreach ($siblings as $sibling) {
          ++$i;
          if ($sibling == $this->full_path) {
              break;
          }
        }
        return $i;
    }

    public function isFirst() {
        return $this->getIndex() === 1;
    }
    
    public function isLast() {
        return $this->getIndex() === count($this->getSiblings(true));
    }
    
    public function getRoot() {
        $it = Finder::create()
          ->directories()
          ->depth(0)
          ->name('/^\d+?\./')
          ->in($this->app['gizmo.content_path']);
        
        $root = array();
        foreach ($it as $child) {
            $root[$child->getRelativePathname()] = (string) $child;
        }
        return $root;
    }
    
    public function getChildren() {
        $it = Finder::create()
          ->directories()
          ->depth(0)
          ->name('/^\d+?\./')
          ->in($this->full_path);
        
        $children = array();
        foreach ($it as $path => $child) {
            $children[$child->getRelativePathname()] = (string) $child;
        }
        return $children;
    }
    
    public function getSiblings($with_self = false) {
        $it = Finder::create()
          ->directories()
          ->depth(0)
          ->name('/^\d+?\./')
          ->in(realpath($this->full_path . '/..'));
        
        if (!$with_self) {
            $it->notName("/^(\d+?\.)?{$this->slug}$/");
        }

        $siblings = array();
        foreach ($it as $path => $child) {
            $siblings[$child->getRelativePathname()] = (string) $child;
        }
        return $siblings;
    }
    
    public function getClosestSiblings() {
        $siblings = $this->getSiblings(true);
        $neighbors = array();
        # flip keys/values
        $siblings = array_flip($siblings);
        # store keys as array
        $keys = array_keys($siblings);
        $keyIndexes = array_flip($keys);

        if (!empty($siblings) && isset($siblings[$this->full_path])) {
            # previous sibling
            if (isset($keys[$keyIndexes[$this->full_path] - 1])) {
                $neighbors[] = $keys[$keyIndexes[$this->full_path] - 1];
            } else {
                $neighbors[] = $keys[count($keys) - 1];
            }
            # next sibling
            if (isset($keys[$keyIndexes[$this->full_path] + 1])) {
                $neighbors[] = $keys[$keyIndexes[$this->full_path] + 1];
            } else {
                $neighbors[] = $keys[0];
            }
        }
        return !empty($neighbors) ? $neighbors : array(false, false);
    }
    
    public function getPreviousSibling() {
        $neighboring_siblings = $this->getClosestSiblings();
        return $neighboring_siblings[0];
    }
    
    public function getNextSibling() {
        $neighboring_siblings = $this->getClosestSiblings();
        return $neighboring_siblings[1];
    }
    
    public function getParent() {
        return realpath($this->full_path . '/..');
    }
    
    public function getParents() {
        $path_parts = explode('/', $this->relative_path);
        $parents = array();
        while (count($path_parts) > 1) {
            array_pop($path_parts);
            $parents[] = $this->app['gizmo.content_path'] . '/' . implode('/', $path_parts);
        }
        $parents = array_reverse($parents);
        return $parents;
    }
    
    public function doRender() {
        return $this->app['twig']->render($this->view, array('page' => $this));
    }
}
