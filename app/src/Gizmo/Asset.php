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
            'path' => function ($model, $gizmo) {
                if (0 !== strpos($model->fullPath, $gizmo['content_path']))
                    return false;

                $path = preg_replace("#^{$gizmo['content_path']}/?#", '', $model->fullPath);
                $lastSegment = null;
                $path = preg_replace_callback('#(/[^/]+)$#', function ($segment) use (&$lastSegment) {
                    $lastSegment = $segment[0];
                    return '';
                }, $path);
                $path = preg_replace(array('/^\d+?\./', '/(\/)\d+?\./'), '\\1', $path);
                return $path . $lastSegment;
            },
            'modelName' => function ($asset) {
                $modelName = explode('\\', strtolower(get_class($asset)));
                return $modelName[count($modelName) - 1];
            },
            'children' => function ($asset, $gizmo) {
                return $gizmo['cache']->getFiles(preg_replace('/\.([^.]+)$/', '', $asset->fullPath),
                    '/^\d+?\.(.+?)\.(' . join('|', $asset->getSupportedExtensions()) . ')$/');
            },
            'siblings' => function ($asset, $gizmo) {
                if ($asset->isHidden)
                    return array();
                
                return $gizmo['cache']->getFiles($asset->parent,
                    '/^(?!' . preg_quote($asset->slug) . ')\d+?\.(.+?)\.(' . join('|', $asset->getSupportedExtensions()) . ')$/');
            },
            'siblingsWitSelf' => function ($asset, $gizmo) {
                if ($asset->isHidden)
                    return array();
                
                return $gizmo['cache']->getFiles($asset->parent,
                    '/^\d+?\.(.+?)\.(' . join('|', $asset->getSupportedExtensions()) . ')$/');
            },
            'isHidden' => function ($model) {
                return !preg_match('#/\d+\.([^\/.]+)\.([^\/.]+)$#', $model->fullPath);
            },
        ));
    }
}
