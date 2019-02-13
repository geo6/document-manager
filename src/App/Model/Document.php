<?php

declare(strict_types=1);

namespace App\Model;

use Mimey\MimeTypes;
use SplFileInfo;

class Document extends SplFileInfo
{
    public function __construct(string $filename)
    {
        parent::__construct($filename);
    }

    /**
     * @return bool
     */
    public function isDirectoryWritable(): bool
    {
        return is_writable(dirname($this->getPathname()));
    }

    /**
     * @return string
     */
    public function getFontAwesomeIcon(): string
    {
        $mime = $this->getMimeType();
        $mimes = new MimeTypes();

        if ($mime === false) {
            return 'fa-file';
        } elseif (preg_match('/text\/.+/', $mime) === 1) {
            return 'fa-file-alt';
        } elseif (preg_match('/image\/.+/', $mime) === 1 && $this->isImage()) {
            return 'fa-file-image';
        } elseif ($mime === 'application/pdf') {
            return 'fa-file-pdf';
        } elseif ($mime === 'application/zip') {
            return 'fa-file-archive';
        } elseif (in_array($mime, $mimes->getAllMimeTypes('doc'), true) || in_array($mime, $mimes->getAllMimeTypes('docx'), true)) {
            return 'fa-file-word';
        } elseif (in_array($mime, $mimes->getAllMimeTypes('xls'), true) || in_array($mime, $mimes->getAllMimeTypes('xlsx'), true)) {
            return 'fa-file-excel';
        } elseif (in_array($mime, $mimes->getAllMimeTypes('ppt'), true) || in_array($mime, $mimes->getAllMimeTypes('pptx'), true)) {
            return 'fa-file-powerpoint';
        } else {
            return 'fa-file';
        }
    }

    /**
     * @return string|false
     */
    public function getInfo()
    {
        $file = $this->getPathname().'.info';

        if (file_exists($file)) {
            return file_get_contents($file);
        }

        return false;
    }

    /**
     * @return string|false
     */
    public function getMimeType()
    {
        if ($this->isDir()) {
            return false;
        } elseif (!$this->isReadable()) {
            return false;
        } else {
            return mime_content_type($this->getPathname());
        }
    }

    /**
     * @return string
     */
    public function getReadableMTime(string $format = '%e %B %Y %H:%M:%S'): string
    {
        $time = $this->getMTime();

        return strftime($format, $time);
    }

    /**
     * @return string
     */
    public function getReadableSize(int $decimals = 2): string
    {
        $size = (string) $this->getSize();

        //return (new ReadableNumber($size))->long();

        $ext = ['o', 'Ko', 'Mo', 'Go', 'To', 'Po', 'Eo', 'Zo', 'Yo'];
        $factor = floor((strlen($size) - 1) / 3);

        return sprintf("%.{$decimals}f ", $size / pow(1024, $factor)).(isset($ext[$factor]) ? $ext[$factor] : '');
    }

    /**
     * @return string
     */
    public function getRelativePath(): string
    {
        $path = $this->getPathname();
        $root = (new SplFileInfo('data'))->getPathname();

        return str_replace($root.'/', '', $path);
    }

    /**
     * @return bool
     */
    public function isImage(): bool
    {
        $mime = $this->getMimeType();

        $imageMimeTypes = [
            image_type_to_mime_type(IMAGETYPE_BMP),
            image_type_to_mime_type(IMAGETYPE_GIF),
            image_type_to_mime_type(IMAGETYPE_ICO),
            image_type_to_mime_type(IMAGETYPE_JPEG),
            image_type_to_mime_type(IMAGETYPE_JPEG2000),
            image_type_to_mime_type(IMAGETYPE_PNG),
            image_type_to_mime_type(IMAGETYPE_WEBP),
        ];

        return $mime !== false && preg_match('/image\/.+/', $mime) === 1 && in_array($mime, $imageMimeTypes);
    }

    /**
     * @return bool
     */
    public function isGeoJSON(): bool
    {
        $mime = $this->getMimeType();
        $extension = $this->getExtension();

        if (in_array($mime, ['application/json', 'text/plain'], true) && in_array(strtolower($extension), ['json', 'geojson'], true)) {
            $content = file_get_contents($this->getPathname());

            if ($content !== false) {
                $json = json_decode($content);

                if (json_last_error() === JSON_ERROR_NONE) {
                    if (isset($json->type) && ($json->type === 'Feature' || $json->type === 'FeatureCollection')) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}
