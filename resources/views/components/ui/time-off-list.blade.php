{{-- Lijst met vrije dagen / sluitingen, met verwijderknop (wire:click="{action}(id)"). --}}
@props(['items', 'timezone', 'action' => 'deleteTimeOff', 'empty' => 'Nog niets gepland.'])

@if ($items->isEmpty())
    <p class="text-sm text-muted">{{ $empty }}</p>
@else
    <x-ui.card :padding="false" flat class="divide-y divide-border overflow-hidden">
        @foreach ($items as $item)
            @php
                $from = $item->starts_at->setTimezone($timezone)->locale('nl');
                $to = $item->ends_at->setTimezone($timezone)->locale('nl');
                $custom = $item->type === \App\Enums\TimeOffType::CustomHours;
                $lastDay = $to->copy()->subSecond();
                $period = $custom
                    ? nl_date($from).' · alleen '.$from->format('H:i').' – '.$to->format('H:i')
                    : ($from->isSameDay($lastDay) ? nl_date($from, 'dd D MMM YYYY') : nl_date($from, 'D MMM').' t/m '.nl_date($lastDay, 'D MMM YYYY'));
            @endphp
            <x-ui.list-item :title="$period" :subtitle="$item->reason ?: ($custom ? 'Afwijkende uren' : 'Vrij')" :chevron="false" wire:key="off-{{ $item->id }}">
                <x-slot:trailing>
                    <x-ui.icon-button icon="trash" label="Verwijderen" wire:click="{{ $action }}({{ $item->id }})" wire:confirm="Weet je zeker dat je dit wilt verwijderen?" />
                </x-slot:trailing>
            </x-ui.list-item>
        @endforeach
    </x-ui.card>
@endif
