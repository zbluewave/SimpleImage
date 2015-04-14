<?php

/**
 * @package     BmpImageLoader class
 * @author      Class to process bmp images
 */

namespace abeautifulsite;

/**
 * Bmp Image Loader
 * Based on ImageCreateFromBMP in http://php.net/manual/en/function.imagecreate.php
 * http://tr.php.net/imagecreate
 * This class could take file or string as input
 *
 */
class BmpImageLoader
{
    /**
     * Create bmp image loader from bmp image string
     * @param  string $image_string image string
     * @return BmpImageLoader
     */
    public static function loadFromData($image_string)
    {
        return new BmpImageLoader($image_string);
    }

    /**
     * Create bmp image loader from file
     * @param  string $filename filename path in local machine
     * @return BmpImageLoader
     */
    public static function loadFromFile($filename)
    {
        $file_content = file_get_contents($filename);
        if (!$file_content) {
            throw new Exception("Invalid File Path: " . $filename);
        }
        return new BmpImageLoader($file_content);
    }

    private function __construct($image_string)
    {
        $pointer = 0;
        $this->file_info  = $this->getFileInfo($image_string, $pointer);
        $this->image_info = $this->getImageInfo($image_string, $pointer);
        $this->palette   = $this->getPalette($image_string, $pointer);
        $this->image_raw = $this->getImageRaw($image_string, $pointer);
    }

    /**
     * Return BmpImage Resource
     *
     * @return resource
     */
    public function getImageResource()
    {
        $empty = chr(0);

        $res = imagecreatetruecolor($this->image_info['width'], $this->image_info['height']);
        $p = 0;
        $y = $this->image_info['height'] - 1;
        while ($y >= 0) {
            $x = 0;
            while ($x < $this->image_info['width']) {
                switch ($this->image_info['bits_per_pixel']) {
                    case 24:
                        $color = unpack('V', substr($this->image_raw, $p, 3) . $empty);
                        break;
                    case 16:
                        $color = unpack('n', substr($this->image_raw, $p, 2));
                        $color[1] = $this->palette[$color[1] + 1];
                        break;
                    case 8:
                        $color = unpack('n', $empty . substr($this->image_raw, $p, 1));
                        $color[1] = $this->palette[$color[1] + 1];
                        break;
                    case 4:
                        $color = unpack('n', $empty . substr($this->image_raw, floor($p), 1));
                        if (($p*2)%2 == 0) {
                            $color[1] = ($color[1] >> 4) ;
                        } else {
                            $color[1] = ($color[1] & 0x0F);
                        }
                        break;
                    case 1:
                        $color = unpack('n', $empty . substr($this->image_raw, floor($p), 1));
                        switch (($p * 8) % 8) {
                            case 0:
                                $color[1] = $color[1] >> 7;
                                break;
                            case 1:
                                $color[1] = ($color[1] & 0x40) >> 6;
                                break;
                            case 2:
                                $color[1] = ($color[1] & 0x20) >> 5;
                                break;
                            case 3:
                                $color[1] = ($color[1] & 0x10) >> 4;
                                break;
                            case 4:
                                $color[1] = ($color[1] & 0x8) >> 3;
                                break;
                            case 5:
                                $color[1] = ($color[1] & 0x4) >> 2;
                                break;
                            case 6:
                                $color[1] = ($color[1] & 0x2) >> 1;
                                break;
                            case 7:
                                $color[1] = ($color[1] & 0x1);
                                break;
                        }

                        $color[1] = $this->palette[$color[1]+1];
                        break;
                }

                imagesetpixel($res, $x, $y, $color[1]);
                $x++;
                $p += $this->image_info['bytes_per_pixel'];
            }

            $y--;
            $p += $this->image_info['decal'];
        }

        return $res;
    }

    private function getFileInfo($image_string, &$pointer)
    {
        $file = unpack(
            "vfile_type/Vfile_size/Vreserved/Vbitmap_offset",
            mb_strcut($image_string, $pointer, ($pointer += 14))
        );

        if ($file['file_type'] != 19778) {
            throw new Exception("Invalid Bmp File");
        }
        return $file;
    }

    private function getImageInfo($image_string, &$pointer)
    {
       $image_info = unpack(
            'Vheader_size/Vwidth/Vheight/vplanes/vbits_per_pixel'.
            '/Vcompression/Vsize_bitmap/Vhoriz_resolution'.
            '/Vvert_resolution/Vcolors_used/Vcolors_important',
            mb_strcut($image_string, $pointer, ($pointer += 40))
        );
        $image_info['colors'] = pow(2, $image_info['bits_per_pixel']);
        if ($image_info['size_bitmap'] == 0) {
            $image_info['size_bitmap'] = $this->file_info['file_size']-$this->file_info['bitmap_offset'];
        }
        $image_info['bytes_per_pixel'] = $image_info['bits_per_pixel']/8;
        $image_info['bytes_per_pixel2'] = ceil($image_info['bytes_per_pixel']);
        $image_info['decal'] = ($image_info['width'] * $image_info['bytes_per_pixel']/4);
        $image_info['decal'] -= floor($image_info['width'] * $image_info['bytes_per_pixel']/4);
        $image_info['decal'] = 4-(4*$image_info['decal']);
        if ($image_info['decal'] == 4) {
            $image_info['decal'] = 0;
        }

        if (!in_array($image_info['bits_per_pixel'], [24, 16, 8, 4, 1])) {
            throw new Exception("Not Support bits per pixel mode :" . $image_info['bits_per_pixel']);
        }
        return $image_info;
    }

    private function getPalette($image_string, &$pointer)
    {
        $palette = [];
        if ($this->image_info['colors'] < 16777216)
        {
            $palette = unpack(
                'V'.$this->image_info['colors'],
                mb_strcut($image_string, $pointer, ($pointer += $this->image_info['colors']*4))
            );
        }
        return $palette;
    }

    private function getImageRaw($image_string, &$pointer)
    {
        return mb_strcut($image_string, $pointer, ($pointer += $this->image_info['size_bitmap']));
    }
}