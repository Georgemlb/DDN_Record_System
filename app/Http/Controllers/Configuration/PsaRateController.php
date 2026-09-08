<?php

namespace App\Http\Controllers\Configuration;

use App\Http\Controllers\Controller;
use App\Models\PsaRate;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PsaRateController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', 'string', 'max:50', 'unique:psa_rates,document_type'],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'officer_fee' => ['required', 'numeric', 'min:0', 'lte:price'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active', true);

        PsaRate::create($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'PSA rate created successfully.']);

        return redirect()->route('configurations.index', ['tab' => 'psa-rates']);
    }

    public function update(Request $request, PsaRate $psaRate): RedirectResponse
    {
        $validated = $request->validate([
            'document_type' => ['required', 'string', 'max:50', 'unique:psa_rates,document_type,'.$psaRate->id],
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'officer_fee' => ['required', 'numeric', 'min:0', 'lte:price'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

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

            return redirect()->route('configurations.index', ['tab' => 'psa-rates'])
                ->with('error', 'Cannot delete PSA rate because it is used in existing transactions. Deactivate it instead.');
        }

        $psaRate->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'PSA rate deleted successfully.']);

        return redirect()->route('configurations.index', ['tab' => 'psa-rates']);
    }
}
