<?php

declare (strict_types = 1);

namespace App\Model;

use Mimey\MimeTypes;
use SplFileInfo;

class Document extends SplFileInfo
{
    /**
     * @param $filename
     */
    public function __construct(string $filename)
    {
        parent::__construct($filename);
    }

    /**
     *
     */
    public function getEXIF()
    {
        if ($this->isReadable() && $this->getSize() > 0 && $this->isImage()) {
            return @exif_read_data($this->getRealPath());
        }

        return false;
    }

    /**
     *
     */
    public function getFontAwesomeIcon(): string
    {
        $mime = $this->getMimeType();
        $mimes = new MimeTypes;

        if (preg_match('/text\/.+/', $mime) === 1) {
            return 'fa-file-alt';
        } elseif (preg_match('/image\/.+/', $mime) === 1) {
            return 'fa-file-image';
        } elseif ($mime === 'application/pdf') {
            return 'fa-file-pdf';
        } elseif ($mime === 'application/zip') {
            return 'fa-file-archive';
        } elseif (in_array($mime, $mimes->getAllMimeTypes('doc')) || in_array($mime, $mimes->getAllMimeTypes('docx'))) {
            return 'fa-file-word';
        } elseif (in_array($mime, $mimes->getAllMimeTypes('xls')) || in_array($mime, $mimes->getAllMimeTypes('xlsx'))) {
            return 'fa-file-excel';
        } elseif (in_array($mime, $mimes->getAllMimeTypes('ppt')) || in_array($mime, $mimes->getAllMimeTypes('pptx'))) {
            return 'fa-file-powerpoint';
        } else {
            return 'fa-file';
        }

        return $mime;
    }

    /**
     *
     */
    public function getInfo()
    {
        $file = $this->getRealPath() . '.info';

        if (file_exists($file)) {
            return file_get_contents($file);
        }

        return false;
    }

    /**
     *
     */
    public function getMimeType()
    {
        if ($this->isDir()) {
            return null;
        } elseif (!$this->isReadable()) {
            return false;
        } else {
            return mime_content_type($this->getRealPath());
        }
    }

    /**
     * @param $format
     */
    public function getReadableMTime(string $format = '%e %B %Y %H:%M:%S'): string
    {
        $time = $this->getMTime();

        return strftime($format, $time);
    }

    /**
     * @param $decimals
     */
    public function getReadableSize(int $decimals = 2): string
    {
        $size = (string) $this->getSize();

        //return (new ReadableNumber($size))->long();

        $ext = ['o', 'Ko', 'Mo', 'Go', 'To', 'Po', 'Eo', 'Zo', 'Yo'];
        $factor = floor((strlen($size) - 1) / 3);

        return sprintf("%.{$decimals}f ", $size / pow(1024, $factor)) . @$ext[$factor];
    }

    /**
     *
     */
    public function getRelativePath(): string
    {
        $path = $this->getRealPath();
        $root = (new SplFileInfo('data'))->getRealPath();

        return str_replace($root . '/', '', $path);
    }

    /**
     *
     */
    public function isImage(): bool
    {
        $mime = $this->getMimeType();

        return preg_match('/image\/.+/', $mime) === 1;
    }
}
