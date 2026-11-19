@include('errors.layout', [
    'code' => '500',
    'heading' => 'Er ging iets mis',
    'message' => 'Het ligt aan ons, niet aan jou. Probeer het over een paar minuten opnieuw.',
    'retry' => true,
])
