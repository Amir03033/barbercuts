<?php

namespace App\Http\Controllers;

use App\Models\Barbershop;
use App\Services\ShopDirectory;
use Illuminate\Http\Response;

/**
 * sitemap.xml voor zoekmachines: de vaste pagina's, de kappersgids per plaats en elke echte, publieke zaak.
 * Demozaken, geblokkeerde zaken en zaken die nog niet klaar zijn met aanmelden staan er nooit in.
 */
class SitemapController extends Controller
{
    public function __invoke(ShopDirectory $directory): Response
    {
        $urls = collect([
            route('home'),
            route('pricing'),
            route('directory'),
            route('terms'),
            route('privacy'),
            route('contact'),
        ])->map(fn (string $url): array => ['loc' => $url, 'lastmod' => null]);

        $cities = $directory->cities(500)
            ->map(fn (array $city): array => ['loc' => route('directory.city', $city['slug']), 'lastmod' => null]);

        $shops = Barbershop::query()->listed()->orderBy('id')->get(['slug', 'updated_at'])
            ->map(fn (Barbershop $shop): array => ['loc' => url('/'.$shop->slug), 'lastmod' => $shop->updated_at?->toAtomString()]);

        $entries = $urls->concat($cities)->concat($shops)->map(function (array $entry): string {
            $lastmod = $entry['lastmod'] === null ? '' : '<lastmod>'.$entry['lastmod'].'</lastmod>';

            return '<url><loc>'.e($entry['loc']).'</loc>'.$lastmod.'</url>';
        })->implode('');

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'.$entries.'</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }
}
