<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = new Silex\Application();
$app['debug'] = true;

$app->register(new Gizmo\Silex\Provider(), array(
    'gizmo.path'           => dirname($_SERVER['SCRIPT_FILENAME']),
    'gizmo.mount_point'    => '/',

    'gizmo.options' => array(
        'timezone'       => 'UTC',
        'default_layout' => 'base.html.twig',
    )
));
