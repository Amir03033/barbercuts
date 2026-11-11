<?php

namespace App\Livewire\App\Customers;

use App\Livewire\Concerns\InShop;
use App\Models\Customer;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Klanten (§9.1): sticky zoekbalk en een lijst met naam en laatste bezoek.
 */
#[Layout('layouts::app', ['active' => 'customers', 'wide' => true])]
#[Title('Klanten')]
class Index extends Component
{
    use InShop;

    private const int PAGE = 40;

    #[Url(as: 'q')]
    public string $search = '';

    public int $limit = self::PAGE;

    public function updatedSearch(): void
    {
        $this->limit = self::PAGE;
    }

    public function loadMore(): void
    {
        $this->limit += self::PAGE;
    }

    public function render(): View
    {
        $search = trim($this->search);
        $digits = ltrim((string) preg_replace('/\D+/', '', $search), '0');

        $query = Customer::query()->when($search !== '', function ($query) use ($search, $digits): void {
            $query->where(function ($query) use ($search, $digits): void {
                $query->where('name', 'like', '%'.$search.'%');

                if (strlen($digits) >= 3) {
                    $query->orWhere('phone', 'like', '%'.$digits.'%');
                }
            });
        });

        $total = (clone $query)->count();

        return view('livewire.app.customers.index', [
            'shop' => $this->shop(),
            'customers' => $query->orderBy('name')->orderBy('id')->limit($this->limit)->get(),
            'total' => $total,
        ]);
    }
}
