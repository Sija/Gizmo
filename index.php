<?php

require_once __DIR__ . '/app/vendor/autoload.php';

$app = new Silex\Application();
$app['debug'] = true;

$app->register(new Gizmo\Silex\Provider(), array(
    'gizmo.path'           => __DIR__,
    'gizmo.mount_point'    => '/',

    'gizmo.options' => array(
        'timezone'       => 'UTC',
        'default_layout' => 'base.html.twig',
    )
));

$app->run();
