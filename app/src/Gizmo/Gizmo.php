<?php

namespace Gizmo;

use Silex\Application;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Gizmo extends \Pimple
{
    const VERSION = '1.0b';
    
    public function __construct(Application $app)
    {
        $this['request'] = $app['request'];
        $this['app'] = $app;
        $this['options'] = array_merge(array(
            'path'           => $app['gizmo.path'],
            'mount_point'    => $app['gizmo.mount_point'],
            
            'timezone'       => 'UTC',
            'default_layout' => 'base.html.twig',
        ),
        $app['gizmo.options']);
        
        $defaultPaths = array(
            'app'       => null,
            'content'   => null,
            'templates' => null,
            'public'    => null,
            'cache'     => 'app/cache',
        );
        foreach ($defaultPaths as $name => $path) {
            $key = sprintf('%s_path', $name);
            if (!isset($this[$key])) {
                $this[$key] = $app['gizmo.path'] . '/' . ($path ?: $name);
            } else {
                $this[$key] = rtrim($this['key'], '/');
            }
        }
        // $configFile = $this['content_path']. '/config.yml';
        // if (file_exists($configFile)) {
        //     $app->register(new \Igorw\Silex\ConfigServiceProvider($configFile));
        // }
        
        date_default_timezone_set($this['options']['timezone']);

        $gizmo = $this;
        $this['cache'] = $this->share(function ($gizmo) {
            $cache = new Cache();
            $cache->addFolder($gizmo['content_path']);
            $cache->addFolder($gizmo['templates_path']);
            $cache->addFolder($gizmo['public_path']);
            return $cache;
        });
        $this['page_factory'] = $this->share(function ($gizmo) {
            return new PageFactory($gizmo);
        });
        $this['asset_factory'] = $this->share(function ($gizmo) {
            return new AssetFactory($gizmo);
        });
        $this['expand_path'] = $this->protect(function ($path) use ($gizmo) {
            $contentPath = $gizmo['content_path'];
            $path = trim($path, '/') ?: 'index';

            # Split the url and recursively unclean the parts into folder names
            $pathSegments = explode('/', $path);
            foreach ($pathSegments as $segment) {
                if ('_' === $segment{0}) return false;
                
                $items = $gizmo['cache']->getFilesOrFolders($contentPath,
                    '/^(\d+?\.)?('. preg_quote($segment) .')$/');
                if (empty($items)) return false;

                foreach ($items as $dir) {
                    $relativePath = substr($dir, strlen($contentPath));
                    $contentPath .= $relativePath;
                }
            }
            return $contentPath;
        });
        $this['model'] = $this->protect(function ($path) use ($gizmo) {
            if (0 !== strpos($path, $gizmo['content_path'])) {
                if (!$path = $gizmo['expand_path']($path)) {
                    return false;
                }
            }
            if (is_dir($path)) {
                return $gizmo['page']($path);
            }
            if (is_file($path)) {
                return $gizmo['asset']($path);
            }
            return false;
        });
        $this['page'] = $this->protect(function ($path) use ($gizmo) {
            return $gizmo['page_factory']->get($path);
        });
        $this['asset'] = $this->protect(function ($path) use ($gizmo) {
            return $gizmo['asset_factory']->get($path);
        });
        
        $this['imagine'] = $this->protect(function () {
            if (class_exists('Imagick', false)) {
                return new \Imagine\Imagick\Imagine();
            }
            return new \Imagine\Gd\Imagine();
        });
        
        $app->register(new \Silex\Provider\TwigServiceProvider(), array(
            'twig.path' => $this['templates_path'],
            'twig.options' => array(
                'base_template_class' => 'Gizmo\\Twig_Template',
                'strict_variables' => false,
                'cache' => $this['cache_path'] . '/templates',
            ),
        ));
        $app['twig'] = $app->share($app->extend('twig', function($twig, $app) use ($gizmo) {
            $twig->addExtension(new \Twig_Extensions_Extension_Text());
            $twig->addExtension(new \Twig_Extensions_Extension_Debug());
            $twig->addExtension(new Twig_Extension($gizmo));
            return $twig;
        }));
        $app->register(new \Silex\Provider\UrlGeneratorServiceProvider());
        
        $app['markdown.features'] = array(
            'entities' => true
        );
        $app->register(new \SilexExtension\MarkdownExtension());
    }

    public function renderModel(Model $model, $code = null)
    {
        // print_r($model->toArray()); die;
        
        $response = new Response(null, $code ?: 200);
        if ($model instanceof Page && $model->template) {
            $content = $this['app']['twig']->render($model->template, array('page' => $model));
            $response->setContent($content);
            return $response;
        }
        if ($model instanceof Asset) {
            $response->headers->set('Content-Type', $model->mimeType);
            $response->setContent($model->getContents());
            return $response;
        }
        return false;
    }
    
    public function dispatch($path = null)
    {
        if ($model = $this['model']($path)) {
            $this['current_model'] = $model;
            if ($rendered = $this->renderModel($model)) {
                return $rendered;
            }
        }
        return $this->dispatch404();
    }

    public function dispatch404()
    {
        if ($page404 = $this['page']('404')) {
            $this['current_model'] = $page404;
            if ($rendered = $this->renderModel($page404, 404)) {
                return $rendered;
            }
        }
        $file404 = $this['public_path'] . '/404.html';
        if (file_exists($file404)) {
            return file_get_contents($file404);
        }
        $this['app']->abort(404, 'Sorry, the requested page could not be found.');
    }
}
