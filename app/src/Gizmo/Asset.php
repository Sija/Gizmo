<?php

namespace Gizmo;

use Symfony\Component\HttpFoundation\Response;

class Asset extends Model
{
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
    public function getContents()
    {
        return file_get_contents($this->fullPath);
    }
    
    /**
     *
     */
    public function renderWith(Response $response)
    {
        $response->headers->set('Content-Type', $this->mimeType);
        $response->setContent($this->getContents());
        return $response;
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
            'mimeType' => function ($asset) {
                $mime = 'application/octet-stream';
                if (class_exists('finfo')) {
                    $finfo = new \finfo(FILEINFO_MIME_TYPE);
                    if ($finfo)
                        $mime = $finfo->file($asset->fullPath);
                }
                if (0 === strpos($mime, 'text/')) {
                    switch ($asset->ext) {
                        case 'js':  return 'application/javascript';
                        case 'css': return 'text/css';
                    }
                }
                return $mime;
            },
            'ext' => function ($asset) {
                return strtolower(pathinfo($asset->fullPath, PATHINFO_EXTENSION));
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
            'isVisible' => function ($model) {
                return !!preg_match('#/\d+\.([^\/.]+)\.([^\/.]+)$#', $model->fullPath);
            },
        ));
    }
}
