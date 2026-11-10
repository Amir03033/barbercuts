<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Een fout die de gebruiker moet zien, met een Nederlandse, menselijke tekst (§7.3).
 */
class BookingException extends RuntimeException {}
