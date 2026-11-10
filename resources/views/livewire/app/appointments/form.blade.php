<div>
    <x-ui.page-header :title="$appointment ? 'Afspraak verplaatsen' : 'Nieuwe afspraak'" :back="route('app.calendar')" />

    <form wire:submit="save" class="space-y-6 px-4 py-4" novalidate>
        {{-- Klant --}}
        <x-ui.section title="Klant">
            {{-- Elke toestand heeft een eigen wire:key: anders hergebruikt Livewire het zoekveld als naamveld. --}}
            @if ($customer)
                <div wire:key="customer-chosen">
                <x-ui.card :padding="false" flat>
                    <x-ui.list-item :title="$customer->name" :subtitle="$customer->phone" :chevron="false">
                        <x-slot:leading><x-ui.avatar :name="$customer->name" /></x-slot:leading>
                        @unless ($appointment)
                            <x-slot:trailing><x-ui.button variant="ghost" size="sm" wire:click="clearCustomer">Wijzig</x-ui.button></x-slot:trailing>
                        @endunless
                    </x-ui.list-item>
                </x-ui.card>
                </div>
            @elseif ($newCustomer)
                <div wire:key="customer-new" class="space-y-3">
                <x-ui.input label="Naam" wire:model="name" autocomplete="off" autofocus />
                <x-ui.phone-input wire:model="phone" autocomplete="off" />
                <x-ui.button variant="ghost" size="sm" wire:click="$set('newCustomer', false)" class="-ml-3">Toch een bestaande klant zoeken</x-ui.button>
                </div>
            @else
                <div wire:key="customer-search" class="space-y-3">
                <x-ui.input label="Zoek op naam of telefoonnummer" type="search" wire:model.live.debounce.250ms="search" autocomplete="off" autofocus />
                @if ($results->isNotEmpty())
                    <x-ui.card :padding="false" flat class="divide-y divide-border overflow-hidden">
                        @foreach ($results as $result)
                            <x-ui.list-item wire:click="chooseCustomer({{ $result->id }})" wire:key="result-{{ $result->id }}" :title="$result->name" :subtitle="$result->phone" :chevron="false" />
                        @endforeach
                    </x-ui.card>
                @endif
                <x-ui.button variant="secondary" icon="plus" full wire:click="startNewCustomer">Nieuwe klant</x-ui.button>
                </div>
            @endif
            @error('customer')
                <p class="text-xs text-danger" role="alert">{{ $message }}</p>
            @enderror
        </x-ui.section>

        {{-- Dienst --}}
        @unless ($appointment)
            <x-ui.section title="Dienst">
                <div class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4">
                    @foreach ($services as $service)
                        <x-ui.chip wire:model.live="serviceId" :value="$service->id" :checked="$serviceId === $service->id" wire:key="service-{{ $service->id }}">
                            {{ $service->name }} · {{ $service->duration }} min
                        </x-ui.chip>
                    @endforeach
                </div>
                @error('serviceId')
                    <p class="text-xs text-danger" role="alert">{{ $message }}</p>
                @enderror
            </x-ui.section>
        @else
            <x-ui.card flat class="!bg-surface text-sm">
                <p class="font-semibold">{{ $appointment->service_name }} · {{ duration_label($appointment->duration) }}</p>
                <p class="text-muted">Nu: {{ $appointment->whenLabel(long: true) }}</p>
            </x-ui.card>
        @endunless

        {{-- Kapper --}}
        @if ($staffOptions->count() > 1)
            <x-ui.section title="Kapper">
                <div class="no-scrollbar -mx-4 flex gap-2 overflow-x-auto px-4">
                    @foreach ($staffOptions as $staff)
                        <x-ui.chip wire:model.live="staffId" :value="$staff->id" :checked="$staffId === $staff->id" wire:key="staff-{{ $staff->id }}">
                            <span class="size-2.5 rounded-full" style="background-color: {{ $staff->color }}" aria-hidden="true"></span>
                            {{ $staff->name }}
                        </x-ui.chip>
                    @endforeach
                </div>
            </x-ui.section>
        @endif
        @error('staffId')
            <p class="text-xs text-danger" role="alert">{{ $message }}</p>
        @enderror

        {{-- Dag en tijd: de eerstvolgende vrije tijden bovenaan --}}
        <x-ui.section title="Wanneer">
            <x-ui.date-strip :days="$days" wire:model.live="date" :selected="$date" />
            @if ($canLoadMore)
                <div class="flex justify-end">
                    <x-ui.button variant="ghost" size="sm" wire:click="showMoreDays">Meer dagen</x-ui.button>
                </div>
            @endif

            <div wire:loading.class="opacity-50" wire:target="date, serviceId, staffId">
                @if ($times === [])
                    <p class="text-sm text-muted">Geen vrije tijden op deze dag.</p>
                @else
                    <x-ui.time-grid :times="$times" wire:model.live="time" :selected="$time" wire:key="times-{{ $date }}-{{ $staffId }}-{{ $serviceId }}" />
                @endif
            </div>
            @error('time')
                <x-ui.alert type="danger">{{ $message }}</x-ui.alert>
            @enderror
        </x-ui.section>

        @unless ($appointment)
            <x-ui.textarea label="Opmerking" wire:model="notes" optional rows="2" />

            <x-ui.button variant="secondary" icon="clock" full wire:click="walkIn" loading="walkIn">Inloop: begint nu</x-ui.button>
        @endunless

        <x-ui.sticky-footer nav>
            <x-ui.button type="submit" loading="save" full :disabled="$time === ''">
                {{ $time === '' ? 'Kies een tijd' : ($appointment ? 'Verplaats naar '.$time : 'Opslaan · '.$time) }}
            </x-ui.button>
        </x-ui.sticky-footer>
    </form>
</div>
