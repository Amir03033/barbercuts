<?php

namespace App\Services;

use App\Models\Barbershop;
use App\Models\DemoInvite;
use App\Models\User;
use App\Support\BrandMark;
use App\Support\Color;
use App\Support\ImageStore;
use GdImage;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * De afbeelding die verschijnt als iemand een link deelt (WhatsApp, Instagram, iMessage): 1200 × 630.
 * Elke zaak krijgt een eigen afbeelding met haar naam, kleur, omslagfoto, logo en beoordeling.
 */
class ShareImage
{
    private const int WIDTH = 1200;

    private const int HEIGHT = 630;

    /**
     * Het pad naar de deelafbeelding van een zaak. Wordt opnieuw gemaakt zodra er iets aan de zaak verandert.
     */
    public function forShop(Barbershop $shop): string
    {
        $key = sha1(implode('|', [
            $shop->name, $shop->city, $shop->accent_color, $shop->cover_path, $shop->logo_path,
            $shop->rating_avg, $shop->rating_count, 'v3',
        ]));
        $directory = storage_path('app/share');
        $path = "{$directory}/{$shop->id}-{$key}.png";

        if (is_file($path)) {
            return $path;
        }

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        // Oude versies van deze zaak opruimen.
        foreach (glob("{$directory}/{$shop->id}-*.png") ?: [] as $old) {
            @unlink($old);
        }

        imagepng($this->drawShop($shop), $path, 7);

        return $path;
    }

    /**
     * De deelafbeelding van de openbare demolink: met de naam van de pitcher, of bij een persoonlijke demo met de naam
     * (en kleur) van de zaak. Zo ziet een kapper in WhatsApp meteen dat de link voor hem is.
     */
    public function forDemo(?User $pitcher, ?DemoInvite $invite): string
    {
        $pitcherName = $pitcher !== null ? explode(' ', trim($pitcher->name))[0] : null;
        $key = sha1(implode('|', [$pitcherName, $invite?->shop_name, $invite?->city, $invite?->accent_color, 'v3']));
        $directory = storage_path('app/share');
        $path = "{$directory}/demo-{$key}.png";

        if (is_file($path)) {
            return $path;
        }

        if (! is_dir($directory)) {
            mkdir($directory, 0775, true);
        }

        imagepng($this->drawDemo($pitcherName, $invite), $path, 7);

        return $path;
    }

    private function drawDemo(?string $pitcherName, ?DemoInvite $invite): GdImage
    {
        $image = $this->canvas();
        $accent = $invite !== null && Color::isHex($invite->accent_color) ? (string) $invite->accent_color : '#0071e3';
        [$r, $g, $b] = Color::rgb($accent);
        $this->gradient($image, sprintf('#%02x%02x%02x', (int) ($r * 0.45), (int) ($g * 0.45), (int) ($b * 0.45)), '#000000');

        $white = imagecolorallocate($image, 255, 255, 255);
        $soft = imagecolorallocatealpha($image, 255, 255, 255, 30);
        $green = imagecolorallocate($image, 48, 209, 88);
        $x = 72;

        imagecopy($image, BrandMark::image(88, dark: false), $x, 64, 0, 0, 88, 88);
        imagettftext($image, 32, 0, $x + 108, 122, $white, $this->font('Bold'), 'barbercuts');

        // "Live demo"-label rechtsboven
        $label = 'Live demo · geen account nodig';
        $box = imagettfbbox(20, 0, $this->font('Medium'), $label);
        $labelWidth = $box[2] - $box[0] + 78;
        $this->pill($image, self::WIDTH - 72 - $labelWidth, 78, $labelWidth, 58, 0.18);
        imagefilledellipse($image, self::WIDTH - 72 - $labelWidth + 30, 107, 14, 14, $green);
        imagettftext($image, 20, 0, self::WIDTH - 72 - $labelWidth + 48, 115, $white, $this->font('Medium'), $label);

        [$lead, $title] = match (true) {
            $invite !== null => ['Zo ziet jouw zaak eruit', $invite->shop_name],
            $pitcherName !== null => ["{$pitcherName} nodigt je uit", 'Probeer BarberCuts'],
            default => ['Je eigen demozaak', 'Probeer BarberCuts'],
        };

        imagettftext($image, 34, 0, $x, 290, $soft, $this->font('Medium'), $lead);

        $y = 390;

        foreach ($this->wrap($title, 76, self::WIDTH - 2 * $x, 2) as $line) {
            imagettftext($image, 76, 0, $x, $y, $white, $this->font('Bold'), $line);
            $y += 92;
        }

        imagettftext($image, 28, 0, $x, 570, $soft, $this->font('Medium'), 'Agenda, klanten en omzet · probeer het in een minuut');

        return $image;
    }

