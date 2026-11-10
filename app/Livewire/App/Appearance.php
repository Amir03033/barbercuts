<?php

namespace App\Livewire\App;

use App\Enums\ThemeMode;
use App\Livewire\Concerns\InShop;
use App\Models\Service;
use App\Models\ShopImage;
use App\Models\StaffMember;
use App\Support\Color;
use App\Support\ImageStore;
use App\Support\WeekSchedule;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Uiterlijk van de boekingspagina: licht/donker, accentkleur, logo, omslagfoto en foto's, met live voorbeeld (§7.9).
 */
#[Layout('layouts::app', ['active' => 'more'])]
#[Title('Uiterlijk')]
class Appearance extends Component
{
    use InShop;
    use WithFileUploads;

    private const int MAX_PHOTOS = 12;

    public string $themeMode = 'light';

    public string $accentColor = '';

    public string $description = '';

    public string $address = '';

    public string $postalCode = '';

    /** @var UploadedFile|null */
    public $logo = null;

    /** @var UploadedFile|null */
    public $cover = null;

    /** @var array<int, UploadedFile> */
    public array $photos = [];

    public function mount(): void
    {
        $this->authorizeOwner();

        $shop = $this->shop();

        $this->themeMode = $shop->theme_mode->value;
        $this->accentColor = $shop->accent_color;
        $this->description = (string) $shop->description;
        $this->address = (string) $shop->address;
        $this->postalCode = (string) $shop->postal_code;
    }

    public function updatedLogo(): void
    {
        $this->validateOnly('logo', ['logo' => ['nullable', 'image', 'max:5120']], attributes: ['logo' => 'logo']);
    }

    public function updatedCover(): void
    {
        $this->validateOnly('cover', ['cover' => ['nullable', 'image', 'max:8192']], attributes: ['cover' => 'omslagfoto']);
    }

    /**
     * Foto's worden meteen opgeslagen na het kiezen.
     */
    public function updatedPhotos(): void
    {
        $this->authorizeOwner();

        $this->validate(['photos.*' => ['image', 'max:8192']], attributes: ['photos.*' => 'foto']);

        $shop = $this->shop();
        $count = ShopImage::query()->count();

        foreach ($this->photos as $photo) {
            if ($count >= self::MAX_PHOTOS) {
                $this->dispatch('toast', message: 'Je kunt maximaal '.self::MAX_PHOTOS." foto's toevoegen.", type: 'error');

                break;
            }

            ShopImage::create([
                'path' => ImageStore::storeWebp($photo, "shops/{$shop->id}/photos", 1200, 1200),
                'sort_order' => $count++,
            ]);
        }

        $this->photos = [];
    }

    public function deletePhoto(int $imageId): void
    {
        $this->authorizeOwner();

        $image = ShopImage::query()->findOrFail($imageId);

        ImageStore::delete($image->path);
        $image->delete();
    }

    public function removeLogo(): void
    {
        $this->authorizeOwner();
        $this->logo = null;

        $shop = $this->shop();
        ImageStore::delete($shop->logo_path);
        $shop->update(['logo_path' => null]);
    }

    public function removeCover(): void
    {
        $this->authorizeOwner();
        $this->cover = null;

        $shop = $this->shop();
        ImageStore::delete($shop->cover_path);
        $shop->update(['cover_path' => null]);
    }

    public function save(): void
    {
        $this->authorizeOwner();

        $this->accentColor = strtolower($this->accentColor);

        $this->validate([
            'themeMode' => ['required', Rule::enum(ThemeMode::class)],
            'accentColor' => ['required', 'regex:/^#[0-9a-f]{6}$/'],
            'description' => ['nullable', 'string', 'max:500'],
            'address' => ['nullable', 'string', 'max:120'],
            'postalCode' => ['nullable', 'string', 'max:10'],
            'logo' => ['nullable', 'image', 'max:5120'],
            'cover' => ['nullable', 'image', 'max:8192'],
        ], attributes: [
            'accentColor' => 'accentkleur', 'description' => 'omschrijving', 'address' => 'adres', 'postalCode' => 'postcode',
            'logo' => 'logo', 'cover' => 'omslagfoto',
        ]);

        $shop = $this->shop();

        $data = [
            'theme_mode' => $this->themeMode,
            'accent_color' => $this->accentColor,
            'description' => trim($this->description) ?: null,
            'address' => trim($this->address) ?: null,
            'postal_code' => strtoupper(trim($this->postalCode)) ?: null,
        ];

        if ($this->logo !== null) {
            ImageStore::delete($shop->logo_path);
            $data['logo_path'] = ImageStore::storeWebp($this->logo, "shops/{$shop->id}", 400, 400);
        }

        if ($this->cover !== null) {
            ImageStore::delete($shop->cover_path);
            $data['cover_path'] = ImageStore::storeWebp($this->cover, "shops/{$shop->id}", 1600, 900);
        }

        $shop->update($data);

        $this->logo = null;
        $this->cover = null;

        $this->dispatch('toast', message: 'Uiterlijk opgeslagen');
    }

    /**
     * Waarschuwing als de accentkleur te weinig contrast heeft met de achtergrond van het thema (§7.9).
     */
    private function contrastWarning(): ?string
    {
        if (! Color::isHex($this->accentColor)) {
            return null;
        }

        $background = $this->themeMode === 'dark' ? Color::DARK_BACKGROUND : Color::LIGHT_BACKGROUND;

        return Color::contrast($this->accentColor, $background) < 3
            ? 'Deze kleur valt weinig op tegen de '.($this->themeMode === 'dark' ? 'donkere' : 'lichte').' achtergrond. Kies een '.($this->themeMode === 'dark' ? 'lichtere' : 'donkerdere').' kleur.'
            : null;
    }

    public function render(): View
    {
        $shop = $this->shop();

        return view('livewire.app.appearance', [
            'shop' => $shop,
            'presets' => Color::PRESETS,
            'contrastWarning' => $this->contrastWarning(),
            'services' => Service::query()->where('is_active', true)->orderBy('sort_order')->limit(3)->get(),
            'staffMembers' => StaffMember::query()->where('is_active', true)->orderBy('sort_order')->get(),
            'week' => WeekSchedule::fromRows($shop->openingHours()->get(), 'opens_at', 'closes_at'),
            'images' => $shop->images()->get(),
            'logoPreview' => $this->previewUrl($this->logo),
            'coverPreview' => $this->previewUrl($this->cover),
        ]);
    }

    private function previewUrl(mixed $file): ?string
    {
        if (! $file instanceof UploadedFile || ! method_exists($file, 'temporaryUrl')) {
            return null;
        }

        try {
            return $file->temporaryUrl();
        } catch (\Throwable) {
            return null;
        }
    }
}
