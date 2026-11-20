{{-- Bij aanmelden vanuit de demo: wat er meegaat (de verzonnen klanten en afspraken niet). --}}
@props(['name' => ''])

<div class="flex items-start gap-3 rounded-card bg-completed/10 p-4 text-sm text-text" role="note">
    <x-ui.icon name="circle-check" size="20" class="mt-0.5 shrink-0 text-completed" />
    <p>
        <strong class="font-semibold">Je instellingen uit de demo gaan mee</strong>{{ $name !== '' ? ': '.$name.',' : ':' }}
        je diensten met prijzen, je openingstijden en je kleur. Alles kun je hierna nog aanpassen. De voorbeeldklanten en -afspraken gaan niet mee.
    </p>
</div>
