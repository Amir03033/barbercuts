<?php

namespace App\Services\Notifications;

use App\Enums\MessageChannel;

final readonly class ChannelChoice
{
    public function __construct(public ?MessageChannel $channel, public ?string $skipReason = null) {}
}
