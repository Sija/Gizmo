<?php

namespace Gizmo\Asset;

class Video extends \Gizmo\Asset
{
    public static function getSupportedExtensions()
    {
        return array('mov', 'avi', 'mpg', 'mp4', 'm4v', 'swf');
    }
}
