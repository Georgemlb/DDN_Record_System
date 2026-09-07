<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string|null $reference_number
 * @property string $type
 * @property string $payment_method
 * @property Carbon $transaction_date
 * @property string $customer_name
 * @property int|null $pax
 * @property string|null $provider
 * @property string|null $service_details
 * @property int|null $baggage_option_id
 * @property string|null $psa_document_type
 * @property int|null $online_assistance_type_id
 * @property string $amount
 * @property string|null $third_party_payment
 * @property string|null $ddn_amount
 * @property string|null $employee_share
 * @property string|null $notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 * @property BaggageOption|null $baggageOption
 * @property OnlineAssistanceType|null $onlineAssistanceType
 * @property User|null $user
 */
#[Fillable([
    'user_id',
    'reference_number',
    'type',
    'payment_method',
    'transaction_date',
    'customer_name',
    'pax',
    'provider',
    'service_details',
    'baggage_option_id',
    'psa_document_type',
    'online_assistance_type_id',
    'amount',
    'third_party_payment',
    'ddn_amount',
    'employee_share',
    'notes',
])]
class Transaction extends Model
{
    use SoftDeletes;

    public const TYPE_AIRLINE = 'airline';

    public const TYPE_TRANSLATION = 'translation';

    public const TYPE_PSA = 'psa';

    public const TYPE_ONLINE_ASSISTANCE = 'online_assistance';

    public const TYPE_PRINTING = 'printing';

    public const TYPES = [
        self::TYPE_AIRLINE,
        self::TYPE_TRANSLATION,
        self::TYPE_PSA,
        self::TYPE_ONLINE_ASSISTANCE,
        self::TYPE_PRINTING,
    ];

    public const PAYMENT_METHOD_CASH = 'cash';

    public const PAYMENT_METHOD_GCASH = 'gcash';

    public const PAYMENT_METHODS = [
        self::PAYMENT_METHOD_CASH,
        self::PAYMENT_METHOD_GCASH,
    ];

    public const PSA_DOC_BC = 'BC';

    public const PSA_DOC_MC = 'MC';

    public const PSA_DOC_DC = 'DC';

    public const PSA_DOC_CENOMAR = 'Cenomar';

    public const PSA_DOCUMENT_TYPES = [
        self::PSA_DOC_BC,
        self::PSA_DOC_MC,
        self::PSA_DOC_DC,
        self::PSA_DOC_CENOMAR,
    ];

    /**
     * Boot model events to assign default reference numbers and user tracking.
     */
    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction) {
            if (empty($transaction->reference_number)) {
                $transaction->reference_number = static::generateReferenceNumber(
                    $transaction->transaction_date ? (string) $transaction->transaction_date : null
                );
            }

            if (empty($transaction->user_id) && auth()->check()) {
                $transaction->user_id = auth()->id();
            }
        });
    }

    /**
     * Generate a unique sequential reference number for a date: TRX-YYYYMMDD-0001
     */
    public static function generateReferenceNumber(?string $date = null): string
    {
        $carbonDate = $date ? Carbon::parse($date) : now();
        $datePart = $carbonDate->format('Ymd');
        $prefix = "TRX-{$datePart}-";

        $latest = static::withTrashed()
            ->where('reference_number', 'like', "{$prefix}%")
            ->orderByDesc('reference_number')
            ->value('reference_number');

        $sequence = 1;
        if ($latest && preg_match('/-(\d{4})$/', $latest, $matches)) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return sprintf('%s%04d', $prefix, $sequence);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transaction_date' => 'date:Y-m-d',
            'pax' => 'integer',
            'amount' => 'decimal:2',
            'third_party_payment' => 'decimal:2',
            'ddn_amount' => 'decimal:2',
            'employee_share' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<BaggageOption, $this>
     */
    public function baggageOption(): BelongsTo
    {
        return $this->belongsTo(BaggageOption::class);
    }

    public function onlineAssistanceType(): BelongsTo
    {
        return $this->belongsTo(OnlineAssistanceType::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
