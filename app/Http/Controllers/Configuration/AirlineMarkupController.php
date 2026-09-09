<?php

namespace App\Http\Controllers\Configuration;

use App\Http\Controllers\Controller;
use App\Models\AirlineMarkup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AirlineMarkupController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'service_type' => ['required', 'in:flight,ferry'],
            'airline' => ['required', 'string', 'max:255'],
            'flight_type' => ['nullable', 'required_if:service_type,flight', 'in:local,international'],
            'markup_type' => ['required', 'in:fixed,percent'],
            'markup_amount' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'is_active' => ['boolean'],
        ]);

        if ($validated['service_type'] === 'ferry') {
            $validated['flight_type'] = null;
        }

        $validated['is_active'] = $request->boolean('is_active', true);

        AirlineMarkup::create($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ticketing markup created successfully.']);

        return redirect()->route('configurations.index', ['tab' => 'airline-markups']);
    }

    public function update(Request $request, AirlineMarkup $airlineMarkup): RedirectResponse
    {
        $validated = $request->validate([
            'service_type' => ['required', 'in:flight,ferry'],
            'airline' => ['required', 'string', 'max:255'],
            'flight_type' => ['nullable', 'required_if:service_type,flight', 'in:local,international'],
            'markup_type' => ['required', 'in:fixed,percent'],
            'markup_amount' => ['required', 'numeric', 'min:0', 'max:9999999.99'],
            'is_active' => ['boolean'],
        ]);

        if ($validated['service_type'] === 'ferry') {
            $validated['flight_type'] = null;
        }

        $validated['is_active'] = $request->boolean('is_active', true);

        $airlineMarkup->update($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ticketing markup updated successfully.']);

        return redirect()->route('configurations.index', ['tab' => 'airline-markups']);
    }

    public function destroy(AirlineMarkup $airlineMarkup): RedirectResponse
    {
        $airlineMarkup->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Airline markup deleted successfully.']);

        return redirect()->route('configurations.index', ['tab' => 'airline-markups']);
    }
}
