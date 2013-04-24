<?php

namespace Gizmo;

use Symfony\Component\Finder\Finder;

class Cache
{
    protected
        $store = array();
    
    protected
        $lastMTime = null,
        $hash = null;

    public function getLastModifiedTime()
    {
        if (!$this->lastMTime) {
            foreach ($this->store as $dir => $items) {
                foreach ($items as $item) {
                    if ($this->lastMTime < $item['mtime']) {
                        $this->lastMTime = $item['mtime'];
                    }
                }
            }
        }
        return $this->lastMTime;
    }

    public function getHash()
    {
        if (!$this->hash) {
            $this->hash = md5(serialize($this->store));
        }
        return $this->hash;
    }

    public function touch()
    {
        $this->lastMTime = $this->hash = null;
    }

    public function addFolder($dir)
    {
        $dir = rtrim($dir, '/');
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
                'path'      => (string) $item,
                'is_folder' => (int) $item->isDir(),
                'mtime'     => $item->getMTime(),
            );
        }
        $this->touch();
    }

    public function getFilesOrFolders($dir, $regex = null, $with_files = true, $with_folders = true)
    {
        $dir = rtrim($dir, '/');
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
        natcasesort($items);
        $items = array_values($items);
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
