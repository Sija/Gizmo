<?php

namespace Gizmo;

class Video extends Asset
{
    public static function getSupportedExtensions()
    {
        return array('mov', 'avi', 'mpg', 'mp4', 'm4v', 'swf');
    }
}
