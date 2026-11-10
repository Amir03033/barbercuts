<?php

namespace App\Livewire\App\Services;

use App\Livewire\Concerns\InShop;
use App\Models\Service;
use App\Models\StaffMember;
use App\Services\ShopSetupService;
use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Dienst toevoegen of bewerken, inclusief welke kappers de dienst doen.
 */
#[Layout('layouts::app', ['active' => 'more'])]
#[Title('Dienst')]
class Form extends Component
{
    use InShop;

    #[Locked]
    public ?int $serviceId = null;

    public string $name = '';

    public string $description = '';

    public string $duration = '30';

    public string $price = '';

    public string $bufferAfter = '0';

    public bool $isActive = true;

    /** @var list<int|string> */
    public array $staffIds = [];

    public function mount(?int $service = null): void
    {
        $this->authorizeOwner();

        $allStaff = StaffMember::query()->pluck('id')->all();

        if ($service === null) {
            $this->staffIds = $allStaff;

            return;
        }

        $model = Service::query()->findOrFail($service);

        $this->serviceId = $model->id;
        $this->name = $model->name;
        $this->description = (string) $model->description;
        $this->duration = (string) $model->duration;
        $this->price = money_input($model->price_cents);
        $this->bufferAfter = (string) $model->buffer_after;
        $this->isActive = $model->is_active;

        // Geen koppeling = iedereen doet deze dienst (§7.1).
        $linked = $model->staffMembers()->pluck('staff_members.id')->all();
        $this->staffIds = $linked === [] ? $allStaff : $linked;
    }

    public function save(ShopSetupService $setup): void
    {
        $this->authorizeOwner();

        $this->validate([
            'name' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:500'],
            'duration' => ['required', 'integer', 'min:5', 'max:480'],
            'price' => ['required', function (string $attribute, mixed $value, Closure $fail): void {
                if (parse_money((string) $value) === null) {
                    $fail('Vul een bedrag in, bijvoorbeeld 25,00.');
                }
            }],
            'bufferAfter' => ['required', 'integer', 'min:0', 'max:120'],
            'staffIds' => ['required', 'array', 'min:1'],
        ], messages: [
            'staffIds.required' => 'Kies minstens één kapper.',
        ], attributes: ['name' => 'naam', 'description' => 'omschrijving', 'duration' => 'duur', 'price' => 'prijs', 'bufferAfter' => 'uitloop']);

        // Alleen kappers van deze zaak: id's van een andere zaak vallen hier weg (§6.3).
        $allStaff = StaffMember::query()->pluck('id');
        $chosen = $allStaff->intersect(array_map(intval(...), $this->staffIds))->values();

        if ($chosen->isEmpty()) {
            $this->addError('staffIds', 'Kies minstens één kapper.');

            return;
        }

        $data = [
            'name' => trim($this->name),
            'description' => trim($this->description) ?: null,
            'duration' => (int) $this->duration,
            'price_cents' => (int) parse_money($this->price),
            'buffer_after' => (int) $this->bufferAfter,
            'is_active' => $this->isActive,
        ];

        DB::transaction(function () use ($setup, $data, $allStaff, $chosen): void {
            if ($this->serviceId === null) {
                $service = $setup->addService($this->shop(), $data);
            } else {
                $service = Service::query()->findOrFail($this->serviceId);
                $service->update($data);
            }

            $service->staffMembers()->sync($chosen->count() === $allStaff->count() ? [] : $chosen->all());
        });

        session()->flash('toast', 'Dienst opgeslagen');
        $this->redirectRoute('app.services');
    }

    public function delete(): void
    {
        $this->authorizeOwner();

        $service = Service::query()->findOrFail($this->serviceId);

        if ($service->appointments()->exists()) {
            $this->dispatch('toast', message: 'Er zijn afspraken met deze dienst. Zet de dienst op niet actief.', type: 'error');

            return;
        }

        $service->delete();

        session()->flash('toast', 'Dienst verwijderd');
        $this->redirectRoute('app.services');
    }

    public function render(): View
    {
        return view('livewire.app.services.form', [
            'staffMembers' => StaffMember::query()->orderBy('sort_order')->orderBy('id')->get(),
            'editing' => $this->serviceId !== null,
        ]);
    }
}
