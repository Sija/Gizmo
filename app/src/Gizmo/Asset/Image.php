<?php

namespace Gizmo;

class Image extends Asset
{
    public function setData(array $data)
    {
        parent::setData($data);

        # set asset.width & asset.height variables
        $img_data = getimagesize($this->fullPath, $img_info);
        $this->width = $img_data[0];
        $this->height = $img_data[1];

        # set iptc variables
        if (isset($img_info['APP13'])) {
            $iptc = iptcparse($img_info['APP13']);
            # asset.title
            if (isset($iptc['2#005'][0])) {
                $this->title = $iptc['2#005'][0];
            }
            # asset.description
            if (isset($iptc['2#120'][0])) {
                $this->description = $iptc['2#120'][0];
            }
            # asset.keywords
            if (isset($iptc['2#025'][0])) {
                $this->keywords = $iptc['2#025'][0];
            }
        }
    }
    
    public static function getSupportedExtensions()
    {
        return array('jpg', 'jpeg', 'gif', 'png');
    }
}