    /**
     * Een pil in een effen kleur: de achtergrond onder het midden, voor $whiteness wit gemaakt. Effen, zodat de delen
     * waar rechthoek en halve cirkels overlappen niet lichter worden.
     */
    private function pill(GdImage $image, int $x, int $y, int $width, int $height, float $whiteness): void
    {
        $radius = intdiv($height, 2);
        $under = imagecolorat($image, $x + intdiv($width, 2), $y + $radius);
        $mix = fn (int $channel): int => (int) round($channel + (255 - $channel) * $whiteness);
        $color = imagecolorallocate($image, $mix(($under >> 16) & 0xFF), $mix(($under >> 8) & 0xFF), $mix($under & 0xFF));
        imagefilledrectangle($image, $x + $radius, $y, $x + $width - $radius - 1, $y + $height - 1, $color);
        imagefilledarc($image, $x + $radius, $y + $radius, $height, $height, 90, 270, $color, IMG_ARC_PIE);
        imagefilledarc($image, $x + $width - $radius - 1, $y + $radius, $height, $height, 270, 90, $color, IMG_ARC_PIE);
    }

    /**
     * De algemene deelafbeelding van het platform (public/og.png).
     */
    public function platform(): GdImage
    {
        $image = $this->canvas();
        $this->gradient($image, '#1d1d1f', '#000000');
        $white = imagecolorallocate($image, 255, 255, 255);
        $soft = imagecolorallocatealpha($image, 255, 255, 255, 30);

        imagecopy($image, BrandMark::image(104, dark: false), 72, 72, 0, 0, 104, 104);
        imagettftext($image, 34, 0, 196, 138, $white, $this->font('Bold'), 'barbercuts');
        imagettftext($image, 78, 0, 72, 330, $white, $this->font('Bold'), 'Je hele kapperszaak.');
        imagettftext($image, 78, 0, 72, 430, $white, $this->font('Bold'), 'In je broekzak.');
        imagettftext($image, 30, 0, 72, 520, $soft, $this->font('Medium'), 'Afspraken, agenda en omzet in één app · barbercuts.nl');

        return $image;
    }

    private function drawShop(Barbershop $shop): GdImage
    {
        $image = $this->canvas();
        $accent = Color::isHex($shop->accent_color) ? $shop->accent_color : Barbershop::DEFAULT_ACCENT;
        $cover = $this->load($shop->cover_path);

        if ($cover !== null) {
            $this->cover($image, $cover);
            $this->shade($image, 0.55);
        } else {
            [$r, $g, $b] = Color::rgb($accent);
            $this->gradient($image, $accent, sprintf('#%02x%02x%02x', (int) ($r * 0.55), (int) ($g * 0.55), (int) ($b * 0.55)));
            // Lichte kleuren iets donkerder, zodat de witte tekst leesbaar blijft.
            $this->shade($image, Color::luminance($accent) > 0.4 ? 0.4 : 0.12);
        }

        $white = imagecolorallocate($image, 255, 255, 255);
        $soft = imagecolorallocatealpha($image, 255, 255, 255, 28);
        $x = 72;

        // Logo, of de eerste letter op een wit vlak
        $tile = 132;
        $this->roundedBox($image, $x, 72, $tile, $tile, 30, $white);
        $logo = $this->load($shop->logo_path);

        if ($logo !== null) {
            imagecopyresampled($image, $logo, $x + 8, 80, 0, 0, $tile - 16, $tile - 16, imagesx($logo), imagesy($logo));
        } else {
            [$r, $g, $b] = Color::rgb($accent);
            $letter = mb_strtoupper(mb_substr($shop->name, 0, 1));
            $box = imagettfbbox(64, 0, $this->font('Bold'), $letter);
            imagettftext($image, 64, 0, (int) ($x + ($tile - ($box[2] - $box[0])) / 2 - $box[0]), 72 + 98, imagecolorallocate($image, $r, $g, $b), $this->font('Bold'), $letter);
        }

        // Naam, op hooguit twee regels
        $lines = $this->wrap($shop->name, 74, self::WIDTH - 2 * $x, 2);
        $y = 300;

        foreach ($lines as $line) {
            imagettftext($image, 74, 0, $x, $y, $white, $this->font('Bold'), $line);
            $y += 92;
        }

        $y -= 30;

        if ($shop->hasRating()) {
            $filled = (int) round((float) $shop->rating_avg);
            $star = imagecolorallocate($image, 255, 159, 10);
            $empty = imagecolorallocatealpha($image, 255, 255, 255, 80);

            foreach (range(1, 5) as $index) {
                $this->star($image, $x + 20 + ($index - 1) * 48, $y - 14, 20, $index <= $filled ? $star : $empty);
            }

            $label = number_format((float) $shop->rating_avg, 1, ',', '').' · '.$shop->rating_count.' '.($shop->rating_count === 1 ? 'review' : 'reviews');
            imagettftext($image, 32, 0, $x + 256, $y, $white, $this->font('Bold'), $label);
            $y += 56;
        }

        if ($shop->city) {
            imagettftext($image, 30, 0, $x, $y, $soft, $this->font('Medium'), 'Barbershop in '.$shop->city);
        }

        // Onderaan: de uitnodiging om te boeken, en het merk
        $cta = 'Boek online';
        $box = imagettfbbox(30, 0, $this->font('Bold'), $cta);
        $width = $box[2] - $box[0] + 72;
        $this->roundedBox($image, $x, self::HEIGHT - 72 - 68, $width, 68, 34, $white);
        imagettftext($image, 30, 0, $x + 36, self::HEIGHT - 72 - 22, imagecolorallocate($image, 29, 29, 31), $this->font('Bold'), $cta);

        $brand = 'barbercuts';
        $box = imagettfbbox(28, 0, $this->font('Bold'), $brand);
        $textWidth = $box[2] - $box[0];
        imagettftext($image, 28, 0, self::WIDTH - $x - $textWidth, self::HEIGHT - 72 - 24, $white, $this->font('Bold'), $brand);
        imagecopy($image, BrandMark::image(52, dark: false), self::WIDTH - $x - $textWidth - 68, self::HEIGHT - 72 - 60, 0, 0, 52, 52);

        return $image;
    }

