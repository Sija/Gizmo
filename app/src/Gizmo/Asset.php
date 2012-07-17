<?php

namespace Gizmo;

class Asset extends Model
{
    public function setData(array $data)
    {
        parent::setData($data);
        
        if (class_exists('finfo')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            if ($finfo) {
                $this->mimeType = $finfo->file($this->fullPath);
            }
        }
    }
    /**
     *
     */
    public function getContents()
    {
        return file_get_contents($this->fullPath);
    }
    
    /**
     *
     */
    public static function getSupportedExtensions()
    {
        return array();
    }
    
    /**
     *
     */
    protected function setDefaultAttributes()
    {
        parent::setDefaultAttributes();
        
        $this->addAttributes(array(
            'permalinkRaw' => function ($asset, $app) {
                # TODO
                return substr($asset->fullPath, strlen($app['gizmo.content_path']));
            },
            'uriRaw' => function ($asset, $app) {
                return $app['request']->getUriForPath($asset->permalinkRaw);
            },
            'urlRaw' => function ($asset, $app) {
                return $app['request']->getBaseURL() . $asset->permalinkRaw;
            },
            'modelName' => function ($asset) {
                $modelName = explode('\\', strtolower(get_class($asset)));
                return $modelName[count($modelName) - 1];
            },
            'children' => function ($asset, $app) {
                return $app['gizmo.cache']->getFiles(preg_replace('/\.([^.]+)$/', '', $asset->fullPath),
                    '/^\d+?\.(.+?)\.(' . join('|', $asset->getSupportedExtensions()) . ')$/');
            },
            'siblings' => function ($asset, $app) {
                return $app['gizmo.cache']->getFiles($asset->parent,
                    '/^\d+?\.(?!' . preg_quote($asset->slug) . ').+?\.(' . join('|', $asset->getSupportedExtensions()) . ')$/');
            },
            'siblingsWitSelf' => function ($asset, $app) {
                return $app['gizmo.cache']->getFiles($asset->parent,
                    '/^\d+?\.(.+?)\.(' . join('|', $asset->getSupportedExtensions()) . ')$/');
            }
        ));
    }
}
