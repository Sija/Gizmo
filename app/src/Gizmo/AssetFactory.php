<?php

namespace Gizmo;

use Symfony\Component\Finder\Finder;

class AssetFactory extends ModelFactory
{
    protected
        $assetMap = array(),
        $extensionMap = array();
    
    public function __construct(Gizmo $gizmo)
    {
        parent::__construct($gizmo);
        self::loadAssetClasses();
        
        foreach (get_declared_classes() as $class) {
            if (get_parent_class($class) == 'Gizmo\\Asset') {
                $extensions = $class::getSupportedExtensions();
                foreach ($extensions as $ext) {
                    $this->extensionMap[$ext][] = $class;
                }
                $this->assetMap[$class] = $extensions;
            }
        }
    }
    
    public function getAssetMap()
    {
        return $this->assetMap;
    }
    
    public function modelFromFullPath($fullPath)
    {
        if (!is_file($fullPath)) {
            return false;
        }
        $assetClass = $this->findAssetClass($fullPath);
        if ($assetClass) {
            $asset = new $assetClass($this->gizmo, array(
                'fullPath' => $fullPath
            ));
            return $asset;
        }
        return false;
    }
    
    protected static function loadAssetClasses()
    {
        static $loaded = false;
        if (!$loaded) {
            $it = Finder::create()
                ->depth(0)
                ->name('/\.php$/')
                ->in(__DIR__ . '/Asset');

            foreach ($it as $file) {
                require_once $file;
            }
            $loaded = true;
        }
    }
    
    protected function findAssetClass($path)
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!$ext) return false;

        $assetClass = 'Gizmo\\Asset';
        if (isset($this->extensionMap[$ext])) {
            $assetClasses = $this->extensionMap[$ext];
            $assetClass = $assetClasses[count($assetClasses) - 1];
            return $assetClass;
        }
        return false;
    }
}
