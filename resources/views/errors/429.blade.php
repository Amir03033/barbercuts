@include('errors.layout', [
    'code' => '429',
    'heading' => 'Even rustig aan',
    'message' => 'Er zijn te veel verzoeken achter elkaar gedaan. Wacht een minuutje en probeer het opnieuw.',
    'retry' => true,
])
