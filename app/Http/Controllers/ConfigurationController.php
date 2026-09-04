<?php

namespace App\Http\Controllers;

use App\Models\BaggageOption;
use App\Models\ExpenseCategory;
use App\Models\OnlineAssistanceType;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConfigurationController extends Controller
{
    public function index(Request $request): Response
    {
        $allowedTabs = ['baggage', 'online-assistance', 'expense-categories'];
        $tab = $request->query('tab', 'baggage');

        if (! in_array($tab, $allowedTabs, true)) {
            $tab = 'baggage';
        }

        return Inertia::render('configurations/Index', [
            'baggageOptions' => BaggageOption::orderBy('sort_order')->orderBy('weight_kg')->get(),
            'onlineAssistanceTypes' => OnlineAssistanceType::orderBy('sort_order')->orderBy('name')->get(),
            'expenseCategories' => ExpenseCategory::orderBy('sort_order')->orderBy('name')->get(),
            'currentTab' => $tab,
        ]);
    }
}
