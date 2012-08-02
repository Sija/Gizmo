<?php

namespace Gizmo;

use Symfony\Component\HttpFoundation\Response;

abstract class Model
{
    protected
        $gizmo = null,
        $attributes = array(),
        $dynamicAttributes = array(),
        $data = array(),
        $requiredKeys = array('fullPath');
    
    public function __construct(Gizmo $gizmo, array $data = array())
    {
        $this->gizmo = $gizmo;
        $this->setDefaultAttributes();
        $this->setData($data);
    }

    public function __toString()
    {
        return sprintf('#<%s: %s>', get_class($this), $this->path);
    }
    
    public function __get($key)
    {
        return $this->get($key);
    }
    
    public function __set($key, $value)
    {
        $this->set($key, $value);
    }
    
    public function __isset($key)
    {
        return isset($this->data[$key])
            || isset($this->attributes[$key])
            || isset($this->dynamicAttributes[$key]);
    }
    
    public function __unset($key)
    {
        unset($this->data[$key]);
    }

    public function get($key) {
        if (isset($this->data[$key]))
            return $this->data[$key];
        
        if (isset($this->attributes[$key]))
            return $this->$key = $this->attributes[$key]($this, $this->gizmo);
        
        if (isset($this->dynamicAttributes[$key]))
            return $this->dynamicAttributes[$key]($this, $this->gizmo);
        
        return null;
    }
    
    public function set($key, $value) {
        $this->data[$key] = $value;
    }
    
    public function addRequiredKey($key)
    {
        $this->requiredKeys[] = $key;
    }

    public function addAttributes(array $attributes)
    {
        $this->attributes = array_merge($this->attributes, $attributes);
    }
    
    public function addDynamicAttributes(array $attributes)
    {
        $this->dynamicAttributes = array_merge($this->dynamicAttributes, $attributes);
    }
    
    public function setData(array $data)
    {
        foreach ($this->requiredKeys as $key) {
            if (!isset($data[$key]))
                throw new \Exception(sprintf('Missing key "%s"', $key));
        }
        $this->data = $data;
    }
    
    public function addData(array $data)
    {
        $this->data = array_merge($this->data, $data);
    }
    
    public function clearData()
    {
        foreach ($this->data as $key => $value) {
            if (!isset($this->requiredKeys[$key]))
                unset($this->$key);
        }
    }
    
    public function toArray(array $keys = null)
    {
        $data = empty($keys) ? $this->data : array();
        $keys = empty($keys) ? array_keys($this->attributes) : $keys;
        foreach ($keys as $key) {
            if (!isset($data[$key]))
                $data[$key] = $this->$key;
        }
        return $data;
    }
    
    public function isEqual(Model $other)
    {
        return $this->path === $other->path;
    }
    
    public function renderWith(Response $response)
    {
        return false;
    }
    
