// Is de zaak alleen-lezen (abonnement verlopen), dan weigert de server wijzigingen met een 403.
// Toon dan een toast in plaats van de technische foutmelding van Livewire (docs/BOUWOPDRACHT.md §7.7).
document.addEventListener('livewire:init', () => {
    window.Livewire.interceptRequest(({ onError }) => {
        onError(({ response, preventDefault }) => {
            if (response?.status === 403 && response.headers.get('X-Shop-Read-Only')) {
                preventDefault();
                window.dispatchEvent(new CustomEvent('toast', {
                    detail: { message: 'Je abonnement is verlopen. Je kunt alles bekijken, maar niets wijzigen.', type: 'error' },
                }));
            }
        });
    });
});
