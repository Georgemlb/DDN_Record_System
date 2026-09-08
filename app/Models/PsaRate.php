<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $document_type
 * @property string $name
 * @property string $price
 * @property string $officer_fee
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read float $net_income
 */
#[Fillable([
    'document_type',
    'name',
    'price',
    'officer_fee',
    'is_active',
    'sort_order',
])]
class PsaRate extends Model
{
    /**
     * The accessors to append to the model's array form.
     *
     * @var array<int, string>
     */
    protected $appends = ['net_income'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'officer_fee' => 'decimal:2',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /**
     * Get the calculated net DDN income (price - officer fee).
     */
    public function getNetIncomeAttribute(): float
    {
        return round(max(0, (float) $this->price - (float) $this->officer_fee), 2);
    }

    /**
     * Scope a query to only include active PSA rates.
     *
     * @param  Builder<PsaRate>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }
}
