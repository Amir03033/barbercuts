{{-- Populairste diensten en resultaten per pitcher. Inline-stijlen: Filament laadt de Tailwind-klassen van de site niet. --}}
<x-filament-widgets::widget>
    <div style="display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(18rem, 1fr))">
        <x-filament::section heading="Populairste diensten" description="Op naam, over alle zaken">
            @if ($services === [])
                <p style="font-size: 0.875rem; color: #6e6e73">Nog geen afspraken in deze periode.</p>
            @else
                @php($max = max(array_column($services, 'appointments')))
                <ol style="display: grid; gap: 0.75rem">
                    @foreach ($services as $service)
                        <li>
                            <div style="display: flex; justify-content: space-between; gap: 0.75rem; font-size: 0.875rem">
                                <span style="font-weight: 600; overflow: hidden; text-overflow: ellipsis; white-space: nowrap">{{ $service['name'] }}</span>
                                <span style="flex-shrink: 0; color: #6e6e73; font-variant-numeric: tabular-nums">{{ number_format($service['appointments'], 0, ',', '.') }} · gem. {{ money($service['avg_price_cents']) }}</span>
                            </div>
                            <div style="margin-top: 0.375rem; height: 0.375rem; border-radius: 999px; background: #f2f2f5; overflow: hidden">
                                <div style="height: 100%; width: {{ max(2, round($service['appointments'] / max(1, $max) * 100)) }}%; border-radius: 999px; background: #0071e3"></div>
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </x-filament::section>

        <x-filament::section heading="Pitchers" description="Demo's en aanmeldingen in deze periode">
            @if ($pitchers === [])
                <p style="font-size: 0.875rem; color: #6e6e73">Nog geen pitchers.</p>
            @else
                <table style="width: 100%; font-size: 0.875rem; border-collapse: collapse">
                    <thead>
                        <tr style="color: #6e6e73; text-align: left">
                            <th style="padding: 0 0 0.5rem; font-weight: 500">Pitcher</th>
                            <th style="padding: 0 0 0.5rem; font-weight: 500; text-align: right">Demo's</th>
                            <th style="padding: 0 0 0.5rem; font-weight: 500; text-align: right">Aanmeldingen</th>
                            <th style="padding: 0 0 0.5rem; font-weight: 500; text-align: right">Uit demo</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pitchers as $pitcher)
                            <tr style="border-top: 1px solid #ececf0">
                                <td style="padding: 0.5rem 0; font-weight: 600">{{ $pitcher['name'] }}</td>
                                <td style="padding: 0.5rem 0; text-align: right; font-variant-numeric: tabular-nums">{{ $pitcher['demos'] }}</td>
                                <td style="padding: 0.5rem 0; text-align: right; font-variant-numeric: tabular-nums; font-weight: 600">{{ $pitcher['signups'] }}</td>
                                <td style="padding: 0.5rem 0; text-align: right; font-variant-numeric: tabular-nums">{{ $pitcher['demo_signups'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
