<?php

namespace Gizmo;

abstract class Model implements \ArrayAccess
{
    protected
        $_app = null,
        $_attributes = array(),
        $_dynamicAttributes = array(),
        $_data = array(),
        $_requiredKeys = array('fullPath');
    
    public function __construct(\Silex\Application $app, array $data = array())
    {
        $this->_app = $app;
        $this->setDefaultAttributes();
        $this->setData($data);
    }

    public function __toString()
    {
        return sprintf('#<%s: %s>', get_class($this), $this->path);
    }
    
    public function __get($key)
    {
        return $this[$key];
    }
    
    public function __set($key, $value)
    {
        $this[$key] = $value;
    }
    
    public function __isset($key)
    {
        return isset($this[$key]);
    }
    
    public function __unset($key)
    {
        unset($this[$key]);
    }
    
    public function offsetGet($key)
    {
        if (isset($this->_data[$key])) {
            return $this->_data[$key];
        }
        if (isset($this->_attributes[$key])) {
            return $this[$key] = $this->_attributes[$key]($this, $this->_app);
        }
        if (isset($this->_dynamicAttributes[$key])) {
            return $this->_dynamicAttributes[$key]($this, $this->_app);
        }
        return null;
    }

    public function offsetSet($key, $value)
    {
        $this->_data[$key] = $value;
    }

    public function offsetExists($key)
    {
        return isset($this->_data[$key])
            || isset($this->_attributes[$key])
            || isset($this->_dynamicAttributes[$key]);
    }

    public function offsetUnset($key)
    {
        unset($this->_data[$key]);
    }

    public function addRequiredKey($key)
    {
        $this->_requiredKeys[] = $key;
    }

    public function addAttributes(array $attributes)
    {
        $this->_attributes = array_merge($this->_attributes, $attributes);
    }
    
    public function addDynamicAttributes(array $attributes)
    {
        $this->_dynamicAttributes = array_merge($this->_dynamicAttributes, $attributes);
    }
    
    public function setData(array $data)
    {
        foreach ($this->_requiredKeys as $key) {
            if (!isset($data[$key])) {
                throw new \Exception(sprintf('Missing key "%s"', $key));
            }
        }
        $this->_data = $data;
    }
    
    public function addData(array $data)
    {
        $this->_data = array_merge($this->_data, $data);
    }
    
    public function clearData()
    {
        foreach ($this->_data as $key => $value) {
            if (!isset($this->_requiredKeys[$key])) {
                unset($this[$key]);
            }
        }
    }
    
    public function toArray()
    {
        $data = $this->_data;
        foreach (array_keys($this->_attributes) as $key) {
            if (!isset($data[$key])) {
                $data[$key] = $this[$key];
            }
        }
        return $data;
    }

