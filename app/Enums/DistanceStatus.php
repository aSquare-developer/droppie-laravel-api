<?php

namespace App\Enums;

enum DistanceStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Queued',
            self::Processing => 'Calculating',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    /**
     * Whether the distance is still being calculated, which blocks editing and deleting the route.
     */
    public function isInProgress(): bool
    {
        return $this === self::Pending || $this === self::Processing;
    }

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        $labels = [];

        foreach (self::cases() as $status) {
            $labels[$status->value] = $status->label();
        }

        return $labels;
    }
}
