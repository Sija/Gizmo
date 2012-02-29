<?php

require_once __DIR__.'/lib/vendor/Silex/silex.phar';

$app = new Silex\Application();
$app['debug'] = true;

$app['gizmo.content_path'] = __DIR__.'/src/content';
$app['gizmo.default_layout'] = 'default.html';

$app['autoloader']->registerNamespace('Symfony', __DIR__.'/lib/vendor/Symfony/src');
$app['autoloader']->registerNamespace('Gizmo', __DIR__.'/lib');

$app->register(new Silex\Provider\UrlGeneratorServiceProvider());
$app->register(new Silex\Provider\SymfonyBridgesServiceProvider());
$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.class_path' => __DIR__.'/lib/vendor/Twig/lib',
    'twig.path' => __DIR__.'/src/views',
    'twig.options' => array(
        'base_template_class' => 'Gizmo\\Twig_Template',
        'strict_variables' => false,
    ),
));

$app['autoloader']->registerNamespace('SilexExtension', __DIR__.'/lib/vendor/Silex-Extensions/src');
$app->register(new SilexExtension\MarkdownExtension(), array(
    'markdown.class_path' => __DIR__.'/lib/vendor/KnpMarkdownBundle',
    'markdown.features' => array(
        'entities' => true
    ),
));

$app['autoloader']->registerPrefix('Twig_Extensions_', __DIR__.'/lib/vendor/Twig-extensions/lib');
/*
$app['twig.configure'] = $app->protect(function($twig) {
    $twig->addExtension(new Twig_Extensions_Extension_Text());
    $twig->addExtension(new Twig_Extensions_Extension_Debug());
});
*/
$app['twig']->addExtension(new Twig_Extensions_Extension_Text());
$app['twig']->addExtension(new Twig_Extensions_Extension_Debug());

$app->before(function () use ($app) {
    #$app['twig']->addGlobal('layout', $app['twig']->loadTemplate('layout.html.twig'));
});

new Gizmo\Gizmo($app);

$app->run();