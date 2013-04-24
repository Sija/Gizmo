<?php

namespace Gizmo\Silex;

class Bootstrap {
    public static function app()
    {
        $app = new \Silex\Application();
        $app['debug'] = true;

        $app->register(new Provider(), array(
            'gizmo.path'           => dirname($_SERVER['SCRIPT_FILENAME']),
            'gizmo.mount_point'    => '/',

            'gizmo.options' => array(
                'timezone'       => 'UTC',
                'default_layout' => 'base.html.twig',
            )
        ));
        return $app;
    }
}
