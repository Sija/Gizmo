<?php

namespace Gizmo;

class PageFactory extends ModelFactory
{
    public function modelFromFullPath($full_path)
    {
        if (!is_dir($full_path)) {
            return false;
        }
        $meta_file = $this->findMetaFile($full_path);
        if ($meta_file) {
            $page = new Page($this->app, array(
                'fullPath' => $full_path,
                'metaFile' => $meta_file
            ));
            return $page;
        }
        return false;
    }
    
    protected function findMetaFile($full_path)
    {
        $meta_files = $this->app['gizmo.cache']->getFiles($full_path, '/^.(?<!_)(.+?)\.yml$/');
        if (empty($meta_files)) {
            return null;
        }
        return $meta_files[0];
    }
}
