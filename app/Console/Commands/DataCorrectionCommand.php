<?php

namespace App\Console\Commands;

use App\Models\DailySettlement;
use App\Models\FlightPortalDeposit;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DataCorrectionCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:correct {--dry-run : Run audit and preview corrections without modifying database} {--apply : Apply corrections to the database}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Audit and correct historical transactions, sync flight portal deductions, and recalculate settlements.';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $isApply = (bool) $this->option('apply');
        $isDryRun = ! $isApply;

        $this->info($isDryRun ? '=== RUNNING IN DRY-RUN MODE (NO CHANGES WILL BE SAVED) ===' : '=== APPLYING CHANGES TO DATABASE ===');

        $transactions = Transaction::orderBy('transaction_date', 'asc')->orderBy('id', 'asc')->get();
        $totalCount = $transactions->count();

        $stats = [
            'total' => $totalCount,
            'airline_reclassified' => 0,
            'ferry_converted' => 0,
            'airline_updated' => 0,
            'baggage_updated' => 0,
            'psa_updated' => 0,
            'oa_mapped' => 0,
            'split_payments' => 0,
            'gcash_whole_payments' => 0,
            'flagged' => 0,
        ];

        $flaggedItems = [];
        $updatesToApply = [];
        $affectedDates = [];

        foreach ($transactions as $tx) {
            $changes = $this->evaluateTransaction($tx, $stats, $flaggedItems);
            if (! empty($changes)) {
                $updatesToApply[$tx->id] = [
                    'transaction' => $tx,
                    'changes' => $changes,
                ];
                $date = Carbon::parse($tx->transaction_date)->toDateString();
                $affectedDates[$date] = true;
            }
        }

        $this->line('');
        $this->info("Audit Summary Across {$totalCount} Transactions:");
        $this->table(
            ['Category', 'Count'],
            [
                ['Airline Mistyped Reclassified', $stats['airline_reclassified']],
                ['Converted to Ferry (2GO)', $stats['ferry_converted']],
                ['Airline Markups & Portal Costs Calculated', $stats['airline_updated']],
                ['Standalone Baggage Pass-through', $stats['baggage_updated']],
                ['PSA Records Standardized', $stats['psa_updated']],
                ['Online Assistance Types Mapped', $stats['oa_mapped']],
                ['Split Payments Parsed from Notes', $stats['split_payments']],
                ['Whole GCash Payments Standardized', $stats['gcash_whole_payments']],
                ['Flagged for Manual Review', count($flaggedItems)],
            ]
        );

        if (! empty($flaggedItems)) {
            $this->line('');
            $this->warn(sprintf('Discovered %d items flagged for manual review:', count($flaggedItems)));
            $this->table(
                ['ID', 'Date', 'Customer', 'Amount', 'Reason', 'Details / Notes'],
                array_map(function ($item) {
                    return [
                        $item['id'],
                        $item['date'],
                        substr($item['customer'], 0, 20),
                        number_format($item['amount'], 2),
                        $item['reason'],
                        substr($item['details'].' | '.$item['notes'], 0, 35),
                    ];
                }, array_slice($flaggedItems, 0, 30))
            );

            if (count($flaggedItems) > 30) {
                $this->comment(sprintf('... and %d more flagged items.', count($flaggedItems) - 30));
            }
        }

        if ($isDryRun) {
            $this->line('');
            $this->info('Dry-run complete. Run with --apply to write all changes to database, sync flight portal, and update settlements.');

            return self::SUCCESS;
        }

        // Apply mode
        $this->info(sprintf('Writing updates to %d transactions...', count($updatesToApply)));

        DB::transaction(function () use ($updatesToApply) {
            foreach ($updatesToApply as $item) {
                /** @var Transaction $tx */
                $tx = $item['transaction'];
                $changes = $item['changes'];

                $tx->update($changes);

                // Sync Flight Portal Deduction if it is airline or ferry with portal third party payment
                if (in_array($tx->type, [Transaction::TYPE_AIRLINE, Transaction::TYPE_FERRY], true)
                    && $tx->disbursement_source === Transaction::DISBURSEMENT_SOURCE_FLIGHT_PORTAL
                    && (float) ($tx->third_party_payment ?? 0) > 0
                ) {
                    FlightPortalDeposit::syncTransactionTicketDeduction($tx);
                }
            }
        });

        // Recalculate Flight Portal Running Balances
        $this->info('Recalculating Flight Portal running balances...');
        FlightPortalDeposit::recalculateRunningBalances();

        // Recalculate Daily Settlements for all affected dates
        $datesCount = count($affectedDates);
        $this->info("Recalculating Daily Settlements across {$datesCount} affected dates...");
        $bar = $this->output->createProgressBar($datesCount);
        $bar->start();

        foreach (array_keys($affectedDates) as $dateStr) {
            DailySettlement::syncForDate($dateStr);
            $bar->advance();
        }
        $bar->finish();
        $this->line('');

        $this->info('All historical data successfully updated, flight portal ledger synced, and settlements refreshed!');

        return self::SUCCESS;
    }

    /**
     * Evaluate a transaction and return array of attributes to update.
     */
    protected function evaluateTransaction(Transaction $tx, array &$stats, array &$flaggedItems): array
    {
        $changes = [];
        $details = strtoupper(trim((string) $tx->service_details));
        $notes = strtoupper(trim((string) $tx->notes));
        $amount = (float) $tx->amount;
        $currentType = $tx->type;

        // --- 1. Check Payment Methods & Split Payments ---
        $parsedGcash = null;
        if (preg_match('/(\d+(?:\.\d+)?)\s*(?:K\b|k\b)?\s*GCASH/i', $notes, $m)) {
            $parsedGcash = (float) $m[1];
            if (stripos($notes, 'K') !== false && $parsedGcash < 100) {
                $parsedGcash *= 1000;
            }
        } elseif (preg_match('/GCASH\s*(\d+(?:\.\d+)?)/i', $notes, $m)) {
            $parsedGcash = (float) $m[1];
        }

        if ($parsedGcash !== null && $parsedGcash > 0 && $parsedGcash < $amount) {
            $cashPart = round($amount - $parsedGcash, 2);
            if ($tx->payment_method !== Transaction::PAYMENT_METHOD_SPLIT || (float) $tx->gcash_amount !== $parsedGcash) {
                $changes['payment_method'] = Transaction::PAYMENT_METHOD_SPLIT;
                $changes['gcash_amount'] = $parsedGcash;
                $changes['cash_amount'] = $cashPart;
                $stats['split_payments']++;
            }
        } elseif ($notes !== '' && (str_contains($notes, 'GCASH') || str_contains($notes, 'G-CASH')) && $parsedGcash === null) {
            if ($tx->payment_method !== Transaction::PAYMENT_METHOD_GCASH) {
                $changes['payment_method'] = Transaction::PAYMENT_METHOD_GCASH;
                $changes['gcash_amount'] = $amount;
                $changes['cash_amount'] = 0.00;
                $stats['gcash_whole_payments']++;
            }
        }

        // --- 2. Check for Non-Sales Drawer Float Rows ---
        if (preg_match('/\b(COINS|CASH IN THE BOX|FORWA?R?D|FPRWARD|FOWARD|COIN|MONEY|TIP)\b/i', $details.' '.$tx->customer_name)) {
            $flaggedItems[] = [
                'id' => $tx->id,
                'date' => $tx->transaction_date,
                'customer' => $tx->customer_name,
                'amount' => $amount,
                'reason' => 'Non-sales drawer float / adjustment row (keep in transactions, manual check)',
                'details' => $details,
                'notes' => $notes,
            ];
            $stats['flagged']++;

            return $changes;
        }

        // --- 3. Check for Ferry (2GO / Ferry) ---
        if (preg_match('/\b(2GO|FERRY)\b/i', $details) && ! str_contains($details, 'AF TICKET')) {
            $pax = $tx->pax ?: 1;
            if (preg_match('/(\d+)\s*PAX/i', $details, $pm)) {
                $pax = (int) $pm[1];
            }
            $ddn = round($pax * 200.00, 2);
            $thirdParty = round(max(0, $amount - $ddn), 2);

            $changes['type'] = Transaction::TYPE_FERRY;
            $changes['provider'] = '2GO';
            $changes['pax'] = $pax;
            $changes['ddn_amount'] = $ddn;
            $changes['third_party_payment'] = $thirdParty > 0 ? $thirdParty : null;
            $changes['disbursement_source'] = Transaction::DISBURSEMENT_SOURCE_FLIGHT_PORTAL;

            $stats['ferry_converted']++;

            return $changes;
        }

        // --- 4. Check for Document Translation typos ---
        if (str_contains($details, 'TRANSALTION') || str_contains($details, 'TRANSLATION')) {
            $changes['type'] = Transaction::TYPE_TRANSLATION;
            $changes['ddn_amount'] = $amount;
            $changes['third_party_payment'] = null;
            $changes['disbursement_source'] = Transaction::DISBURSEMENT_SOURCE_NONE;

            return $changes;
        }

        // --- 5. Check for Misclassified Airline Tickets sitting in Online Assistance ---
        $isAirlineTicket = false;
        if ($currentType === Transaction::TYPE_AIRLINE) {
            $isAirlineTicket = true;
        } elseif (preg_match('/\b(?:AF|VF)?\s*(?:TICKET|TCKET|TIICKET|TIXKET)\b/i', $details)
            || preg_match('/\b(?:5J|PR|DG|Z2)\s+(?:OW|RT)\b/i', $details)
            || preg_match('/\b(?:PR|5J)\s+(?:RT|OW)\b/i', $details)
            || str_contains($details, 'ADDITIONAL BAGGAGE')
        ) {
            $isAirlineTicket = true;
            $changes['type'] = Transaction::TYPE_AIRLINE;
            $stats['airline_reclassified']++;
        }

        // --- 6. Process Airline Transactions ---
        if ($isAirlineTicket) {
            $airlineChanges = $this->evaluateAirline($tx, $details, $amount, $stats, $flaggedItems);

            return array_merge($changes, $airlineChanges);
        }

        // --- 7. Process PSA Transactions (Check details AND customer name) ---
        $allText = $details.' '.$tx->customer_name;
        if ($currentType === Transaction::TYPE_PSA || preg_match('/\b(BC|MC|DC|CENOMAR|BIRTH|MARRIAGE|DEATH|PSA)\b/i', $allText)) {
            $psaChanges = $this->evaluatePsa($tx, $details, $amount, $stats);

            return array_merge($changes, $psaChanges);
        }

        // --- 8. Process Online Assistance ---
        if ($currentType === Transaction::TYPE_ONLINE_ASSISTANCE) {
            $oaChanges = $this->evaluateOnlineAssistance($tx, $details, $amount, $stats, $flaggedItems);

            return array_merge($changes, $oaChanges);
        }

        return $changes;
    }

    /**
     * Evaluate Airline transaction rules.
     */
    protected function evaluateAirline(Transaction $tx, string $details, float $amount, array &$stats, array &$flaggedItems): array
    {
        $changes = [];

        // Standalone Baggage check
        if (str_contains($details, 'BAGGAGE') && ! str_contains($details, 'TICKET')) {
            $changes['provider'] = $tx->provider ?: 'Cebu Pacific';
            $changes['pax'] = 1;
            $changes['trip_type'] = Transaction::TRIP_TYPE_ONE_WAY;
            $changes['flight_type'] = 'one_way';
            $changes['ddn_amount'] = 0.00;
            $changes['third_party_payment'] = $amount;
            $changes['disbursement_source'] = Transaction::DISBURSEMENT_SOURCE_FLIGHT_PORTAL;
            $stats['baggage_updated']++;

            return $changes;
        }

        // Determine PAX
        $pax = $tx->pax ?: 1;
        if (preg_match('/(\d+)\s*PAX/i', $details, $pm)) {
            $pax = (int) $pm[1];
        }
        $changes['pax'] = $pax;

        // Determine Trip Type
        $isRoundTrip = (bool) (preg_match('/\bRT\b/i', $details) || str_contains($details, 'ROUND'));
        $legs = $isRoundTrip ? 2 : 1;
        $tripType = $isRoundTrip ? Transaction::TRIP_TYPE_ROUND_TRIP : Transaction::TRIP_TYPE_ONE_WAY;
        $changes['trip_type'] = $tripType;
        $changes['flight_type'] = $tripType;

        // Determine International vs Local
        $isInternational = (bool) (str_contains($details, 'INT') || stripos($tx->provider ?? '', 'Turkish') !== false || $tx->flight_type === 'international');

        if ($isInternational) {
            $category = 'budget';
            $markupPerPaxLeg = 1250.00;
            $provider = 'Budget Carriers';

            if (preg_match('/\b(PR|PAL|TURKISH)\b/i', $details) || stripos($tx->provider ?? '', 'Turkish') !== false || stripos($tx->provider ?? '', 'Philippine') !== false) {
                $category = 'full_service';
                $markupPerPaxLeg = 1500.00;
                $provider = stripos($details, 'TURKISH') !== false ? 'Turkish Airlines' : 'Philippine Airlines';
            } elseif (preg_match('/\b(5J|CEB|Z2|AIRASIA)\b/i', $details)) {
                $category = 'budget';
                $markupPerPaxLeg = 1250.00;
                $provider = preg_match('/\b(Z2|AIRASIA)\b/i', $details) ? 'Philippines AirAsia' : 'Cebu Pacific';
            } else {
                // Unstated carrier: fare < 20000/pax -> budget, else full service
                if (($amount / max(1, $pax)) < 20000.00) {
                    $category = 'budget';
                    $markupPerPaxLeg = 1250.00;
                    $provider = 'Budget Carriers';
                } else {
                    $category = 'full_service';
                    $markupPerPaxLeg = 1500.00;
                    $provider = 'Full Service Carriers';
                }
            }

            $totalMarkup = round($markupPerPaxLeg * $pax * $legs, 2);
            $thirdParty = round(max(0, $amount - $totalMarkup), 2);

            $changes['provider'] = $provider;
            $changes['carrier_category'] = $category;
            $changes['ddn_amount'] = $totalMarkup;
            $changes['third_party_payment'] = $thirdParty > 0 ? $thirdParty : null;
            $changes['disbursement_source'] = Transaction::DISBURSEMENT_SOURCE_FLIGHT_PORTAL;
            $stats['airline_updated']++;

            return $changes;
        }

        // Local Flight: Check for Mixed Roundtrip
        if ($isRoundTrip && preg_match('/(5J|PR|DG|Z2|CEB|PAL|AIRASIA)\s*\/\s*(5J|PR|DG|Z2|CEB|PAL|AIRASIA)/i', $details, $mm)) {
            $outCode = strtoupper($mm[1]);
            $retCode = strtoupper($mm[2]);

            $outProvider = $this->mapAirlineCode($outCode);
            $retProvider = $this->mapAirlineCode($retCode);
            $outMarkup = $this->getAirlineMarkupAmount($outProvider);
            $retMarkup = $this->getAirlineMarkupAmount($retProvider);

            $totalMarkup = round(($outMarkup + $retMarkup) * $pax, 2);
            $thirdParty = round(max(0, $amount - $totalMarkup), 2);

            $changes['provider'] = $outProvider;
            $changes['return_provider'] = $retProvider;
            $changes['ddn_amount'] = $totalMarkup;
            $changes['third_party_payment'] = $thirdParty > 0 ? $thirdParty : null;
            $changes['disbursement_source'] = Transaction::DISBURSEMENT_SOURCE_FLIGHT_PORTAL;
            $stats['airline_updated']++;

            return $changes;
        }

        // Standard Single-Airline Local Flight
        $code = null;
        if (preg_match('/\b(5J|PR|DG|Z2|PAL|CEB|CEBGO|AIRASIA|AIR SWIFT)\b/i', $details, $cm)) {
            $code = strtoupper($cm[1]);
        } elseif ($tx->provider) {
            $code = $tx->provider;
        } else {
            // Default unspecified domestic flights to Cebu Pacific (standard ₱300/pax)
            $code = '5J';
        }

        $provider = $this->mapAirlineCode($code);
        $baseMarkup = $this->getAirlineMarkupAmount($provider);
        $totalMarkup = round($baseMarkup * $pax * $legs, 2);
        $thirdParty = round(max(0, $amount - $totalMarkup), 2);

        $changes['provider'] = $provider;
        $changes['return_provider'] = null;
        $changes['ddn_amount'] = $totalMarkup;
        $changes['third_party_payment'] = $thirdParty > 0 ? $thirdParty : null;
        $changes['disbursement_source'] = Transaction::DISBURSEMENT_SOURCE_FLIGHT_PORTAL;
        $stats['airline_updated']++;

        return $changes;
    }

    /**
     * Evaluate PSA transaction rules.
     */
    protected function evaluatePsa(Transaction $tx, string $details, float $amount, array &$stats): array
    {
        $changes = [];
        $docType = Transaction::PSA_DOC_BC;
        $officerFee = 260.00;

        if (str_contains($details, 'CENOMAR')) {
            $docType = Transaction::PSA_DOC_CENOMAR;
            $officerFee = 310.00;
        } elseif (str_contains($details, 'MC') || str_contains($details, 'MARRIAGE')) {
            $docType = Transaction::PSA_DOC_MC;
            $officerFee = 260.00;
        } elseif (str_contains($details, 'DC') || str_contains($details, 'DEATH')) {
            $docType = Transaction::PSA_DOC_DC;
            $officerFee = 260.00;
        }

        $ddn = round(max(0, $amount - $officerFee), 2);

        $changes['type'] = Transaction::TYPE_PSA;
        $changes['psa_document_type'] = $docType;
        $changes['third_party_payment'] = $officerFee;
        $changes['ddn_amount'] = $ddn;
        $changes['disbursement_source'] = Transaction::DISBURSEMENT_SOURCE_CASH;

        $stats['psa_updated']++;

        return $changes;
    }

    /**
     * Evaluate Online Assistance rules.
     */
    protected function evaluateOnlineAssistance(Transaction $tx, string $details, float $amount, array &$stats, array &$flaggedItems): array
    {
        $changes = [];
        $notes = strtoupper(trim((string) $tx->notes));
        $allDetails = $details.' '.$tx->customer_name.' '.$notes;

        // 0. Apostille (Document authentication service)
        if (preg_match('/APPOSTILLE|APOSTILLE/i', $allDetails)) {
            $changes['ddn_amount'] = $amount;
            $changes['third_party_payment'] = null;
            $changes['disbursement_source'] = Transaction::DISBURSEMENT_SOURCE_NONE;
            $stats['oa_mapped']++;

            return $changes;
        }

        // 1. DFA Passport Appointment
        if (str_contains($details, 'DFA') || str_contains($details, 'PASSPORT APPOINTMENT')) {
            if ($amount == 1200.00) {
                $changes['online_assistance_type_id'] = 10; // Regular DFA Passport
                $changes['third_party_payment'] = 950.00;
                $changes['ddn_amount'] = 250.00;
                $changes['disbursement_source'] = Transaction::DISBURSEMENT_SOURCE_GCASH;
                $stats['oa_mapped']++;

                return $changes;
            }

            // Flag varied amounts 1300, 1450, 1550, 1650 for manual review
            $flaggedItems[] = [
                'id' => $tx->id,
                'date' => $tx->transaction_date,
                'customer' => $tx->customer_name,
                'amount' => $amount,
                'reason' => 'DFA appointment with non-standard amount (₱'.number_format($amount, 2).')',
                'details' => $details,
                'notes' => $tx->notes,
            ];
            $stats['flagged']++;

            return $changes;
        }

        // 2. SSS
        if (str_contains($details, 'SSS') && ! str_contains($details, 'CARD')) {
            $changes['online_assistance_type_id'] = 1; // SSS registration
            $changes['ddn_amount'] = $amount;
            $changes['third_party_payment'] = null;
            $changes['disbursement_source'] = Transaction::DISBURSEMENT_SOURCE_NONE;
            $stats['oa_mapped']++;

            return $changes;
        }

        // 3. Pag-IBIG
        if (str_contains($details, 'PAG') || str_contains($details, 'IBIG')) {
            $typeId = ($amount == 100.00) ? 4 : 2;
            $changes['online_assistance_type_id'] = $typeId;
            $changes['ddn_amount'] = $amount;
            $changes['third_party_payment'] = null;
            $changes['disbursement_source'] = Transaction::DISBURSEMENT_SOURCE_NONE;
            $stats['oa_mapped']++;

            return $changes;
        }

        // 4. TIN
        if (str_contains($details, 'TIN')) {
            $changes['online_assistance_type_id'] = 5;
            $changes['ddn_amount'] = $amount;
            $changes['third_party_payment'] = null;
            $changes['disbursement_source'] = Transaction::DISBURSEMENT_SOURCE_NONE;
            $stats['oa_mapped']++;

            return $changes;
        }

        // 5. NBI
        if (str_contains($details, 'NBI')) {
            $changes['online_assistance_type_id'] = 8;
            $fee = 160.00;
            $changes['third_party_payment'] = $fee;
            $changes['ddn_amount'] = round(max(0, $amount - $fee), 2);
            $changes['disbursement_source'] = Transaction::DISBURSEMENT_SOURCE_GCASH;
            $stats['oa_mapped']++;

            return $changes;
        }

        // 6. Police Clearance
        if (str_contains($details, 'POLICE')) {
            $changes['online_assistance_type_id'] = 9;
            $fee = 180.00;
            $changes['third_party_payment'] = $fee;
            $changes['ddn_amount'] = round(max(0, $amount - $fee), 2);
            $changes['disbursement_source'] = Transaction::DISBURSEMENT_SOURCE_GCASH;
            $stats['oa_mapped']++;

            return $changes;
        }

        // 7. OEC
        if (str_contains($details, 'OEC')) {
            $changes['online_assistance_type_id'] = 12;
            $changes['ddn_amount'] = $amount;
            $changes['third_party_payment'] = null;
            $changes['disbursement_source'] = Transaction::DISBURSEMENT_SOURCE_NONE;
            $stats['oa_mapped']++;

            return $changes;
        }

        // 8. Other standard DDN pure service fee assistance
        if (preg_match('/\b(PCSO|TYPING|RESIGNATION|INVITATION|E TRAVEL|E-TRAVEL|NATIONAL ID|SSS CARD|DOB CORRECTION|CORRECTION|CONSULTATION|CONSULATION|VISA|NIHONGGO|CHANGE ADDRESS|PENSION)\b/i', $allDetails)) {
            $changes['ddn_amount'] = $amount;
            $changes['third_party_payment'] = null;
            $changes['disbursement_source'] = Transaction::DISBURSEMENT_SOURCE_NONE;
            $stats['oa_mapped']++;

            return $changes;
        }

        // If not mapped and type_id is null, flag it
        if (! $tx->online_assistance_type_id) {
            $flaggedItems[] = [
                'id' => $tx->id,
                'date' => $tx->transaction_date,
                'customer' => $tx->customer_name,
                'amount' => $amount,
                'reason' => 'Unmapped online assistance type',
                'details' => $details,
                'notes' => $tx->notes,
            ];
            $stats['flagged']++;
        }

        return $changes;
    }

    /**
     * Map airline code or name to standard provider name.
     */
    protected function mapAirlineCode(string $code): string
    {
        $code = strtoupper(trim($code));

        return match ($code) {
            '5J', 'CEB', 'CEBU', 'CEBU PACIFIC' => 'Cebu Pacific',
            'PR', 'PAL', 'PHILIPPINE AIRLINES' => 'Philippine Airlines',
            'Z2', 'AIRASIA', 'PHILIPPINES AIRASIA' => 'Philippines AirAsia',
            'DG', 'CEBGO' => 'Cebgo',
            'T6', 'AIR SWIFT' => 'Air Swift',
            default => 'Cebu Pacific',
        };
    }

    /**
     * Get markup amount for a domestic airline provider.
     */
    protected function getAirlineMarkupAmount(string $provider): float
    {
        return match ($provider) {
            'Cebu Pacific' => 300.00,
            'Philippine Airlines' => 350.00,
            'Philippines AirAsia' => 300.00,
            'Cebgo' => 250.00,
            'Air Swift' => 175.00,
            default => 300.00,
        };
    }
}
