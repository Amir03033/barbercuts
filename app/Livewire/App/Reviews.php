<?php

namespace App\Livewire\App;

use App\Livewire\Concerns\InShop;
use App\Models\Review;
use App\Services\ReviewService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Reviews van de zaak (fase 7): iedereen in de zaak leest mee; de eigenaar reageert of meldt een review bij het platform.
 * Verwijderen kan niet: alleen de platformadmin kan een gemelde review verbergen.
 */
#[Layout('layouts::app', ['active' => 'more'])]
#[Title('Reviews')]
class Reviews extends Component
{
    use InShop;

    #[Locked]
    public ?int $reviewId = null;

    public string $reply = '';

    public string $reportReason = '';

    public function startReply(int $reviewId): void
    {
        $this->authorizeOwner();

        $review = Review::query()->findOrFail($reviewId);
        $this->reviewId = $review->id;
        $this->reply = (string) $review->reply;
        $this->dispatch('open-sheet', name: 'reply');
    }

    public function saveReply(ReviewService $reviews): void
    {
        $this->authorizeOwner();
        $this->validate(['reply' => ['nullable', 'string', 'max:1000']], attributes: ['reply' => 'reactie']);

        $reviews->reply(Review::query()->findOrFail($this->reviewId), $this->reply);

        $this->dispatch('close-sheet');
        $this->dispatch('toast', message: trim($this->reply) === '' ? 'Reactie verwijderd' : 'Reactie geplaatst');
        $this->reset('reply', 'reviewId');
    }

    public function startReport(int $reviewId): void
    {
        $this->authorizeOwner();

        $this->reviewId = Review::query()->findOrFail($reviewId)->id;
        $this->reportReason = '';
        $this->dispatch('open-sheet', name: 'report');
    }

    public function report(ReviewService $reviews): void
    {
        $this->authorizeOwner();
        $this->validate(['reportReason' => ['required', 'string', 'min:5', 'max:255']], attributes: ['reportReason' => 'reden']);

        $reviews->report(Review::query()->findOrFail($this->reviewId), $this->reportReason);

        $this->dispatch('close-sheet');
        $this->dispatch('toast', message: 'Gemeld. We kijken ernaar.');
        $this->reset('reportReason', 'reviewId');
    }

    public function render(): View
    {
        return view('livewire.app.reviews', [
            'shop' => $this->shop()->refresh(),
            'isOwner' => $this->isOwner(),
            'reviews' => Review::query()->with(['customer', 'staffMember'])->latest()->limit(100)->get(),
        ]);
    }
}
