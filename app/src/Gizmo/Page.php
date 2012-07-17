<?php

namespace Gizmo;

use Symfony\Component\Yaml\Yaml;

class Page extends Model
{
    protected
        $_requiredKeys = array('fullPath', 'metaFile');
    
    public function setData(array $data)
    {
        parent::setData($data);
        parent::addData($this->meta);
    }
    
    protected function setDefaultAttributes()
    {
        parent::setDefaultAttributes();
        
        $this->addAttributes(array(
            'files' => function ($page, $app) {
                return $app['gizmo.cache']->getFiles($page->fullPath,
                    '/(?<!thumb)\.(?!yml)([\w\d]+?)$/i');
            }
        ));
        $asset_types = $this->_app['gizmo.asset_factory']->getAssetMap();
        
        foreach ($asset_types as $class => $extensions) {
            $key = sprintf('%ss', strtolower(preg_replace('/^((.+?)\\\)?(.+?)$/', '\\3', $class)));
            $this->addAttributes(array(
                $key => function ($page, $app) use ($extensions) {
                    return $app['gizmo.cache']->getFiles($page->fullPath,
                        '/(?<!thumb)\.(' . implode('|', $extensions) . ')$/i');
                }
            ));
        }

        $this->addAttributes(array(
            'format' => function ($page, $app) {
                return $app['request']->getRequestFormat();
            },
            'modelName' => function ($page) {
                $modelName = preg_replace('/\.yml$/', '', basename($page->metaFile));
                $modelName = preg_replace('/([^.]+\.)?([^.]+)$/', '\\2', $modelName);
                return $modelName;
            },
            'meta' => function ($page, $app) {
                $files = array();
                $path_parts = explode('/', $page->path);
                for ($i = count($path_parts); $i >= 0; --$i) {
                    $path = realpath($page->fullPath . str_repeat('/..', $i));
                    $files += $app['gizmo.cache']->getFiles($path, '/^_shared\.yml$/');
                }
                $meta_data = Yaml::parse($page->metaFile);
                $data = array();
                foreach ($files as $file) {
                    if ($loaded = Yaml::parse($file)) {
                        $data = array_merge($data, $loaded);
                    }
                }
                if (!empty($meta_data)) {
                    $data = array_merge($data, $meta_data);
                }
                return $data;
            },
            'template' => function ($page, $app) {
                $files = $app['gizmo.cache']->getFiles($app['gizmo.templates_path'], 
                    sprintf('/%s(.*?)\.%s\.twig/i', $page->modelName, $page->format));

                if (!empty($files)) {
                    return basename($files[0]);
                }
                if ($page->path != '404') {
                    $files = $app['gizmo.cache']->getFiles($app['gizmo.templates_path'], 
                        sprintf('/%s(.*?)\.(.+?)\.twig/i', $page->modelName));
                    
                    if (!empty($files)) {
                        return null;
                    }
                }
                return $app['gizmo.default_layout'];
            },
            'updated' => function ($page) {
                return filemtime($page->metaFile);
            },
            'thumb' => function ($page, $app) {
                $thumbnails = $app['gizmo.cache']->getFiles($page->fullPath, '/thumb\.(gif|png|jpe?g)$/i');
                return empty($thumbnails) ? false : $thumbnails[0];
            }
        ));
        $this->addDynamicAttributes(array(
            'isCurrent' => function ($page, $app) {
                $requestUri = $app['request']->getRequestUri();
                $baseUrl = $app['request']->getBaseUrl();

                if ('index' == $page->path) {
                    return $requestUri == $baseUrl;
                } else {
                    return $requestUri == $baseUrl . '/' . $page->path;
                }
            },
            'inPath' => function ($page, $app) {
                $requestUri = $app['request']->getRequestUri();
                $baseUrl = $app['request']->getBaseUrl();

                if ('index' === $page->path) {
                    return $requestUri == $baseUrl || $requestUri == $baseUrl . '/index';
                } else {
                    return preg_match("#(/{$page->slug}$|{$page->slug}/)#", $requestUri);
                }
            }
        ));
    }
}