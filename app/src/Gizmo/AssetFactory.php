<?php

namespace Gizmo;

use Symfony\Component\Finder\Finder;

class AssetFactory extends ModelFactory
{
    protected $assetMap = array();
    protected $extensionMap = array();
    
    public function __construct(\Silex\Application $app)
    {
        parent::__construct($app);
        self::loadAssetClasses();
        
        foreach (get_declared_classes() as $class) {
            if (get_parent_class($class) == 'Gizmo\\Asset') {
                $extensions = $class::getSupportedExtensions();
                foreach ($extensions as $extension) {
                    $this->extensionMap[$extension][] = $class;
                }
                $this->assetMap[$class] = $extensions;
            }
        }
    }

    public function modelFromFullPath($full_path)
    {
        if (!is_file($full_path)) {
            return false;
        }
        $asset_class = $this->findAssetClass($full_path);
        if ($asset_class) {
            $asset = new $asset_class($this->app, array(
                'fullPath' => $full_path
            ));
            return $asset;
        }
        return false;
    }

    protected function findAssetClass($full_path)
    {
        $file_extension = strtolower(pathinfo($full_path, PATHINFO_EXTENSION));
        if (empty($file_extension) || !is_file($full_path)) {
            return false;
        }
        $asset_class = 'Gizmo\\Asset';
        if (isset($this->extensionMap[$file_extension])) {
            $asset_classes = $this->extensionMap[$file_extension];
            $asset_class = $asset_classes[count($asset_classes) - 1];
            return $asset_class;
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
    
    public function getAssetMap()
    {
        return $this->assetMap;
    }
}
