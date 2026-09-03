<?php

namespace App\Http\Controllers\Configuration;

use App\Http\Controllers\Controller;
use App\Models\OnlineAssistanceType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OnlineAssistanceTypeController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'price' => ['required', 'numeric', 'min:0'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer'],
        ]);

        OnlineAssistanceType::create($validated);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Online assistance type created successfully.']);

        return redirect()->route('configurations.index', ['tab' => 'online-assistance']);
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

            return redirect()->route('configurations.index', ['tab' => 'online-assistance'])
                ->with('error', 'Cannot delete online assistance type because it is used in existing transactions. Deactivate it instead.');
        }

        $onlineAssistanceType->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Online assistance type deleted successfully.']);

        return redirect()->route('configurations.index', ['tab' => 'online-assistance']);
    }
}
