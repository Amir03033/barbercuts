<div>
    <x-ui.page-header title="Uiterlijk" :back="route('app.more')">
        <x-ui.button :href="$shop->bookingUrl()" variant="ghost" size="sm" target="_blank" rel="noopener">Bekijk pagina</x-ui.button>
    </x-ui.page-header>

    <form wire:submit="save" class="space-y-8 px-4 py-4" novalidate>
        <x-ui.section title="Thema">
            <x-ui.segmented label="Thema" wire:model.live="themeMode" :value="$themeMode" name="themeMode"
                :options="['light' => 'Licht', 'dark' => 'Donker']" />

            <x-ui.field label="Accentkleur" name="accentColor">
                <x-ui.color-swatches :colors="$presets" wire:model.live="accentColor" :value="$accentColor" name="accentPreset" label="Accentkleur" />
            </x-ui.field>
            <label class="flex min-h-11 items-center gap-3 text-sm text-muted">
                <input type="color" wire:model.live.debounce.200ms="accentColor" value="{{ $accentColor }}" aria-label="Eigen kleur kiezen"
                    class="size-11 shrink-0 cursor-pointer rounded-card border border-border bg-bg p-1" />
                <span>Of kies een eigen kleur</span>
            </label>

            @if ($contrastWarning)
                <x-ui.alert type="warning">{{ $contrastWarning }}</x-ui.alert>
            @endif
        </x-ui.section>

        <x-ui.section title="Logo en omslagfoto">
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-2">
                    <x-ui.file-button wire:model="logo">{{ $shop->logo_path || $logoPreview ? 'Ander logo' : 'Logo kiezen' }}</x-ui.file-button>
                    @if ($shop->logo_path)
                        <x-ui.button variant="ghost" size="sm" full wire:click="removeLogo">Logo weghalen</x-ui.button>
                    @endif
                </div>
                <div class="space-y-2">
                    <x-ui.file-button wire:model="cover">{{ $shop->cover_path || $coverPreview ? 'Andere omslag' : 'Omslagfoto' }}</x-ui.file-button>
                    @if ($shop->cover_path)
                        <x-ui.button variant="ghost" size="sm" full wire:click="removeCover">Omslag weghalen</x-ui.button>
                    @endif
                </div>
            </div>
        </x-ui.section>

        <x-ui.section title="Banner" text="Een mededeling of actie bovenaan je pagina, bijvoorbeeld: “Deze maand 10% korting voor studenten”.">
            <x-ui.card flat class="space-y-4">
                <x-ui.toggle label="Banner tonen" wire:model.live="bannerActive" :checked="$bannerActive" />
                <x-ui.input label="Tekst" wire:model.blur="bannerText" maxlength="160" optional />
                @if ($bannerPreview || $shop->bannerUrl())
                    <img src="{{ $bannerPreview ?? $shop->bannerUrl() }}" alt="Afbeelding van de banner" class="aspect-[16/9] w-full rounded-card object-cover" width="640" height="360" />
                @endif
                <div class="grid grid-cols-2 gap-3">
                    <x-ui.file-button wire:model="bannerImage">{{ $shop->banner_path || $bannerPreview ? 'Andere afbeelding' : 'Afbeelding kiezen' }}</x-ui.file-button>
                    @if ($shop->banner_path)
                        <x-ui.button variant="ghost" size="sm" full wire:click="removeBannerImage">Afbeelding weghalen</x-ui.button>
                    @endif
                </div>
                @error('bannerImage')
                    <p class="text-sm text-danger">{{ $message }}</p>
                @enderror
            </x-ui.card>
        </x-ui.section>

        <x-ui.section title="Links" text="Staan als knoppen op je pagina.">
            <x-ui.input label="Instagram" wire:model.blur="instagram" optional placeholder="studiofade" autocapitalize="none" autocomplete="off" />
            <x-ui.input label="Website" wire:model.blur="website" optional placeholder="studiofade.nl" inputmode="url" autocapitalize="none" autocomplete="url" />
        </x-ui.section>

        <x-ui.section title="Over je zaak">
            <x-ui.textarea label="Omschrijving" wire:model.blur="description" optional placeholder="Bijvoorbeeld: Barbershop in het centrum, sinds 2015." />
            <x-ui.input label="Adres" wire:model.blur="address" autocomplete="street-address" optional />
            <x-ui.input label="Postcode" wire:model.blur="postalCode" autocomplete="postal-code" optional />
        </x-ui.section>

        <x-ui.section title="Foto's" text="Laat je zaak en je werk zien. Foto's worden meteen opgeslagen; de eerste foto's ziet een klant het eerst.">
            @if ($images->isNotEmpty())
                <div class="grid grid-cols-3 gap-2">
                    @foreach ($images as $image)
                        <div class="relative" wire:key="photo-{{ $image->id }}">
                            <img src="{{ $image->url() }}" alt="Foto" loading="lazy" width="200" height="200" class="aspect-square w-full rounded-card object-cover" />
                            <x-ui.icon-button icon="x" label="Foto verwijderen" wire:click="deletePhoto({{ $image->id }})" wire:confirm="Deze foto verwijderen?"
                                class="absolute top-1 right-1 !size-9 bg-bg/90 !text-text shadow-soft" />
                            @unless ($loop->first)
                                <x-ui.icon-button icon="chevron-left" label="Foto naar voren" wire:click="movePhotoForward({{ $image->id }})"
                                    class="absolute bottom-1 left-1 !size-9 bg-bg/90 !text-text shadow-soft" />
                            @endunless
                        </div>
                    @endforeach
                </div>
            @endif
            <p class="text-xs text-muted">{{ $images->count() }} van {{ $maxPhotos }} foto's</p>
            <x-ui.file-button wire:model="photos" multiple icon="plus">Foto's toevoegen</x-ui.file-button>
            @error('photos.*')
                <p class="text-sm text-danger">{{ $message }}</p>
            @enderror
        </x-ui.section>

        <x-ui.section title="Voorbeeld" text="Zo zien klanten je boekingspagina.">
            <x-shop.page preview :shop="$shop" :services="$services" :staff-members="$staffMembers" :week="$week" :images="$images"
                :theme-mode="$themeMode" :accent-color="$accentColor" :logo-url="$logoPreview" :cover-url="$coverPreview" />
        </x-ui.section>

        <x-ui.sticky-footer nav>
            <x-ui.button type="submit" loading="save" full>Opslaan</x-ui.button>
        </x-ui.sticky-footer>
    </form>
</div>
