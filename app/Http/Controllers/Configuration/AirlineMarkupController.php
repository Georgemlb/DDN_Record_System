<?php

namespace App\Http\Controllers\Configuration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Configuration\AirlineMarkupRequest;
use App\Models\AirlineMarkup;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AirlineMarkupController extends Controller
{
    public function store(AirlineMarkupRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if ($validated['service_type'] === 'ferry') {
            $validated['flight_type'] = null;
        }

        $validated['is_active'] = $request->boolean('is_active', true);

        AirlineMarkup::create($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Ticketing markup created successfully.']);

        return redirect()->route('configurations.index', ['tab' => 'airline-markups']);
    }

    public function update(AirlineMarkupRequest $request, AirlineMarkup $airlineMarkup): RedirectResponse
    {
        $validated = $request->validated();

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
