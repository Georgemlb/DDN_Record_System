<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\OnlineAssistanceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class OnlineAssistanceTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('settings/OnlineAssistanceTypes', [
            'onlineAssistanceTypes' => OnlineAssistanceType::orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer'],
        ]);

        OnlineAssistanceType::create($validated);

        return back()->with('success', 'Online assistance type created successfully.');
    }

    public function update(Request $request, OnlineAssistanceType $onlineAssistanceType): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer'],
        ]);

        $onlineAssistanceType->update($validated);

        return back()->with('success', 'Online assistance type updated successfully.');
    }

    public function destroy(OnlineAssistanceType $onlineAssistanceType): RedirectResponse
    {
        if ($onlineAssistanceType->transactions()->exists()) {
            return back()->with('error', 'Cannot delete online assistance type because it is used in existing transactions. Deactivate it instead.');
        }

        $onlineAssistanceType->delete();

        return back()->with('success', 'Online assistance type deleted successfully.');
    }
}
