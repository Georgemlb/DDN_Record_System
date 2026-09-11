<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
 * @property int|null $customer_id
 * @property string $amount_paid
 * @property string $balance
 * @property string $payment_status
 * @property BaggageOption|null $baggageOption
 * @property OnlineAssistanceType|null $onlineAssistanceType
 * @property User|null $user
 * @property Customer|null $customer
 * @property Collection<int, TransactionPayment> $payments
 */
#[Fillable([
    'user_id',
    'customer_id',
    'reference_number',
    'type',
    'payment_method',
    'transaction_date',
    'customer_name',
    'pax',
    'provider',
    'return_provider',
    'service_details',
    'baggage_option_id',
    'psa_document_type',
    'online_assistance_type_id',
    'amount',
    'amount_paid',
    'balance',
    'payment_status',
    'cash_amount',
    'gcash_amount',
    'third_party_payment',
    'disbursement_source',
    'ddn_amount',
    'employee_share',
    'notes',
    'flight_type',
    'trip_type',
    'carrier_category',
])]
class Transaction extends Model
{
    use SoftDeletes;

    public const TYPE_AIRLINE = 'airline';

    public const TYPE_FERRY = 'ferry';

    public const TYPE_TRANSLATION = 'translation';

    public const TYPE_PSA = 'psa';

    public const TYPE_ONLINE_ASSISTANCE = 'online_assistance';

    public const TYPE_PRINTING = 'printing';

    public const TYPE_REMITTANCE = 'remittance';

    public const TYPES = [
        self::TYPE_AIRLINE,
        self::TYPE_FERRY,
        self::TYPE_TRANSLATION,
        self::TYPE_PSA,
        self::TYPE_ONLINE_ASSISTANCE,
        self::TYPE_PRINTING,
        self::TYPE_REMITTANCE,
    ];

    public const DISBURSEMENT_SOURCE_CASH = 'cash';

    public const DISBURSEMENT_SOURCE_GCASH = 'gcash';

    public const DISBURSEMENT_SOURCE_FLIGHT_PORTAL = 'flight_portal';

    public const DISBURSEMENT_SOURCE_NONE = 'none';

    public const DISBURSEMENT_SOURCES = [
        self::DISBURSEMENT_SOURCE_CASH,
        self::DISBURSEMENT_SOURCE_GCASH,
        self::DISBURSEMENT_SOURCE_FLIGHT_PORTAL,
        self::DISBURSEMENT_SOURCE_NONE,
    ];

    public const TRIP_TYPE_ONE_WAY = 'one_way';

    public const TRIP_TYPE_ROUND_TRIP = 'round_trip';

    public const TRIP_TYPES = [
        self::TRIP_TYPE_ONE_WAY,
        self::TRIP_TYPE_ROUND_TRIP,
    ];

    public const CARRIER_CATEGORY_BUDGET = 'budget';

    public const CARRIER_CATEGORY_FULL_SERVICE = 'full_service';

    public const CARRIER_CATEGORIES = [
        self::CARRIER_CATEGORY_BUDGET,
        self::CARRIER_CATEGORY_FULL_SERVICE,
    ];

    public const PAYMENT_METHOD_CASH = 'cash';

    public const PAYMENT_METHOD_GCASH = 'gcash';

    public const PAYMENT_METHOD_SPLIT = 'split';

    public const PAYMENT_METHODS = [
        self::PAYMENT_METHOD_CASH,
        self::PAYMENT_METHOD_GCASH,
        self::PAYMENT_METHOD_SPLIT,
    ];

    public const PAYMENT_STATUS_PAID = 'paid';

    public const PAYMENT_STATUS_PARTIAL = 'partial';

    public const PAYMENT_STATUS_UNPAID = 'unpaid';

    public const PAYMENT_STATUSES = [
        self::PAYMENT_STATUS_PAID,
        self::PAYMENT_STATUS_PARTIAL,
        self::PAYMENT_STATUS_UNPAID,
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
     * Boot model events for reference number, user tracking, and initial payments.
     */
    protected static function booted(): void
    {
        static::creating(function (Transaction $transaction) {
            if (empty($transaction->reference_number)) {
                $transaction->reference_number = static::generateReferenceNumber($transaction->transaction_date ? $transaction->transaction_date->format('Y-m-d') : null);
            }

            if (empty($transaction->user_id) && auth()->check()) {
                $transaction->user_id = auth()->id();
            }

            if (empty($transaction->payment_status)) {
                $transaction->payment_status = static::PAYMENT_STATUS_PAID;
            }

            if ($transaction->amount_paid === null) {
                if ($transaction->payment_status === static::PAYMENT_STATUS_PAID) {
                    $transaction->amount_paid = $transaction->amount;
                    $transaction->balance = 0.00;
                } else {
                    $transaction->amount_paid = 0.00;
                    $transaction->balance = $transaction->amount;
                }
            } elseif ($transaction->balance === null) {
                $transaction->balance = max(0, round((float) $transaction->amount - (float) $transaction->amount_paid, 2));
            }
        });

        static::created(function (Transaction $transaction) {
            if ($transaction->payments()->doesntExist() && (float) $transaction->amount_paid > 0) {
                $transaction->payments()->create([
                    'user_id' => $transaction->user_id ?? auth()->id(),
                    'amount' => $transaction->amount_paid,
                    'payment_method' => $transaction->payment_method,
                    'cash_amount' => $transaction->payment_method === static::PAYMENT_METHOD_SPLIT ? $transaction->cash_amount : null,
                    'gcash_amount' => $transaction->payment_method === static::PAYMENT_METHOD_SPLIT ? $transaction->gcash_amount : null,
                    'payment_date' => $transaction->transaction_date ? $transaction->transaction_date->format('Y-m-d') : now()->toDateString(),
                    'notes' => $transaction->payment_status === static::PAYMENT_STATUS_PARTIAL ? 'Initial downpayment' : 'Full payment',
                ]);
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
     * Recalculate transaction balance and payment status from payments.
     */
    public function recalculateBalance(): void
    {
        $totalPaid = (float) $this->payments()->sum('amount');
        $totalAmount = (float) $this->amount;
        $balance = max(0, round($totalAmount - $totalPaid, 2));

        $status = self::PAYMENT_STATUS_PARTIAL;
        if ($totalPaid <= 0) {
            $status = self::PAYMENT_STATUS_UNPAID;
        } elseif ($balance <= 0) {
            $status = self::PAYMENT_STATUS_PAID;
        }

        $this->update([
            'amount_paid' => round($totalPaid, 2),
            'balance' => $balance,
            'payment_status' => $status,
        ]);
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
            'amount_paid' => 'decimal:2',
            'balance' => 'decimal:2',
            'cash_amount' => 'decimal:2',
            'gcash_amount' => 'decimal:2',
            'third_party_payment' => 'decimal:2',
            'ddn_amount' => 'decimal:2',
            'employee_share' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return HasMany<TransactionPayment, $this>
     */
    public function payments(): HasMany
    {
        return $this->hasMany(TransactionPayment::class);
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