    private function canvas(): GdImage
    {
        $image = imagecreatetruecolor(self::WIDTH, self::HEIGHT);
        imagealphablending($image, true);
        imageantialias($image, true);

        return $image;
    }

    private function font(string $weight): string
    {
        return resource_path("fonts/Inter-{$weight}.ttf");
    }

    private function gradient(GdImage $image, string $from, string $to): void
    {
        [$r1, $g1, $b1] = Color::rgb($from);
        [$r2, $g2, $b2] = Color::rgb($to);

        for ($y = 0; $y < self::HEIGHT; $y++) {
            $t = $y / (self::HEIGHT - 1);
            imageline($image, 0, $y, self::WIDTH, $y, imagecolorallocate($image, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t)));
        }
    }

    /**
     * Leg een donkere laag over het beeld (0 = niets, 1 = zwart).
     */
    private function shade(GdImage $image, float $strength): void
    {
        imagefilledrectangle($image, 0, 0, self::WIDTH, self::HEIGHT, imagecolorallocatealpha($image, 0, 0, 0, (int) round(127 * (1 - $strength))));
    }

    /**
     * Vul het beeld met de foto, bijgesneden vanuit het midden.
     */
    private function cover(GdImage $image, GdImage $photo): void
    {
        $scale = max(self::WIDTH / imagesx($photo), self::HEIGHT / imagesy($photo));
        $width = (int) round(self::WIDTH / $scale);
        $height = (int) round(self::HEIGHT / $scale);

        imagecopyresampled($image, $photo, 0, 0, (int) ((imagesx($photo) - $width) / 2), (int) ((imagesy($photo) - $height) / 2), self::WIDTH, self::HEIGHT, $width, $height);
    }

    private function load(?string $path): ?GdImage
    {
        if ($path === null) {
            return null;
        }

        try {
            $loaded = imagecreatefromstring((string) Storage::disk(ImageStore::disk())->get($path));
        } catch (Throwable) {
            return null;
        }

        return $loaded instanceof GdImage ? $loaded : null;
    }

    private function roundedBox(GdImage $image, int $x, int $y, int $width, int $height, int $radius, int $color): void
    {
        imagefilledrectangle($image, $x + $radius, $y, $x + $width - $radius, $y + $height, $color);
        imagefilledrectangle($image, $x, $y + $radius, $x + $width, $y + $height - $radius, $color);

        foreach ([[$x + $radius, $y + $radius], [$x + $width - $radius, $y + $radius], [$x + $radius, $y + $height - $radius], [$x + $width - $radius, $y + $height - $radius]] as [$cx, $cy]) {
            imagefilledellipse($image, $cx, $cy, $radius * 2, $radius * 2, $color);
        }
    }

    private function star(GdImage $image, int $cx, int $cy, int $radius, int $color): void
    {
        $points = [];

        foreach (range(0, 9) as $index) {
            $angle = -M_PI / 2 + $index * M_PI / 5;
            $r = $index % 2 === 0 ? $radius : $radius * 0.45;
            $points[] = (int) round($cx + cos($angle) * $r);
            $points[] = (int) round($cy + sin($angle) * $r);
        }

        imagefilledpolygon($image, $points, $color);
    }

    /**
     * Breek tekst af op woorden zodat elke regel binnen de breedte past; de laatste regel krijgt zo nodig "…".
     *
     * @return list<string>
     */
    private function wrap(string $text, int $size, int $maxWidth, int $maxLines): array
    {
        $fits = function (string $line) use ($size, $maxWidth): bool {
            $box = imagettfbbox($size, 0, $this->font('Bold'), $line);

            return $maxWidth >= $box[2] - $box[0];
        };

        $lines = [];
        $current = '';

        foreach (preg_split('/\s+/', trim($text)) ?: [] as $word) {
            $candidate = trim($current.' '.$word);

            if ($current === '' || $fits($candidate)) {
                $current = $candidate;

                continue;
            }

            $lines[] = $current;
            $current = $word;
        }

        $lines[] = $current;

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $lines[$maxLines - 1] .= '…';
        }

        foreach ($lines as $index => $line) {
            while (! $fits($line) && mb_strlen($line) > 2) {
                $line = mb_substr($line, 0, -2).'…';
            }

            $lines[$index] = $line;
        }

        return $lines;
    }
}
