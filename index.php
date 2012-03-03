<?php

require_once __DIR__.'/app/vendor/.composer/autoload.php';
require_once __DIR__.'/app/vendor/silex/silex.phar';

$app = new Silex\Application();
$app['debug'] = true;

$app['gizmo.content_path'] = __DIR__.'/content';
$app['gizmo.default_layout'] = 'default.html.twig';

$app->register(new Silex\Provider\TwigServiceProvider(), array(
    'twig.class_path' => __DIR__.'/app/vendor/Twig/lib',
    'twig.path' => __DIR__.'/templates',
    'twig.options' => array(
        'base_template_class' => 'Gizmo\\Twig_Template',
        'strict_variables' => false,
        'cache' => __DIR__.'/app/cache/templates',
    ),
));

$app->register(new SilexExtension\MarkdownExtension(), array(
    'markdown.class_path' => __DIR__.'/app/vendor/KnpMarkdownBundle',
    'markdown.features' => array(
        'entities' => true
    ),
));

/*
$app['twig.configure'] = $app->protect(function($twig) {
    $twig->addExtension(new Twig_Extensions_Extension_Text());
    $twig->addExtension(new Twig_Extensions_Extension_Debug());
});
*/
$app['twig']->addExtension(new Twig_Extensions_Extension_Text());
$app['twig']->addExtension(new Twig_Extensions_Extension_Debug());
$app['twig']->addExtension(new Gizmo\Twig_Extension_Gizmo());

$app->before(function () use ($app) {
    #$app['twig']->addGlobal('layout', $app['twig']->loadTemplate('layout.html.twig'));
});

new Gizmo\Gizmo($app);

$app->run();