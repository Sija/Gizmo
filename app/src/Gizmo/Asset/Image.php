<?php

namespace Gizmo;

class Image extends Asset
{
    public static function getSupportedExtensions()
    {
        return array('jpg', 'jpeg', 'gif', 'png');
    }

    public function setData(array $data)
    {
        parent::setData($data);

        # set asset.width & asset.height variables
        $imgData = getimagesize($this->fullPath, $imgInfo);
        $this->width = $imgData[0];
        $this->height = $imgData[1];

        # set iptc variables
        if (isset($imgInfo['APP13'])) {
            $iptc = iptcparse($imgInfo['APP13']);
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
}
