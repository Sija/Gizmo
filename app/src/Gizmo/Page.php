<?php

namespace Gizmo;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Yaml\Yaml;

class Page extends Model implements \ArrayAccess
{
    protected
        $requiredKeys = array('fullPath', 'metaFile');

    public function renderWith(Response $response)
    {
        if ($this->template) {
            $content = $this->gizmo['app']['twig']->render($this->template, array('page' => $this));
            $response->setContent($content);
            return $response;
        }
        return false;
    }
    
    public function offsetGet($key)
    {
        return isset($this->meta[$key]) ? $this->meta[$key] : null;
    }

    public function offsetSet($key, $value)
    {
        $meta = $this->meta;
        $meta[$key] = $value;
        $this->meta = $meta;
        return $value;
    }

    public function offsetExists($key)
    {
        return isset($this->meta[$key]);
    }

    public function offsetUnset($key)
    {
        unset($this->meta[$key]);
    }
    
    protected function setDefaultAttributes()
    {
        parent::setDefaultAttributes();
        
        $assetTypes = $this->gizmo['asset_factory']->getAssetMap();
        foreach ($assetTypes as $class => $extensions) {
            $key = sprintf('%ss', strtolower(preg_replace('/^((.+?)\\\)?(.+?)$/', '\\3', $class)));
            $this->addAttributes(array(
                $key => function ($page, $gizmo) use ($extensions) {
                    return $gizmo['cache']->getFiles($page->fullPath,
                        '/^(?!thumb).(?<!_)(.+?)\.(' . join('|', $extensions) . ')$/i');
                }
            ));
        }
        $this->addAttributes(array(
            'files' => function ($page, $gizmo) {
                return $gizmo['cache']->getFiles($page->fullPath,
                    '/^(?!thumb).(?<!_)(.+?)\.(?!yml)([\w\d]+?)$/i');
            }
        ));
        $this->addAttributes(array(
            'format' => function ($page, $gizmo) {
                return $gizmo['request']->getRequestFormat();
            },
            'modelName' => function ($page) {
                $modelName = preg_replace('/\.yml$/', '', basename($page->metaFile));
                $modelName = preg_replace('/([^.]+\.)?([^.]+)$/', '\\2', $modelName);
                return $modelName;
            },
            'sharedMeta' => function ($page, $gizmo) {
                $sharedFiles = array();
                # need to take account for 'fake' level 0 reserved for index page
                for ($i = $page->level ?: 1; $i >= 0; --$i) {
                    $sharedFiles = array_merge($sharedFiles, $gizmo['cache']->getFiles(
                        realpath($page->fullPath . str_repeat('/..', $i)),
                        '/^_shared\.yml$/'));
                }
                $data = array();
                foreach ($sharedFiles as $file) {
                    if ($loadedData = Yaml::parse($file))
                        $data = array_merge($data, $loadedData);
                }
                return $data;
            },
            'meta' => function ($page, $gizmo) {
                $data = $page->sharedMeta;
                $modelMeta = Yaml::parse($page->metaFile);
                if (!empty($modelMeta)) {
                    $data = array_merge($data, $modelMeta);
                }
                return $data;
            },
            'template' => function ($page, $gizmo) {
                $files = $gizmo['cache']->getFiles($gizmo['templates_path'], 
                    sprintf('/%s(.*?)\.%s\.twig/i', $page->modelName, $page->format));

                if (!empty($files)) {
                    return basename($files[0]);
                }
                if ($page->path != '404') {
                    $files = $gizmo['cache']->getFiles($gizmo['templates_path'], 
                        sprintf('/%s(.*?)\.(.+?)\.twig/i', $page->modelName));
                    
                    if (!empty($files))
                        return null;
                }
                return $gizmo['options']['default_layout'];
            },
            'updated' => function ($page) {
                return filemtime($page->metaFile);
            },
            'thumb' => function ($page, $gizmo) {
                $thumbnails = $gizmo['cache']->getFiles($page->fullPath, '/^thumb\.(gif|png|jpe?g)$/i');
                return empty($thumbnails) ? null : $thumbnails[0];
            }
        ));
        $this->addDynamicAttributes(array(
            'isCurrent' => function ($page, $gizmo) {
                return $gizmo['dispatched_model']->isEqual($page);
            },
            'inPath' => function ($page, $gizmo) {
                return $page->isCurrent || in_array($page->fullPath, $gizmo['dispatched_model']->parents);
            },
            'inRequestPath' => function ($page, $gizmo) {
                $requestUri = rtrim($gizmo['request']->getRequestUri(), '/');
                $baseUrl = $gizmo['request']->getBaseUrl();

                # FIXME: find a better way
                if ($page->isHomepage) {
                    return $requestUri == $baseUrl || 0 === strpos($requestUri, rtrim($page->permalink, '/') . '/index');
                }
                return 0 === strpos($requestUri, $page->permalink . (
                    strlen($requestUri) === strlen($page->permalink) ? '' : '/'
                ));
            }
        ));
    }
}