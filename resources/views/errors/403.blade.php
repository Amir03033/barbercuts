@include('errors.layout', [
    'code' => '403',
    'heading' => 'Geen toegang',
    'message' => 'Je hebt geen toegang tot deze pagina. Log in met het juiste account en probeer het opnieuw.',
    'retry' => false,
])
