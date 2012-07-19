<?php

namespace Gizmo;

class Twig_Extension extends \Twig_Extension
{
    protected $gizmo = null;
    
    public function __construct(Gizmo $gizmo)
    {
        $this->gizmo = $gizmo;
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
    
    public function getFilters()
    {
        return array(
            'thumbnail' => new \Twig_Filter_Method($this, 'thumbnail'),
        );
    }

    public function get($path)
    {
        return $this->gizmo['model']($path);
    }
    
    public function thumbnail($path, $width = null, $height = null, $outbound = null, $quality = null)
    {
        $model = $this->gizmo['model']($path);
        if ($model instanceof Page) {
            if ($model->thumb) {
                $model = $this->gizmo['asset']($model->thumb);
                if (!($width && $height))
                    if ($model) return $model->url;
            } else {
                $model = $model->images[0];
            }
        }
        if ($model instanceof Asset) {
            return $this->gizmo['app']['url_generator']->generate('thumb', array(
                'width' => $width ?: 200,
                'height' => $height ?: 200,
                'path' => $model->path,
                'outbound' => $outbound ? 'true' : null,
                'quality' => $quality,
            ));
        }
        return null;
    }
}
