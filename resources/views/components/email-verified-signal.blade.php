{{-- Seintje aan andere open schermen in deze browser dat het e-mailadres bevestigd is; die gaan dan meteen verder. --}}
<script>
    try { new BroadcastChannel('barbercuts').postMessage('email-verified'); } catch (error) {}
    try { localStorage.setItem('barbercuts:email-verified', String(Date.now())); } catch (error) {}
</script>
