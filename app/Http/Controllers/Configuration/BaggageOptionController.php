<?php

namespace App\Http\Controllers\Configuration;

use App\Http\Controllers\Controller;
use App\Models\BaggageOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BaggageOptionController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'airline' => ['nullable', 'string', 'max:255'],
            'weight_kg' => ['required', 'integer', 'min:0', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active', true);

        BaggageOption::create($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Baggage option created successfully.']);

        return redirect()->route('configurations.index', ['tab' => 'baggage']);
    }

    public function update(Request $request, BaggageOption $baggageOption): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'airline' => ['nullable', 'string', 'max:255'],
            'weight_kg' => ['required', 'integer', 'min:0', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:65535'],
        ]);

        $validated['sort_order'] = $validated['sort_order'] ?? 0;
        $validated['is_active'] = $request->boolean('is_active', true);

        $baggageOption->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Baggage option updated successfully.']);

        return redirect()->route('configurations.index', ['tab' => 'baggage']);
    }

    public function destroy(BaggageOption $baggageOption): RedirectResponse
    {
        if ($baggageOption->transactions()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => 'Cannot delete baggage option because it is used in existing transactions. Deactivate it instead.',
            ]);

            return redirect()->route('configurations.index', ['tab' => 'baggage'])
                ->with('error', 'Cannot delete baggage option because it is used in existing transactions. Deactivate it instead.');
        }

        $baggageOption->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Baggage option deleted successfully.']);

        return redirect()->route('configurations.index', ['tab' => 'baggage']);
    }
}
