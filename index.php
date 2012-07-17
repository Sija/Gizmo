<?php

require_once __DIR__.'/app/vendor/autoload.php';

$app = new Silex\Application();
$app['debug'] = true;

$app->register(new Gizmo\Gizmo(), array(
    'gizmo.mount_point'    => '/',
    'gizmo.timezone'       => 'UTC',
    'gizmo.default_layout' => 'base.html.twig',
    
    'gizmo.app_path'       => __DIR__.'/app',
    'gizmo.content_path'   => __DIR__.'/content',
    'gizmo.templates_path' => __DIR__.'/templates',
    'gizmo.public_path'    => __DIR__.'/web',
    'gizmo.assets_path'    => __DIR__.'/web/assets',
));

$app['assetic.filters'] = $app->protect(function ($fm) {
    $fm->set('coffee', new \Assetic\Filter\CoffeeScriptFilter(
        '/usr/local/bin/coffee',
        '/usr/local/bin/node'
    ));
});

$app['assetic.assets'] = $app->protect(function ($am, $fm) use ($app) {
    $am->set('styles', new \Assetic\Asset\AssetCache(
        new \Assetic\Asset\AssetCollection(array(
            new \Assetic\Asset\GlobAsset(
                $app['gizmo.assets_path'] . '/stylesheets/*.css', 
                array()
            ),
        )),
        new \Assetic\Cache\FilesystemCache($app['gizmo.cache_path'] . '/assetic')
    ));
    $am->get('styles')->setTargetPath('compiled.css');
    
    $am->set('scripts', new \Assetic\Asset\AssetCache(
        new \Assetic\Asset\AssetCollection(array(
            new \Assetic\Asset\GlobAsset(
                $app['gizmo.assets_path'] . '/javascripts/*.js', 
                array()
            ),
            new \Assetic\Asset\GlobAsset(
                $app['gizmo.assets_path'] . '/javascripts/*.js.coffee', 
                array($fm->get('coffee'))
            ),
        )),
        new \Assetic\Cache\FilesystemCache($app['gizmo.cache_path'] . '/assetic')
    ));
    $am->get('scripts')->setTargetPath('compiled.js');
});

$app->run();
