<?php

use App\Filament\Resources\PromoVideos\Pages\CreatePromoVideo;
use App\Filament\Resources\PromoVideos\Pages\ListPromoVideos;
use App\Filament\Resources\PromoVideos\PromoVideoResource;
use App\Livewire\PitcherOverview;
use App\Models\PromoVideo;
use App\Models\User;
use App\Support\CurrentShop;
use App\Support\ImageStore;
use App\Support\UploadLimit;
use Filament\Facades\Filament;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake(ImageStore::disk());
    Filament::setCurrentPanel('admin');
    app(CurrentShop::class)->disableScope();
});

function promoVideo(array $attributes = []): PromoVideo
{
    Storage::disk(ImageStore::disk())->put('videos/agenda.mp4', 'video');

    return PromoVideo::query()->create($attributes + [
        'title' => 'Agenda in 30 seconden', 'file_path' => 'videos/agenda.mp4', 'orientation' => 'landscape',
        'show_on_demo' => false, 'show_on_home' => false, 'for_pitchers' => false, 'is_active' => true,
    ]);
}

it('lets an admin upload a video and choose where it shows', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(CreatePromoVideo::class)
        ->fillForm([
            'title' => 'Agenda in 30 seconden',
            'caption' => 'Zo boeken je klanten zelf',
            'file_path' => UploadedFile::fake()->create('agenda.mp4', 1000, 'video/mp4'),
            'orientation' => 'portrait',
            'show_on_demo' => true,
            'for_pitchers' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $video = PromoVideo::query()->sole();

    expect($video->title)->toBe('Agenda in 30 seconden')
        ->and($video->orientation)->toBe('portrait')
        ->and($video->show_on_demo)->toBeTrue()
        ->and($video->show_on_home)->toBeFalse()
        ->and($video->file_path)->toStartWith('videos/');

    Storage::disk(ImageStore::disk())->assertExists($video->file_path);

    Livewire::test(ListPromoVideos::class)->assertCanSeeTableRecords([$video]);
});

it('only accepts video files', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    Livewire::test(CreatePromoVideo::class)
        ->fillForm(['title' => 'Fout', 'file_path' => UploadedFile::fake()->create('tekst.pdf', 100, 'application/pdf'), 'orientation' => 'landscape'])
        ->call('create')
        ->assertHasFormErrors(['file_path']);

    expect(PromoVideo::query()->count())->toBe(0);
});

it('shows each video only where it is switched on', function () {
    $demo = promoVideo(['title' => 'Voor de demo', 'caption' => 'Kijk mee op de demo', 'show_on_demo' => true]);
    $home = promoVideo(['title' => 'Voor de landing', 'caption' => 'Kijk mee op de landing', 'show_on_home' => true]);
    promoVideo(['title' => 'Uit', 'caption' => 'Staat uit', 'show_on_demo' => true, 'show_on_home' => true, 'is_active' => false]);

    $this->get('/probeer')->assertOk()->assertSee('Kijk mee op de demo')->assertSee($demo->url(), false)->assertDontSee('Kijk mee op de landing')->assertDontSee('Staat uit');
    $this->get('/')->assertOk()->assertSee('Bekijk het in 30 seconden')->assertSee('Kijk mee op de landing')->assertDontSee('Staat uit');
});

it('hides the video blocks when there are no videos', function () {
    $this->get('/')->assertOk()->assertDontSee('Bekijk het in 30 seconden');
    $this->get('/probeer')->assertOk()->assertDontSee('Liever eerst kijken');
});

it('gives pitchers their videos to share with their demo link', function () {
    $pitcher = User::factory()->create(['name' => 'Sam Jansen']);
    $pitcher->forceFill(['is_pitcher' => true])->save();
    $video = promoVideo(['for_pitchers' => true]);

    Livewire::actingAs($pitcher)->test(PitcherOverview::class)
        ->assertSee("Video's om te delen", false)
        ->assertSee('Agenda in 30 seconden')
        ->assertSee(str_replace('/', '\\/', 'Probeer het zelf, zonder account: '.$pitcher->demoLink()), false)
        ->assertSee('download="'.$video->downloadName().'"', false);
});

it('removes the file together with the video', function () {
    $video = promoVideo();

    $video->delete();

    Storage::disk(ImageStore::disk())->assertMissing('videos/agenda.mp4');
});

it('reads the upload limit of the server and explains it when it is low', function () {
    expect(UploadLimit::toKilobytes('2M'))->toBe(2048)
        ->and(UploadLimit::toKilobytes('128M'))->toBe(131072)
        ->and(UploadLimit::toKilobytes('1G'))->toBe(1048576)
        ->and(UploadLimit::toKilobytes('-1'))->toBe(0)
        ->and(UploadLimit::label(2048))->toBe('2 MB');

    expect(PromoVideoResource::maxKilobytes())->toBeLessThanOrEqual(PromoVideoResource::MAX_KB);

    if (UploadLimit::kilobytes() > 0 && UploadLimit::kilobytes() < 30 * 1024) {
        expect(PromoVideoResource::uploadHelp())->toContain('upload_max_filesize');
    }
});