    protected function setDefaultAttributes()
    {
        $this->addAttributes(array(
            'path' => function ($model, $gizmo) {
                if (0 !== strpos($model->fullPath, $gizmo['content_path']))
                    return false;

                $path = preg_replace("#^{$gizmo['content_path']}/?#", '', $model->fullPath);
                $path = preg_replace(array('/^\d+?\./', '/(\/)\d+?\./'), '\\1', $path);
                $path = trim($path, '/') ?: 'index';
                return $path;
            },
            'slug' => function ($model) {
                return preg_replace('#(.*?)/([^/]+)$#', '\\2', $model->path);
            },
            'permalink' => function ($model, $gizmo) {
                $url = rtrim($gizmo['options']['mount_point'], '/') . '/';
                if (!$model->isHomepage) {
                    $url .= $model->path;
                }
                return $url;
            },
            'url' => function ($model, $gizmo) {
                return $gizmo['request']->getBaseURL() . $model->permalink;
            },
            'uri' => function ($model, $gizmo) {
                return $gizmo['request']->getUriForPath($model->permalink);
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
            'root' => function ($model, $gizmo) {
                return $gizmo['cache']->getFolders($gizmo['content_path'], '/^\d+?\./');
            },
            'parent' => function ($model, $gizmo) {
                if ($model->isHomepage)
                    return null;

                $pathSegments = explode('/', $model->path);
                $parents = array();
                while (count($pathSegments) > 0) {
                    array_pop($pathSegments);
                    if ($parent = $gizmo['page'](join('/', $pathSegments)))
                        return $parent->fullPath;
                }
                return null;
            },
            'parents' => function ($model, $gizmo) {
                if ($model->isHomepage)
                    return array();
                
                $pathSegments = explode('/', $model->path);
                $parents = array();
                while (count($pathSegments) > 0) {
                    array_pop($pathSegments);
                    if ($parent = $gizmo['page'](join('/', $pathSegments)))
                        $parents[] = $parent->fullPath;
                }
                $parents = array_reverse($parents);
                return $parents;
            },
            'children' => function ($model, $gizmo) {
                return $gizmo['cache']->getFolders($model->fullPath, '/^\d+?\./');
            },
            'siblings' => function ($model, $gizmo) {
                if ($model->isHidden)
                    return array();
                
                # need to account for 'fake' index page
                $dir = $model->parent;
                if ($model->level === 1) {
                    $dir = preg_replace('#^(' . $gizmo['content_path'] . ')/+index$#', '\\1', $dir);
                }
                return $gizmo['cache']->getFolders($dir,
                    '/^\d+?\.(?!' . preg_quote($model->slug) . ')/');
            },
            'siblingsWitSelf' => function ($model, $gizmo) {
                if ($model->isHidden)
                    return array();

                # need to account for 'fake' index page
                $dir = $model->parent;
                if ($model->level === 1) {
                    $dir = preg_replace('#^(' . $gizmo['content_path'] . ')/+index$#', '\\1', $dir);
                }
                return $gizmo['cache']->getFolders($dir, '/^\d+?\./');
            },
            'closestSiblings' => function ($model) {
                if ($model->isHidden)
                    return array(null, null);
                
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
                    if (isset($keys[$keyIndexes[$path] - 1]))
                        $neighbors[] = $keys[$keyIndexes[$path] - 1];
                    else
                        $neighbors[] = $keys[count($keys) - 1];
                    
                    # next sibling
                    if (isset($keys[$keyIndexes[$path] + 1]))
                        $neighbors[] = $keys[$keyIndexes[$path] + 1];
                    else
                        $neighbors[] = $keys[0];
                }
                return !empty($neighbors) ? $neighbors : array(null, null);
            },
            'previousSibling' => function ($model) {
                return $model->closestSiblings[0];
            },
            'previousSiblings' => function ($model) {
                if ($model->isHidden)
                    return array();

                return array_slice($model->siblingsWitSelf, 0, $model->index - 1);
            },
            'nextSibling' => function ($model) {
                return $model->closestSiblings[1];
            },
            'nextSiblings' => function ($model) {
                if ($model->isHidden)
                    return array();

                $siblingsWitSelf = $model->siblingsWitSelf;
                return array_slice($siblingsWitSelf, $model->index, count($siblingsWitSelf));
            },
            'index' => function ($model) {
                $i = 0;
                $siblings = $model->siblingsWitSelf;
                foreach ($siblings as $sibling) {
                  ++$i;
                  if ($sibling === $model->fullPath)
                      return $i;
                }
                return 0;
            },
            'level' => function ($model) {
                return !$model->isHomepage
                    ? 1 + substr_count($model->path, '/')
                    : 0;
            },
            'isHomepage' => function ($model) {
                return $model->path === 'index';
            },
            'isVisible' => function ($model) {
                return !!preg_match('#/\d+\.(.+)$#', $model->fullPath);
            },
            'isHidden' => function ($model) {
                return !$model->isVisible;
            },
            'isFirst' => function ($model) {
                return !$model->isHidden && $model->index === 1;
            },
            'isLast' => function ($model) {
                return !$model->isHidden && $model->index === count($model->siblingsWitSelf);
            }
        ));
    }
}