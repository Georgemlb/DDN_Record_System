<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

use Illuminate\Database\Eloquent\Attributes\Fillable;

#[Fillable([
    'airline',
    'flight_type',
    'markup_amount',
    'is_active',
])]
class AirlineMarkup extends Model
{
    protected function casts(): array
    {
        return [
            'markup_amount' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }
}
