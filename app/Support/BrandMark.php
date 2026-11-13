<?php

namespace App\Support;

use GdImage;

/**
 * Het beeldmerk van BarberCuts: een zwarte tegel met een witte "b" uit een stam en een ring (of andersom, wit met zwart).
 * Eén plek voor de vorm, zodat de iconen, de deelafbeeldingen en de svg in de site gelijk blijven.
 * Alle maten zijn op een vlak van 100 × 100.
 */
class BrandMark
{
    public const string BLACK = '#000000';

    public const string WHITE = '#ffffff';

    /**
     * Het beeldmerk als svg (voor favicon.svg).
     */
    public static function svg(bool $dark = true): string
    {
        [$tile, $letter] = $dark ? [self::BLACK, self::WHITE] : [self::WHITE, self::BLACK];

        // In een donker browsertabblad draait het om (wit met zwart), zodat het icoon zichtbaar blijft.
        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
            .'<style>.t{fill:'.$tile.'}.s{fill:'.$letter.'}.r{stroke:'.$letter.'}@media (prefers-color-scheme:dark){.t{fill:'.$letter.'}.s{fill:'.$tile.'}.r{stroke:'.$tile.'}}</style>'
            .'<rect class="t" width="100" height="100" rx="22"/>'
            .'<rect class="s" x="30" y="20" width="12" height="59" rx="6"/>'
            .'<circle class="r" cx="50" cy="60" r="13" fill="none" stroke-width="12"/>'
            .'</svg>';
    }

    /**
     * Teken het beeldmerk als afbeelding van $size pixels.
     *
     * @param  bool  $rounded  afgeronde hoeken met doorzichtige randen (false = volledig vlak, voor iOS en "maskable")
     * @param  float  $scale  grootte van de letter binnen het vlak (0.72 voor "maskable": de rand kan worden afgesneden)
     * @param  bool  $dark  zwarte tegel met witte letter (false = witte tegel met zwarte letter, voor op een gekleurde achtergrond)
     */
    public static function image(int $size, bool $rounded = true, float $scale = 1.0, bool $dark = true): GdImage
    {
        // Drie keer zo groot tekenen en verkleinen geeft gladde randen.
        $factor = 3;
        $big = $size * $factor;
        $canvas = imagecreatetruecolor($big, $big);
        imagealphablending($canvas, false);
        imagesavealpha($canvas, true);

        [$tr, $tg, $tb] = Color::rgb($dark ? self::BLACK : self::WHITE);
        [$lr, $lg, $lb] = Color::rgb($dark ? self::WHITE : self::BLACK);
        $clear = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        $tile = imagecolorallocate($canvas, $tr, $tg, $tb);
        $white = imagecolorallocate($canvas, $lr, $lg, $lb);
        $unit = $big / 100;
        $radius = 22 * $unit;

        for ($y = 0; $y < $big; $y++) {
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
                imagesetpixel($canvas, $x, $y, $outside ? $clear : $tile);
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
