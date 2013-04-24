<?php

namespace Gizmo\Silex;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Silex\ControllerProviderInterface;
use Silex\ControllerCollection;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Provider implements ServiceProviderInterface, ControllerProviderInterface
{
    public function register(Application $app)
    {
        $app['gizmo'] = $app->share(function ($app) {
            return new \Gizmo\Gizmo($app);
        });
    }

    public function boot(Application $app)
    {
        if (isset($app['gizmo.mount_point'])) {
            $app->mount($app['gizmo.mount_point'], $this);
        }
    }
    
    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];
        
        // Tumbnailer route
        $controllers->get('/thumb/{width}x{height}/{path}', function ($width, $height, $path) use ($app) {
            $gizmo = $app['gizmo'];
            
            $outbound = $app['request']->query->get('outbound') ?: false;
            $mode = !$outbound ?
                \Imagine\Image\ImageInterface::THUMBNAIL_INSET :
                \Imagine\Image\ImageInterface::THUMBNAIL_OUTBOUND;

            $quality = (int) $app['request']->query->get('quality');
            $quality = max(0, min(100, $quality ?: 75)) ?: 75;
            
            $format = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            
            $cacheDir = $gizmo['cache_path'] . '/thumbs/';
            if (!is_dir($cacheDir)) {
                if (!@mkdir($cacheDir, 0777, true)) {
                    throw new \RuntimeException(sprintf('Cannot create thumbnails cache directory (%s).', $cacheDir));
                }
            }
            $key = md5("{$width}x{$height}|q{$quality}|m{$mode}|{$path}") . ".{$format}";
            $cachePath = $cacheDir . $key;
            
            if (!file_exists($cachePath)) {
                $image = $gizmo['asset']($path);

                if (!$image || 0 !== strpos($image->mimeType, 'image/')) {
                    return new Response(null, 404);
                }
                $imagine = $gizmo['imagine'];
                $imagine->open($image->fullPath)
                    ->thumbnail(new \Imagine\Image\Box($width, $height), $mode)
                    ->save($cachePath, array('quality' => $quality));
            }
            $cachedImage = $gizmo['asset_factory']->modelFromFullPath($cachePath);
            if ($cachedImage) {
                return $gizmo->renderModel($cachedImage);
            }
            return new Response(null, 404);
        })
          ->bind('thumb')
          ->assert('width', '\d+')
          ->assert('height', '\d+')
          ->assert('path', '.+?');
        
        // Assets route
        $controllers->get('/-/{path}', function ($path) use ($app) {
            $gizmo = $app['gizmo'];
            
            $path = $gizmo['public_path'] . '/' . $path;
            $asset = $gizmo['asset_factory']->modelFromFullPath($path);
            if ($asset) {
                return $gizmo->renderModel($asset);
            }
            return new Response(null, 404);
        })
          ->bind('public')
          ->assert('path', '.+?');
        
        // Bind default catch-all route for pages
        $controllers->match('/{path}.{_format}', function ($path) use ($app) {
            return $app['gizmo']->dispatch($path);
        })
          ->bind('page')
          ->assert('path', '.+?')
          ->assert('_format', 'html|json|xml|rss|rdf|atom')
          ->value('_format', 'html');

        // Homepage route
        $controllers->match('/', function () use ($app) {
            return $app['gizmo']->dispatch();
        })
          ->bind('homepage');
        
        return $controllers;
    }
}
