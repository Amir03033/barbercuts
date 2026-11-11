<?php

namespace App\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * De zaak is alleen-lezen (abonnement verlopen of geblokkeerd): wijzigen in /app kan niet (§7.7).
 */
class ShopReadOnlyException extends HttpException
{
    public function __construct(string $message = 'Je abonnement is verlopen. Je kunt alles nog bekijken, maar niets meer wijzigen. Start je abonnement om verder te gaan.')
    {
        parent::__construct(403, $message, null, ['X-Shop-Read-Only' => '1']);
    }
}
