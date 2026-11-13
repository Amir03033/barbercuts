<?php

namespace App\Console\Commands;

use App\Services\ShareImage;
use App\Support\BrandMark;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

/**
 * Maakt alle iconen van de site en de app uit het beeldmerk (App\Support\BrandMark): favicon, app-iconen voor
 * Android en iOS en het icoon voor meldingen. Draai dit opnieuw als het beeldmerk verandert; de bestanden staan in Git.
 */
#[Signature('barbercuts:make-icons')]
#[Description('Maak de favicons en app-iconen opnieuw uit het beeldmerk')]
class MakeIcons extends Command
{
    public function handle(): int
    {
        $icons = public_path('icons');

        // [bestand, grootte, afgeronde hoeken, schaal van de letter]
        $files = [
            [$icons.'/icon-192.png', 192, true, 1.0],
            [$icons.'/icon-512.png', 512, true, 1.0],
            // "Maskable" (Android): volledig vlak, de letter kleiner zodat een ronde uitsnede hem niet raakt.
            [$icons.'/icon-maskable-192.png', 192, false, 0.72],
            [$icons.'/icon-maskable-512.png', 512, false, 0.72],
            // iOS rondt het icoon zelf af en wil geen doorzichtige hoeken.
            [$icons.'/apple-touch-icon.png', 180, false, 0.9],
            [public_path('favicon-32.png'), 32, true, 1.0],
            [public_path('favicon-48.png'), 48, true, 1.0],
        ];

        foreach ($files as [$path, $size, $rounded, $scale]) {
            imagepng(BrandMark::image($size, $rounded, $scale), $path, 9);
            $this->line('✓ '.str_replace(public_path().'/', '', $path));
        }

        file_put_contents(public_path('favicon.svg'), BrandMark::svg());

        // favicon.ico met de png van 32 en 48 pixels erin (voor browsers die alleen /favicon.ico opvragen).
        $images = array_map(fn (int $size): string => (string) file_get_contents(public_path("favicon-{$size}.png")), [32, 48]);
        $offset = 6 + 16 * count($images);
        $ico = pack('vvv', 0, 1, count($images));

        foreach ([32, 48] as $index => $size) {
            $ico .= pack('CCCCvvVV', $size, $size, 0, 0, 1, 32, strlen($images[$index]), $offset);
            $offset += strlen($images[$index]);
        }

        file_put_contents(public_path('favicon.ico'), $ico.implode('', $images));
        unlink(public_path('favicon-48.png'));

        // De afbeelding bij het delen van barbercuts.nl zelf
        imagepng(app(ShareImage::class)->platform(), public_path('og.png'), 7);

        $this->info('Klaar: og.png, favicon.svg, favicon.ico, favicon-32.png en de app-iconen in public/icons.');

        return self::SUCCESS;
    }
}
