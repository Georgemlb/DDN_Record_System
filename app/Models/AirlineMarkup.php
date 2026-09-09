<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'service_type',
    'airline',
    'flight_type',
    'markup_type',
    'markup_amount',
    'is_active',
])]
class AirlineMarkup extends Model
{
    public const SERVICE_TYPE_FLIGHT = 'flight';

    public const SERVICE_TYPE_FERRY = 'ferry';

    public const SERVICE_TYPES = [
        self::SERVICE_TYPE_FLIGHT,
        self::SERVICE_TYPE_FERRY,
    ];

    public const MARKUP_TYPE_FIXED = 'fixed';

    public const MARKUP_TYPE_PERCENT = 'percent';

    public const MARKUP_TYPES = [
        self::MARKUP_TYPE_FIXED,
        self::MARKUP_TYPE_PERCENT,
    ];

    protected function casts(): array
    {
        return [
            'markup_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
