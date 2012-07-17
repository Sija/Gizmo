<?php

namespace Gizmo;

use Symfony\Component\Finder\Finder;

class Cache
{
    protected $store = array();
    
    public function addFolder($dir)
    {
        $it = Finder::create()
          ->depth(0)
          ->in($dir);
        
        foreach ($it as $item) {
            $filename = $item->getBasename();
            if (!$item->isReadable() || '.' === $filename{0}) {
                continue;
            }
            if ($item->isDir()) {
                $this->addFolder((string) $item);
            }
            $this->store[$dir][$item->getBasename()] = array(
                'path' => (string) $item,
                'is_folder' => $item->isDir() ? 1 : 0,
                'mtime' => $item->getMTime(),
            );
        }
    }

    public function getFilesOrFolders($dir, $regex = null, $with_files = true, $with_folders = true)
    {
        if (!isset($this->store[$dir])) {
            return array();
        }
        $items = array();
        foreach ($this->store[$dir] as $name => $item) {
            if (!$regex || preg_match($regex, $name)) {
                if ($item['is_folder']) {
                    if ($with_folders) $items[] = $item['path'];
                } else {
                    if ($with_files) $items[] = $item['path'];
                }
            }
        }
        return $items;
    }

    public function getFiles($dir, $regex = null)
    {
        return $this->getFilesOrFolders($dir, $regex, true, false);
    }
    
    public function getFolders($dir, $regex = null)
    {
        return $this->getFilesOrFolders($dir, $regex, false, true);
    }
}
