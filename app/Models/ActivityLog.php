<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Wie deed wat. Geen tenant-scope: de admin leest dit over alle zaken heen.
 *
 * @property array<string, mixed>|null $properties
 */
#[Table('activity_log')]
class ActivityLog extends Model
{
    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['properties' => 'array'];
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    public static function record(string $action, ?Barbershop $shop = null, ?Model $subject = null, array $properties = []): self
    {
        $log = new self;
        $log->action = $action;
        $log->barbershop_id = $shop?->id;
        $log->user_id = Auth::id();
        $log->subject_type = $subject?->getMorphClass();
        $log->subject_id = $subject?->getKey();
        $log->properties = $properties;
        $log->save();

        return $log;
    }
}