    protected function setDefaultAttributes()
    {
        $this->addAttributes(array(
            'path' => function ($model, $app) {
                if (0 !== strpos($model->fullPath, $app['gizmo.content_path'])) {
                    return false;
                }
                $path = preg_replace("#^{$app['gizmo.content_path']}/?#", '', $model->fullPath);
                $path = preg_replace(array('/^\d+?\./', '/(\/)\d+?\./'), '\\1', $path);
                $path = trim($path, '/') ?: 'index';
                return $path;
            },
            'slug' => function ($model) {
                return preg_replace('#(.*?)/([^/]+)$#', '\\2', $model->path);
            },
            'permalink' => function ($model, $app) {
                $url = rtrim($app['gizmo.mount_point'], '/') . '/' . $model->path;
                $url = preg_replace('/\/index$/', '', $url);
                return $url;
            },
            'url' => function ($model, $app) {
                return $app['request']->getBaseURL() . $model->permalink;
            },
            'uri' => function ($model, $app) {
                return $app['request']->getUriForPath($model->permalink);
            },
            'title' => function ($model) {
                return ucfirst(preg_replace(
                    array('/[-_]/', '/\.[\w\d]+?$/', '/^\d+?\./'),
                    array(' ', '', ''),
                    $model->slug
                ));
            },
            'updated' => function ($model) {
                return filemtime($model->fullPath);
            },
            'root' => function ($model, $app) {
                return $app['gizmo.cache']->getFolders($app['gizmo.content_path'], '/^\d+?\./');
            },
            'parent' => function ($model, $app) {
                $pathSegments = explode('/', $model->path);
                $parents = array();
                while (count($pathSegments) >= 1) {
                    array_pop($pathSegments);
                    if ($parent = $app['gizmo.page'](join('/', $pathSegments))) {
                        return $parent->fullPath;
                    }
                }
                return null;
            },
            'parents' => function ($model, $app) {
                $pathSegments = explode('/', $model->path);
                $parents = array();
                while (count($pathSegments) >= 1) {
                    array_pop($pathSegments);
                    if ($parent = $app['gizmo.page'](join('/', $pathSegments))) {
                        $parents[] = $parent->fullPath;
                    }
                }
                $parents = array_reverse($parents);
                return $parents;
            },
            'children' => function ($model, $app) {
                return $app['gizmo.cache']->getFolders($model->fullPath, '/^\d+?\./');
            },
            'siblings' => function ($model, $app) {
                if ($model->isRoot) {
                    return array();
                }
                return $app['gizmo.cache']->getFolders($model->parent, '/^\d+?\.(?!' . preg_quote($model->slug) . ')/');
            },
            'siblingsWitSelf' => function ($model, $app) {
                if ($model->isRoot) {
                    return (array) $model->fullPath;
                }
                return $app['gizmo.cache']->getFolders($model->parent, '/^\d+?\./');
            },
            'closestSiblings' => function ($model) {
                $siblings = $model->siblingsWitSelf;
                $neighbors = array();
                # flip keys/values
                $siblings = array_flip($siblings);
                # store keys as array
                $keys = array_keys($siblings);
                $keyIndexes = array_flip($keys);

                $path = $model->fullPath;
                if (!empty($siblings) && isset($siblings[$path])) {
                    # previous sibling
                    if (isset($keys[$keyIndexes[$path] - 1])) {
                        $neighbors[] = $keys[$keyIndexes[$path] - 1];
                    } else {
                        $neighbors[] = $keys[count($keys) - 1];
                    }
                    # next sibling
                    if (isset($keys[$keyIndexes[$path] + 1])) {
                        $neighbors[] = $keys[$keyIndexes[$path] + 1];
                    } else {
                        $neighbors[] = $keys[0];
                    }
                }
                return !empty($neighbors) ? $neighbors : array(null, null);
            },
            'previousSibling' => function ($model) {
                return $model->closestSiblings[0];
            },
            'previousSiblings' => function ($model) {
                if (!$model->index) {
                    return array();
                }
                return array_slice($model->siblingsWitSelf, 0, $model->index - 1);
            },
            'nextSibling' => function ($model) {
                return $model->closestSiblings[1];
            },
            'nextSiblings' => function ($model) {
                if (!$model->index) {
                    return array();
                }
                $siblingsWitSelf = $model->siblingsWitSelf;
                return array_slice($siblingsWitSelf, $model->index, count($siblingsWitSelf));
            },
            'index' => function ($model) {
                $i = 0;
                $siblings = $model->siblingsWitSelf;
                foreach ($siblings as $sibling) {
                  ++$i;
                  if ($sibling == $model->fullPath) {
                      return $i;
                  }
                }
                return 0;
            },
            'level' => function ($model) {
                return !$model->isRoot
                    ? 1 + substr_count($model->path, '/')
                    : 0;
            },
            'isRoot' => function ($model, $app) {
                return ($model->fullPath == $app['gizmo.content_path']);
            },
            'isFirst' => function ($model) {
                return $model->index === 1;
            },
            'isLast' => function ($model) {
                return $model->index === count($model->siblingsWitSelf);
            }
        ));
    }
}