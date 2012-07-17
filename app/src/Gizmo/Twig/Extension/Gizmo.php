<?php

namespace Gizmo;

class Twig_Extension_Gizmo extends \Twig_Extension
{
    protected $app = null;
    
    public function __construct(\Silex\Application $app)
    {
        $this->app = $app;
    }
    
    public function getName()
    {
        return 'Gizmo';
    }

    public function getFunctions()
    {
        return array(
            'get' => new \Twig_Function_Method($this, 'get'),
        );
    }
    
    public function get($path)
    {
        return $this->app['gizmo.model']($path);
    }
}
