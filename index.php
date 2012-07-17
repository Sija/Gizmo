<?php

require_once __DIR__.'/app/vendor/autoload.php';

$app = new Silex\Application();
$app['debug'] = true;

$app->register(new Gizmo\Gizmo(), array(
    'gizmo.mount_point'    => '/',
    'gizmo.timezone'       => 'UTC',
    
    'gizmo.app_path'       => __DIR__.'/app',
    'gizmo.content_path'   => __DIR__.'/content',
    'gizmo.templates_path' => __DIR__.'/templates',
    'gizmo.public_path'    => __DIR__.'/web',
    'gizmo.default_layout' => 'default.html.twig',
));

$app->run();
