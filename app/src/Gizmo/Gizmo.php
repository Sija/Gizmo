<?php

namespace Gizmo;

use Silex\Application;
use Silex\ServiceProviderInterface;
use Silex\ControllerProviderInterface;
use Silex\ControllerCollection;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class Gizmo implements ServiceProviderInterface, ControllerProviderInterface
{
    const VERSION = '1.0b';
    
    protected
        $app = null;
    
    public function register(Application $app)
    {
        $this->app = $app;
        
        $self = $this;
        $app['gizmo'] = $app->share(function () use ($self) {
            return $self;
        });
        $app['gizmo.cache'] = $app->share(function ($app) {
            $cache = new Cache();
            $cache->addFolder($app['gizmo.content_path']);
            $cache->addFolder($app['gizmo.templates_path']);
            $cache->addFolder($app['gizmo.public_path']);
            return $cache;
        });
        $app['gizmo.page_factory'] = $app->share(function ($app) {
            return new PageFactory($app);
        });
        $app['gizmo.asset_factory'] = $app->share(function ($app) {
            return new AssetFactory($app);
        });
        $app['gizmo.expand_path'] = $app->protect(function ($path) use ($app) {
            $content_path = $app['gizmo.content_path'];
            $path = trim($path, '/');
            if ($path) {
                # Split the url and recursively unclean the parts into folder names
                $path_parts = explode('/', $path);
                foreach ($path_parts as $part) {
                    if ('_' === $part{0}) return false;
                    
                    $items = $app['gizmo.cache']->getFilesOrFolders($content_path,
                        '/^(\d+?\.)?('. preg_quote($part) .')$/');
                    if (empty($items)) return false;

                    foreach ($items as $dir) {
                        $relative_path = substr($dir, strlen($content_path));
                        $content_path .= $relative_path;
                    }
                }
            }
            return $content_path;
        });
        $app['gizmo.model'] = $app->protect(function ($path) use ($app) {
            if (0 !== strpos($path, $app['gizmo.content_path'])) {
                if (!$path = $app['gizmo.expand_path']($path)) {
                    return false;
                }
            }
            if (is_dir($path)) {
                return $app['gizmo.page']($path);
            }
            if (is_file($path)) {
                return $app['gizmo.asset']($path);
            }
            return false;
        });
        $app['gizmo.page'] = $app->protect(function ($path) use ($app) {
            return $app['gizmo.page_factory']->get($path);
        });
        $app['gizmo.asset'] = $app->protect(function ($path) use ($app) {
            return $app['gizmo.asset_factory']->get($path);
        });
    }

    public function boot(Application $app)
    {
        date_default_timezone_set(isset($app['gizmo.timezone']) ? $app['gizmo.timezone'] : 'UTC');
        
        $app['gizmo.cache_path'] = $app['gizmo.app_path'] . '/cache';
        
        $app->register(new \Silex\Provider\TwigServiceProvider(), array(
            'twig.path' => $app['gizmo.templates_path'],
            'twig.options' => array(
                'base_template_class' => 'Gizmo\\Twig_Template',
                'strict_variables' => false,
                'cache' => $app['gizmo.cache_path'] . '/templates',
            ),
        ));
        $app['twig']->addExtension(new \Twig_Extensions_Extension_Text());
        $app['twig']->addExtension(new \Twig_Extensions_Extension_Debug());
        $app['twig']->addExtension(new Twig_Extension_Gizmo($app));
        
        $app->register(new \SilexExtension\AsseticExtension(), array(
            'assetic.path_to_web' => $app['gizmo.assets_path'],
            'assetic.options' => array(
                'formulae_cache_dir' => $app['gizmo.cache_path'] . '/assetic',
                'debug' => $app['debug']
            ),
        ));
        $app['markdown.features'] = array(
            'entities' => true
        );
        $app->register(new \SilexExtension\MarkdownExtension());
        
        if (isset($app['gizmo.mount_point'])) {
            $app->mount($app['gizmo.mount_point'], $this);
        }
    }

    public function connect(Application $app)
    {
        $controllers = $app['controllers_factory'];

        // Bind default catch-all route for pages
        $controllers->match('/{path}.{_format}', function ($path) use ($app) {
            return $app['gizmo']->dispatch($path);

        })->bind('page')
          ->assert('path', '.+?')
          ->assert('_format', 'html|json|xml|rss|rdf|atom')
          ->value('_format', 'html');

        // Homepage route
        $controllers->match('/', function () use ($app) {
            return $app['gizmo']->dispatch();

        })->bind('homepage');

        return $controllers;
    }

    public function render404()
    {
        $response = new Response(null, 404);
        if ($page404 = $this->app['gizmo.page_factory']->fromPath('404')) {
            $response->setContent($this->renderModel($page404));
            return $response;
        }
        $file404 = $this->app['gizmo.public_path'] . '/404.html';
        if (file_exists($file404)) {
            $response->setContent(file_get_contents($file404));
            return $response;
        }
        $this->app->abort(404, 'Sorry, the requested page could not be found.');
    }
    
    public function renderModel(Model $model)
    {
        // print_r($model->toArray()); die;
        
        if ($model instanceof Page && $model->template) {
            return $this->app['twig']->render($model->template, array('page' => $model));
        }
        if ($model instanceof Asset) {
            return new Response($model->getContents(), 200, array(
                'Content-Type' => $model->mimeType
            ));
        }
        return false;
    }
    
    public function dispatch($path = null)
    {
        if ($model = $this->app['gizmo.model']($path)) {
            if ($rendered = $this->renderModel($model)) {
                return $rendered;
            }
        }
        return $this->render404();
    }
}
