<?php

namespace App\Services;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class LedgerService
{
    /**
     * Apply shared search, type, date range, and sorting filters to a ledger query
     * and attach `can_move_up` / `can_move_down` intra-day ordering flags.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @param  class-string<TModel>  $modelClass
     * @param  array<int, string>  $validTypes
     * @return LengthAwarePaginator<int, TModel>
     */
    public function paginateWithOrdering(
        Request $request,
        Builder $query,
        string $modelClass,
        array $validTypes,
    ): LengthAwarePaginator {
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhereHas('transaction', function ($tq) use ($search) {
                        $tq->where('customer_name', 'like', "%{$search}%")
                            ->orWhere('reference_number', 'like', "%{$search}%");
                    });
            });
        }

        if ($type = $request->input('type')) {
            if ($type !== 'all' && in_array($type, $validTypes, true)) {
                $query->where('type', $type);
            }
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('entry_date', '>=', $dateFrom);
        }
        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('entry_date', '<=', $dateTo);
        }

        $sort = (string) $request->input('sort', 'entry_date');
        $direction = strtolower((string) $request->input('direction', 'desc')) === 'asc' ? 'asc' : 'desc';

        if (in_array($sort, ['amount', 'running_balance', 'type', 'created_at'], true)) {
            $query->orderBy($sort, $direction);
        } else {
            $query->orderByRaw('date(entry_date) '.$direction);
        }

        if ($sort === 'entry_date' || ! $request->has('sort')) {
            $query->orderBy('sort_order', $direction);
        }

        if ($sort !== 'id') {
            $query->orderBy('id', $direction);
        }

        $paginated = $query->paginate(15)->withQueryString();

        $pageDates = $paginated->getCollection()->map(function ($item) {
            return Carbon::parse($item->getAttribute('entry_date'))->toDateString();
        })->unique()->values()->all();

        $dateStats = $modelClass::query()
            ->whereIn('entry_date', $pageDates)
            ->selectRaw('entry_date, MIN(sort_order) as min_order, MAX(sort_order) as max_order, COUNT(*) as entry_count')
            ->groupBy('entry_date')
            ->get()
            ->keyBy(function ($item) {
                return Carbon::parse($item->getAttribute('entry_date'))->toDateString();
            });

        $paginated->getCollection()->transform(function ($entry) use ($dateStats, $direction) {
            $dateStr = Carbon::parse($entry->getAttribute('entry_date'))->toDateString();
            $stat = $dateStats->get($dateStr);
            if (! $stat || (int) $stat->getAttribute('entry_count') <= 1) {
                $entry->setAttribute('can_move_up', false);
                $entry->setAttribute('can_move_down', false);

                return $entry;
            }

            $maxOrder = (int) $stat->getAttribute('max_order');
            $minOrder = (int) $stat->getAttribute('min_order');
            $sortOrder = (int) $entry->getAttribute('sort_order');

            if ($direction === 'desc') {
                $entry->setAttribute('can_move_up', $sortOrder < $maxOrder);
                $entry->setAttribute('can_move_down', $sortOrder > $minOrder);
            } else {
                $entry->setAttribute('can_move_up', $sortOrder > $minOrder);
                $entry->setAttribute('can_move_down', $sortOrder < $maxOrder);
            }

            return $entry;
        });

        return $paginated;
    }

    /**
     * Swap an entry's sort_order with its adjacent sibling on the same date.
     *
     * @template TModel of Model
     *
     * @param  class-string<TModel>  $modelClass
     * @param  TModel  $target
     */
    public function moveEntryWithinDate(
        string $modelClass,
        Model $target,
        string $visualDirection,
        string $tableDirection = 'desc',
    ): bool {
        $entryDate = Carbon::parse($target->getAttribute('entry_date'))->toDateString();

        $dayEntries = $modelClass::query()
            ->whereDate('entry_date', $entryDate)
            ->orderBy('sort_order', 'asc')
            ->orderBy('id', 'asc')
            ->get();

        if ($dayEntries->count() <= 1) {
            return false;
        }

        foreach ($dayEntries as $index => $item) {
            $expectedOrder = $index + 1;
            if ((int) $item->getAttribute('sort_order') !== $expectedOrder) {
                $item->setAttribute('sort_order', $expectedOrder);
                $item->saveQuietly();
            }
        }

        $targetId = $target->getKey();
        $currentIndex = $dayEntries->search(fn ($item) => $item->getKey() === $targetId);

        if ($currentIndex === false) {
            return false;
        }

        $targetIndex = null;
        if ($tableDirection === 'desc') {
            if ($visualDirection === 'up' && $currentIndex < $dayEntries->count() - 1) {
                $targetIndex = $currentIndex + 1;
            } elseif ($visualDirection === 'down' && $currentIndex > 0) {
                $targetIndex = $currentIndex - 1;
            }
        } else {
            if ($visualDirection === 'up' && $currentIndex > 0) {
                $targetIndex = $currentIndex - 1;
            } elseif ($visualDirection === 'down' && $currentIndex < $dayEntries->count() - 1) {
                $targetIndex = $currentIndex + 1;
            }
        }

        if ($targetIndex === null) {
            return false;
        }

        $currentItem = $dayEntries[$currentIndex];
        $sibling = $dayEntries[$targetIndex];

        $currentOrder = $currentItem->getAttribute('sort_order');
        $currentItem->setAttribute('sort_order', $sibling->getAttribute('sort_order'));
        $sibling->setAttribute('sort_order', $currentOrder);

        $currentItem->saveQuietly();
        $sibling->saveQuietly();

        return true;
    }
}
