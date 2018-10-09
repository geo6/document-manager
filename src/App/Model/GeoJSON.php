<?php

declare(strict_types=1);

namespace App\Model;

use ErrorException;

class GeoJSON extends Document
{
    public function __construct(string $filename)
    {
        parent::__construct($filename);

        if ($this->isGeoJSON() !== true) {
            throw new ErrorException(sprintf('The file "%s" is not a valid GeoJSON file !', $this->getPathname()));
        }
    }

    /**
     * @return object|false
     */
    public function getJSON()
    {
        $content = file_get_contents($this->getPathname());

        if ($content !== false) {
            return json_decode($content);
        }

        return false;
    }

    /**
     * @return int
     */
    public function getFeaturesCount(): int
    {
        $json = $this->getJSON();

        if ($json !== false && isset($json->type) && $json->type === 'Feature') {
            return 1;
        } elseif ($json !== false && isset($json->type, $json->features) && $json->type === 'FeatureCollection') {
            return count($json->features);
        } else {
            return 0;
        }
    }

    /**
     * @return string|null
     */
    public function getTitle()
    {
        $json = $this->getJSON();

        return $json !== false && isset($json->title) ? $json->title : null;
    }

    /**
     * @return string|null
     */
    public function getDescription()
    {
        $json = $this->getJSON();

        return $json !== false && isset($json->description) ? $json->description : null;
    }
}
