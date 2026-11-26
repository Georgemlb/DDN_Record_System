<?php

namespace App\Http\Controllers\Configuration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuration\OnlineAssistanceTypeRequest;
use App\Models\OnlineAssistanceType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class OnlineAssistanceTypeController extends Controller
{
    public function store(OnlineAssistanceTypeRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $hasDisbursement = $request->boolean('has_disbursement', false);
        $amount = $hasDisbursement && ! empty($validated['disbursement_amount'])
            ? (float) $validated['disbursement_amount']
            : null;

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['has_disbursement'] = $hasDisbursement;
        $validated['disbursement_amount'] = $amount;
        $validated['is_active'] = $request->boolean('is_active', true);

        OnlineAssistanceType::create($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Online assistance type created successfully.']);

        return redirect()->route('configurations.index', ['tab' => 'online-assistance']);
    }

    public function update(OnlineAssistanceTypeRequest $request, OnlineAssistanceType $onlineAssistanceType): RedirectResponse
    {
        $validated = $request->validated();

        $hasDisbursement = $request->boolean('has_disbursement', false);
        $amount = $hasDisbursement && ! empty($validated['disbursement_amount'])
            ? (float) $validated['disbursement_amount']
            : null;

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['has_disbursement'] = $hasDisbursement;
        $validated['disbursement_amount'] = $amount;
        $validated['is_active'] = $request->boolean('is_active', true);

        $onlineAssistanceType->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Online assistance type updated successfully.']);

        return redirect()->route('configurations.index', ['tab' => 'online-assistance']);
    }

    public function destroy(OnlineAssistanceType $onlineAssistanceType): RedirectResponse
    {
        if ($onlineAssistanceType->transactions()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Cannot delete online assistance type because it is used in existing transactions. Deactivate it instead.',
            ]);

            return redirect()->route('configurations.index', ['tab' => 'online-assistance']);
        }

        $onlineAssistanceType->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Online assistance type deleted successfully.']);

        return redirect()->route('configurations.index', ['tab' => 'online-assistance']);
    }
}
