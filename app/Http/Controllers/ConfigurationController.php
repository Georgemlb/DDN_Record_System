<?php

namespace App\Http\Controllers;

use App\Models\AirlineMarkup;
use App\Models\ExpenseCategory;
use App\Models\OnlineAssistanceType;
use App\Models\PsaRate;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConfigurationController extends Controller
{
    public function index(Request $request): Response
    {
        $allowedTabs = ['airline-markups', 'online-assistance', 'expense-categories', 'psa-rates'];
        $tab = $request->query('tab', 'airline-markups');

        if (! in_array($tab, $allowedTabs, true)) {
            $tab = 'airline-markups';
        }

        return Inertia::render('configurations/Index', [
            'onlineAssistanceTypes' => OnlineAssistanceType::orderBy('sort_order')->orderBy('name')->get(),
            'expenseCategories' => ExpenseCategory::orderBy('sort_order')->orderBy('name')->get(),
            'airlineMarkups' => AirlineMarkup::orderBy('service_type')->orderBy('airline')->orderBy('flight_type')->get(),
            'psaRates' => PsaRate::orderBy('sort_order')->orderBy('name')->get(),
            'currentTab' => $tab,
        ]);
    }
}
