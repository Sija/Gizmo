<?php

namespace Gizmo;

use Symfony\Component\Yaml\Yaml;

class Page extends Model
{
    protected
        $requiredKeys = array('fullPath', 'metaFile');
    
    public function setData(array $data)
    {
        parent::setData($data);
        parent::addData($this->modelMeta);
    }
    
    protected function setDefaultAttributes()
    {
        parent::setDefaultAttributes();
        
        $assetTypes = $this->app['gizmo.asset_factory']->getAssetMap();
        foreach ($assetTypes as $class => $extensions) {
            $key = sprintf('%ss', strtolower(preg_replace('/^((.+?)\\\)?(.+?)$/', '\\3', $class)));
            $this->addAttributes(array(
                $key => function ($page, $app) use ($extensions) {
                    return $app['gizmo.cache']->getFiles($page->fullPath,
                        '/^(?!thumb).(?<!_)(.+?)\.(' . join('|', $extensions) . ')$/i');
                }
            ));
        }
        $this->addAttributes(array(
            'files' => function ($page, $app) {
                return $app['gizmo.cache']->getFiles($page->fullPath,
                    '/^(?!thumb).(?<!_)(.+?)\.(?!yml)([\w\d]+?)$/i');
            }
        ));
        $this->addAttributes(array(
            'format' => function ($page, $app) {
                return $app['request']->getRequestFormat();
            },
            'modelName' => function ($page) {
                $modelName = preg_replace('/\.yml$/', '', basename($page->metaFile));
                $modelName = preg_replace('/([^.]+\.)?([^.]+)$/', '\\2', $modelName);
                return $modelName;
            },
            'modelMeta' => function ($page, $app) {
                $sharedFiles = array();
                for ($i = $page->level; $i >= 0; --$i) {
                    $sharedFiles = array_merge($sharedFiles, $app['gizmo.cache']->getFiles(
                        realpath($page->fullPath . str_repeat('/..', $i)),
                        '/^_shared\.yml$/'));
                }
                $data = array();
                foreach ($sharedFiles as $file) {
                    if ($loadedData = Yaml::parse($file)) {
                        $data = array_merge($data, $loadedData);
                    }
                }
                $modelMeta = Yaml::parse($page->metaFile);
                if (!empty($modelMeta)) {
                    $data = array_merge($data, $modelMeta);
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
                $requestUri = rtrim($app['request']->getRequestUri(), '/');
                $baseUrl = $app['request']->getBaseUrl();

                if ('index' == $page->path) {
                    return $requestUri == $baseUrl;
                } else {
                    return $requestUri == $baseUrl . $page->permalink;
                }
            },
            'inPath' => function ($page, $app) {
                $requestUri = rtrim($app['request']->getRequestUri(), '/');
                $baseUrl = $app['request']->getBaseUrl();

                if ('index' === $page->path) {
                    return $requestUri == $baseUrl;
                } else {
                    return preg_match("#(/{$page->slug}$|{$page->slug}/)#", $requestUri);
                }
            }
        ));
    }
}