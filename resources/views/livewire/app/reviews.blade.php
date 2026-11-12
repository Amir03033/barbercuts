<div>
    <x-ui.page-header title="Reviews" :back="route('app.more')" />

    <div class="space-y-4 px-4 py-4">
        @if ($shop->hasRating())
            <x-ui.card flat class="flex items-center gap-4">
                <p class="text-display font-bold tabular-nums">{{ number_format($shop->rating_avg, 1, ',', '') }}</p>
                <div>
                    <x-ui.stars :rating="$shop->rating_avg" size="20" />
                    <p class="text-sm text-muted">{{ $shop->rating_count }} {{ $shop->rating_count === 1 ? 'review' : 'reviews' }} van klanten die er geweest zijn</p>
                </div>
            </x-ui.card>
        @endif

        @forelse ($reviews as $review)
            <x-ui.card flat wire:key="review-{{ $review->id }}" class="!py-0">
                <x-shop.review :review="$review" />
                @if ($review->hidden_at)
                    <p class="pb-3 text-xs text-muted">Verborgen door BarberCuts; telt niet mee in je score.</p>
                @elseif ($isOwner)
                    <div class="flex gap-2 border-t border-border py-2">
                        <x-ui.button variant="ghost" size="sm" wire:click="startReply({{ $review->id }})">{{ $review->reply ? 'Reactie wijzigen' : 'Reageren' }}</x-ui.button>
                        @if ($review->reported_at)
                            <span class="flex items-center px-3 text-sm text-muted">Gemeld</span>
                        @else
                            <x-ui.button variant="ghost" size="sm" wire:click="startReport({{ $review->id }})">Melden</x-ui.button>
                        @endif
                    </div>
                @endif
            </x-ui.card>
        @empty
            <x-ui.empty-state icon="star" title="Nog geen reviews"
                text="Na een voltooide afspraak vragen we je klant automatisch om een review. Ze verschijnen hier en op je pagina." />
        @endforelse
    </div>

    @if ($isOwner)
        <x-ui.bottom-sheet name="reply" title="Reageren op review">
            <form wire:submit="saveReply" class="space-y-4" novalidate>
                <x-ui.textarea label="Je reactie" wire:model="reply" rows="4" maxlength="1000" hint="Staat onder de review op je pagina. Leeg laten haalt je reactie weg." />
                <x-ui.button type="submit" loading="saveReply" full>Opslaan</x-ui.button>
            </form>
        </x-ui.bottom-sheet>

        <x-ui.bottom-sheet name="report" title="Review melden">
            <form wire:submit="report" class="space-y-4" novalidate>
                <p class="text-sm text-muted">Een review verwijderen kan niet. Is hij beledigend, nep of niet van een klant? Meld hem, dan kijken wij ernaar.</p>
                <x-ui.textarea label="Wat is er mis?" wire:model="reportReason" rows="3" maxlength="255" />
                <x-ui.button type="submit" loading="report" full>Melden</x-ui.button>
            </form>
        </x-ui.bottom-sheet>
    @endif
</div>
