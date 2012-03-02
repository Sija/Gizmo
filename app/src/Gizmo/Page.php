<?php

namespace Gizmo;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class Page implements \ArrayAccess {
    
    static protected function getNormalizedPath($path) {
        return trim($path, '/') ?: 'index';
    }
    
    static protected function findFullPath($path, $content_path) {
        $path = self::getNormalizedPath($path);

        # Split the url and recursively unclean the parts into folder names
        $path_parts = explode('/', $path);
        foreach ($path_parts as $part) {
            if ('_' === $part{0}) return false;

            $it = Finder::create()
              ->directories()
              ->depth(0)
              ->name("/^(\d+?\.)?{$part}$/")
              ->in($content_path);
            if (!iterator_count($it)) return false;

            foreach ($it as $dir) {
                $content_path .= '/' . $dir->getRelativePathname();
            }
        }
        return $content_path;
    }
    
    static protected function findModelFile($full_path) {
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
        
        return (string) $meta_file;
    }
    
    static public function fromPath($path) {
        $app = Gizmo::getInstance();
        
        $full_path = self::findFullPath($path, $app['gizmo.content_path']);
        if ($full_path) {
            $meta_file = self::findModelFile($full_path);
            if ($meta_file) {
                return new self($meta_file, $full_path, $path);
            }
        }
        return false;
    }
    
    static public function fromFullPath($full_path) {
        $app = Gizmo::getInstance();
        if (0 !== strpos($full_path, $app['gizmo.content_path'])) {
            return false;
        }
        $path = preg_replace("#^{$app['gizmo.content_path']}/#", '', $full_path);
        $path = preg_replace(array('/^\d+?\./', '/(\/)\d+?\./'), '\\1', $path);
        
        return self::fromPath($path);
    }
    
    public function __construct($meta_file, $full_path, $path) {
        $this->app = Gizmo::getInstance();
        $this->full_path = $full_path;
        $this->path = self::getNormalizedPath($path);
        $this->url = $this->app['request']->getBaseURL() . '/' . $this->path;
        $this->slug = preg_replace('#(.*?)/([^/]+)$#', '\\2', $this->path);
        $this->title = ucfirst(preg_replace('/[-_]/', ' ', $this->slug));

        $this->model_name = preg_replace('/\.yml$/', '', basename($meta_file));
        $this->model_name = preg_replace('/([^.]+\.)?([^.]+)$/', '\\2', $this->model_name);
        
        $this->data = $this->getModelData($meta_file);
        $it = Finder::create()
          ->files()
          ->depth(0)
          ->name($this->model_name . '*.twig')
          ->in($this->app['twig.path']);

        if (!iterator_count($it)) {
            $this->view = $app['gizmo.default_layout'];
        } else {    
            $views = iterator_to_array($it, false);
            $this->view = $views[0]->getRelativePathname();
        }
    }
    
    public function getModelData($meta_file) {
        $it = Finder::create()
          ->files()
          ->depth(0)
          ->name('_shared.yml');
        
        $path_parts = explode('/', $this->path);
        for ($i = count($path_parts); $i >= 0; --$i) {
            $it->in(realpath($this->full_path . str_repeat('/..', $i)));
        }
        
        $meta_data = Yaml::parse($meta_file);
        $data = array();
        foreach ($it as $file) {
            if ($loaded = Yaml::parse((string) $file)) {
                $data = array_merge($data, $loaded);
            }
        }
        if (!empty($meta_data)) {
            $data = array_merge($data, $meta_data);
        }
        return $data;
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
        $path_parts = explode('/', $this->path);
        $parents = array();
        while (count($path_parts) > 1) {
            array_pop($path_parts);
            $parents[] = $this->app['gizmo.content_path'] . '/' . implode('/', $path_parts);
        }
        $parents = array_reverse($parents);
        return $parents;
    }
}
