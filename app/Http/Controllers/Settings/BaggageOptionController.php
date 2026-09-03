<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\BaggageOption;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BaggageOptionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/BaggageOptions', [
            'baggageOptions' => BaggageOption::orderBy('sort_order')->orderBy('weight_kg')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'airline' => ['nullable', 'string', 'max:255'],
            'weight_kg' => ['required', 'integer', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer'],
        ]);

        BaggageOption::create($validated);

        return back()->with('success', 'Baggage option created successfully.');
    }

    public function update(Request $request, BaggageOption $baggageOption): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'airline' => ['nullable', 'string', 'max:255'],
            'weight_kg' => ['required', 'integer', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer'],
        ]);

        $baggageOption->update($validated);

        return back()->with('success', 'Baggage option updated successfully.');
    }

    public function destroy(BaggageOption $baggageOption): RedirectResponse
    {
        if ($baggageOption->transactions()->exists()) {
            return back()->with('error', 'Cannot delete baggage option because it is used in existing transactions. Deactivate it instead.');
        }

        $baggageOption->delete();

        return back()->with('success', 'Baggage option deleted successfully.');
    }
}
