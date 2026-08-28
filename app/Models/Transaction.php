<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property Carbon $transaction_date
 * @property string $customer_name
 * @property int $pax
 * @property string|null $provider
 * @property string|null $service_details
 * @property string $amount
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable([
    'transaction_date',
    'customer_name',
    'pax',
    'provider',
    'service_details',
    'amount',
    'notes',
])]
class Transaction extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transaction_date' => 'date:Y-m-d',
            'amount' => 'decimal:2',
        ];
    }
}
