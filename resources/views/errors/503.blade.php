@include('errors.layout', [
    'code' => '503',
    'heading' => 'We zijn zo terug',
    'message' => 'BarberCuts is even niet beschikbaar, bijvoorbeeld door onderhoud. Je afspraken staan veilig. Probeer het zo opnieuw.',
    'retry' => true,
])
