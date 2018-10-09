<?php

declare(strict_types=1);

namespace App\Model;

use ErrorException;

class Image extends Document
{
    public function __construct(string $filename)
    {
        parent::__construct($filename);

        if ($this->isImage() !== true) {
            throw new ErrorException(sprintf('The file "%s" is not a valid image file !', $this->getPathname()));
        }
    }

    /**
     * @return array|false
     */
    public function getEXIF()
    {
        if ($this->isReadable() && $this->getSize() > 0 && $this->isImage()) {
            return @exif_read_data($this->getPathname());
        }

        return false;
    }
}
