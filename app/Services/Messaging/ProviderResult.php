<?php

namespace App\Services\Messaging;

/**
 * Uitkomst van een verzending via een SMS- of WhatsApp-provider.
 */
final readonly class ProviderResult
{
    public function __construct(
        public bool $successful,
        public ?string $providerId = null,
        public ?int $costCents = null,
        public ?string $error = null,
    ) {}

    public static function ok(?string $providerId = null, ?int $costCents = null): self
    {
        return new self(true, $providerId, $costCents);
    }

    public static function failed(string $error): self
    {
        return new self(false, error: $error);
    }
}
