<?php

namespace App\Http\Controllers\Configuration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuration\PsaRateRequest;
use App\Models\PsaRate;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PsaRateController extends Controller
{
    public function store(PsaRateRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active', true);

        PsaRate::create($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'PSA rate created successfully.']);

        return redirect()->route('configurations.index', ['tab' => 'psa-rates']);
    }

    public function update(PsaRateRequest $request, PsaRate $psaRate): RedirectResponse
    {
        $validated = $request->validated();

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active', true);

        $psaRate->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'PSA rate updated successfully.']);

        return redirect()->route('configurations.index', ['tab' => 'psa-rates']);
    }

    public function destroy(PsaRate $psaRate): RedirectResponse
    {
        if (Transaction::where('type', Transaction::TYPE_PSA)->where('psa_document_type', $psaRate->document_type)->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Cannot delete PSA rate because it is used in existing transactions. Deactivate it instead.',
            ]);

            return redirect()->route('configurations.index', ['tab' => 'psa-rates']);
        }

        $psaRate->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'PSA rate deleted successfully.']);

        return redirect()->route('configurations.index', ['tab' => 'psa-rates']);
    }
}
