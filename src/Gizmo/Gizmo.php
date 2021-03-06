<?php

namespace Gizmo;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Gizmo extends \Pimple
{
    const VERSION = '1.0b';
    
    public function __construct(\Silex\Application $app)
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
            'content'   => null,
            'templates' => null,
            'public'    => null,
            'cache'     => null,
        );
        foreach ($defaultPaths as $name => $path) {
            $key = sprintf('%s_path', $name);
            if (isset($app['gizmo.' . $key])) {
                $this[$key] = rtrim($app['gizmo.' . $key], '/');
            } else {
                $this[$key] = $app['gizmo.path'] . '/' . ($path ?: $name);
            }
        }
        if (!is_dir($this['cache_path'])) {
            if (!@mkdir($this['cache_path'], 0777)) {
                throw new \RuntimeException(sprintf('Cannot create cache directory (%s).', $this['cache_path']));
            }
        }
        // $configFile = $this['content_path']. '/config.yml';
        // if (file_exists($configFile)) {
        //     $app->register(new \Igorw\Silex\ConfigServiceProvider($configFile));
        // }
        
        date_default_timezone_set($this['options']['timezone']);
        
        $gizmo = $this;
        
        $app->register(new \Silex\Provider\TwigServiceProvider(), array(
            'twig.path' => $this['templates_path'],
            'twig.options' => array(
                'auto_reload'           => true,
                'strict_variables'      => false,
                'base_template_class'   => 'Gizmo\\Twig_Template',
                'cache'                 => $this['cache_path'] . '/templates',
            ),
        ));
        $app['twig'] = $app->share($app->extend('twig', function($twig, $app) use ($gizmo) {
            if ($app['debug']) {
                $twig->addExtension(new \Twig_Extensions_Extension_Debug());
            }
            $twig->addExtension(new \Twig_Extensions_Extension_Text());
            $twig->addExtension(new Twig_Extension($gizmo));
            return $twig;
        }));
        $app->register(new \Silex\Provider\UrlGeneratorServiceProvider());
        $app->register(new \Markdown\Silex\Service\MarkdownSilexService());
        $app->register(new \Smartypants\Silex\Service\SmartypantsSilexService());
        
        $app['request_local?'] = $this->share(function ($app) {
            return in_array($app['request']->server->get('REMOTE_ADDR'), array('127.0.0.1', '::1'))
                || preg_match('/(^localhost$|\.(local|dev)$)/', $app['request']->getHost());
        });
        $app->error(function (\Exception $e) use ($app, $gizmo) {
            if ($app['request_local?']) {
                return;
            }
            if ($dispatched = $gizmo->dispatch500($e)) {
                return $dispatched;
            }
            if (!$app['debug']) return false;
        });
        
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
        
        $this['model'] = $this->protect(function ($path) use ($gizmo) {
            if (0 !== strpos($path, $gizmo['content_path'])) {
                if (!$path = $gizmo->expandPath($path))
                    return false;
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
        
        $this['imagine'] = function () {
            if (class_exists('Imagick', false)) {
                return new \Imagine\Imagick\Imagine();
            }
            return new \Imagine\Gd\Imagine();
        };
    }

    public function expandPath($path)
    {
        $contentPath = $this['content_path'];
        $path = trim($path, '/') ?: 'index';

        # Split the url and recursively unclean the parts into folder names
        $pathSegments = explode('/', $path);
        foreach ($pathSegments as $segment) {
            if ('_' === $segment{0}) return false;
            
            $items = $this['cache']->getFilesOrFolders($contentPath,
                '/^'. (!strpos($segment, '.') ? '(\d+?\.)?' : '') .'('. preg_quote($segment) .')$/');
            
            switch (count($items)) {
                case 0: return false;
                case 1:
                    $relativePath = substr($items[0], strlen($contentPath));
                    $contentPath .= $relativePath;
                    break;
                default:
                    throw new \Exception(sprintf('There are %d items with name "%s"', count($items), $segment));
            }
        }
        return $contentPath;
    }
    
    public function renderModel(Model $model, $code = null)
    {
        // print_r($model->toArray()); die;
        
        $response = new Response();
        $response->setStatusCode($code ?: 200);
        $response->setPublic();
        
        $date = new \DateTime('@' . $model->updated);
        if ($model instanceof Asset) {
            // $response->setMaxAge(3600);
        }
        $response->setETag($model->etag);
        $response->setLastModified($date);
        if ($response->isNotModified($this['request'])) {
            return $response;
        }
        return $model->renderWith($response);
    }
    
    public function dispatch($path = null)
    {
        if ($model = $this['model']($path)) {
            $this['dispatched_model'] = $model;
            if ($model instanceof Page) {
                if (isset($model['_redirect_to'])) {
                    return $this['app']->redirect($model['_redirect_to']);
                }
                if (isset($model['_render_with'])) {
                    $forwarded_model_path = $model['_render_with'];
                    if ('/' !== $forwarded_model_path{0}) {
                        $forwarded_model_path = $model->path . '/' . $forwarded_model_path;
                    }
                    return $this->dispatch($forwarded_model_path);
                }
            }
            if ($rendered = $this->renderModel($model)) {
                return $rendered;
            }
        }
        return $this->dispatch404();
    }

    public function dispatch404()
    {
        if ($page404 = $this['page']('404')) {
            $this['dispatched_model'] = $page404;
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
    
    public function dispatch500(\Exception $e = null)
    {
        if ($page500 = $this['page']('500')) {
            $page500['e'] = $e;
            $this['dispatched_model'] = $page500;
            if ($rendered = $this->renderModel($page500, 500)) {
                return $rendered;
            }
        }
        $file500 = $this['public_path'] . '/500.html';
        if (file_exists($file500)) {
            return file_get_contents($file500);
        }
    }
}
