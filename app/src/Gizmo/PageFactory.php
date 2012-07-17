<?php

namespace Gizmo;

class PageFactory extends ModelFactory
{
    public function modelFromFullPath($fullPath)
    {
        if (!is_dir($fullPath)) {
            return false;
        }
        $metaFile = $this->findMetaFile($fullPath);
        if ($metaFile) {
            $page = new Page($this->app, array(
                'fullPath' => $fullPath,
                'metaFile' => $metaFile
            ));
            return $page;
        }
        return false;
    }
    
    protected function findMetaFile($fullPath)
    {
        $metaFiles = $this->app['gizmo.cache']->getFiles($fullPath, '/^.(?<!_)(.+?)\.yml$/');
        if (empty($metaFiles)) {
            return null;
        }
        return $metaFiles[0];
    }
}
