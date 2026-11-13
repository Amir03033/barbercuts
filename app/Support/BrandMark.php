<?php

namespace App\Support;

use GdImage;

/**
 * Het beeldmerk van BarberCuts: een blauwe tegel met een witte "b" uit een stam en een ring.
 * Eén plek voor de vorm, zodat de iconen, de deelafbeeldingen en de svg in de site gelijk blijven.
 * Alle maten zijn op een vlak van 100 × 100.
 */
class BrandMark
{
    public const string TOP = '#2997ff';

    public const string BOTTOM = '#0058d0';

    /**
     * Het beeldmerk als svg (voor favicon.svg).
     */
    public static function svg(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
            .'<defs><linearGradient id="g" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="'.self::TOP.'"/><stop offset="1" stop-color="'.self::BOTTOM.'"/></linearGradient></defs>'
            .'<rect width="100" height="100" rx="22" fill="url(#g)"/>'
            .'<rect x="30" y="20" width="12" height="59" rx="6" fill="#fff"/>'
            .'<circle cx="50" cy="60" r="13" fill="none" stroke="#fff" stroke-width="12"/>'
            .'</svg>';
    }

    /**
     * Teken het beeldmerk als afbeelding van $size pixels.
     *
     * @param  bool  $rounded  afgeronde hoeken met doorzichtige randen (false = volledig vlak, voor iOS en "maskable")
     * @param  float  $scale  grootte van de letter binnen het vlak (0.72 voor "maskable": de rand kan worden afgesneden)
     */
    public static function image(int $size, bool $rounded = true, float $scale = 1.0): GdImage
    {
        // Drie keer zo groot tekenen en verkleinen geeft gladde randen.
        $factor = 3;
        $big = $size * $factor;
        $canvas = imagecreatetruecolor($big, $big);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        [$r1, $g1, $b1] = Color::rgb(self::TOP);
        [$r2, $g2, $b2] = Color::rgb(self::BOTTOM);
        $clear = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        $white = imagecolorallocate($canvas, 255, 255, 255);
        $unit = $big / 100;
        $radius = 22 * $unit;

        for ($y = 0; $y < $big; $y++) {
            $t = $y / max(1, $big - 1);
            $row = imagecolorallocate($canvas, (int) round($r1 + ($r2 - $r1) * $t), (int) round($g1 + ($g2 - $g1) * $t), (int) round($b1 + ($b2 - $b1) * $t));

            for ($x = 0; $x < $big; $x++) {
                // Coördinaten op het vlak van 100, met de letter geschaald rond het midden.
                $u = ($x / $unit - 50) / $scale + 50;
                $v = ($y / $unit - 50) / $scale + 50;

                $inStem = $u >= 30 && $u <= 42 && $v >= 20 && $v <= 79
                    && ! ($v < 26 && hypot($u - 36, $v - 26) > 6) && ! ($v > 73 && hypot($u - 36, $v - 73) > 6);
                $distance = hypot($u - 50, $v - 60);
                $inRing = $distance >= 7 && $distance <= 19;

                if ($inStem || $inRing) {
                    imagesetpixel($canvas, $x, $y, $white);

                    continue;
                }

                $outside = $rounded && self::outsideCorner($x, $y, $big, $radius);
                imagesetpixel($canvas, $x, $y, $outside ? $clear : $row);
            }
        }

        $image = imagecreatetruecolor($size, $size);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagecopyresampled($image, $canvas, 0, 0, 0, 0, $size, $size, $big, $big);

        return $image;
    }

    private static function outsideCorner(int $x, int $y, int $size, float $radius): bool
    {
        $dx = $x < $radius ? $radius - $x : ($x > $size - $radius ? $x - ($size - $radius) : 0);
        $dy = $y < $radius ? $radius - $y : ($y > $size - $radius ? $y - ($size - $radius) : 0);

        return $dx > 0 && $dy > 0 && hypot($dx, $dy) > $radius;
    }
}
