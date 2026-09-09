<?php

namespace Database\Seeders;

use App\Models\AirlineMarkup;
use Illuminate\Database\Seeder;

class AirlineMarkupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $markups = [
            // Domestic Flights
            [
                'service_type' => AirlineMarkup::SERVICE_TYPE_FLIGHT,
                'airline' => 'Air Swift',
                'flight_type' => 'local',
                'markup_type' => AirlineMarkup::MARKUP_TYPE_FIXED,
                'markup_amount' => 175.00,
                'is_active' => true,
            ],
            [
                'service_type' => AirlineMarkup::SERVICE_TYPE_FLIGHT,
                'airline' => 'Cebgo',
                'flight_type' => 'local',
                'markup_type' => AirlineMarkup::MARKUP_TYPE_FIXED,
                'markup_amount' => 250.00,
                'is_active' => true,
            ],
            [
                'service_type' => AirlineMarkup::SERVICE_TYPE_FLIGHT,
                'airline' => 'Cebu Pacific',
                'flight_type' => 'local',
                'markup_type' => AirlineMarkup::MARKUP_TYPE_FIXED,
                'markup_amount' => 300.00,
                'is_active' => true,
            ],
            [
                'service_type' => AirlineMarkup::SERVICE_TYPE_FLIGHT,
                'airline' => 'Philippine Airlines',
                'flight_type' => 'local',
                'markup_type' => AirlineMarkup::MARKUP_TYPE_FIXED,
                'markup_amount' => 350.00,
                'is_active' => true,
            ],
            [
                'service_type' => AirlineMarkup::SERVICE_TYPE_FLIGHT,
                'airline' => 'Philippines AirAsia',
                'flight_type' => 'local',
                'markup_type' => AirlineMarkup::MARKUP_TYPE_FIXED,
                'markup_amount' => 300.00,
                'is_active' => true,
            ],
            [
                'service_type' => AirlineMarkup::SERVICE_TYPE_FLIGHT,
                'airline' => 'Sky Pasada',
                'flight_type' => 'local',
                'markup_type' => AirlineMarkup::MARKUP_TYPE_FIXED,
                'markup_amount' => 0.00,
                'is_active' => true,
            ],
            [
                'service_type' => AirlineMarkup::SERVICE_TYPE_FLIGHT,
                'airline' => 'Sunlight Air',
                'flight_type' => 'local',
                'markup_type' => AirlineMarkup::MARKUP_TYPE_FIXED,
                'markup_amount' => 200.00,
                'is_active' => true,
            ],
            // International Flights
            [
                'service_type' => AirlineMarkup::SERVICE_TYPE_FLIGHT,
                'airline' => 'Budget Carriers',
                'flight_type' => 'international',
                'markup_type' => AirlineMarkup::MARKUP_TYPE_FIXED,
                'markup_amount' => 1250.00,
                'is_active' => true,
            ],
            [
                'service_type' => AirlineMarkup::SERVICE_TYPE_FLIGHT,
                'airline' => 'Full Service Carriers',
                'flight_type' => 'international',
                'markup_type' => AirlineMarkup::MARKUP_TYPE_FIXED,
                'markup_amount' => 1500.00,
                'is_active' => true,
            ],
            // Ferries
            [
                'service_type' => AirlineMarkup::SERVICE_TYPE_FERRY,
                'airline' => 'All Ferries',
                'flight_type' => null,
                'markup_type' => AirlineMarkup::MARKUP_TYPE_FIXED,
                'markup_amount' => 200.00,
                'is_active' => true,
            ],
        ];

        foreach ($markups as $markup) {
            AirlineMarkup::updateOrCreate(
                [
                    'service_type' => $markup['service_type'],
                    'airline' => $markup['airline'],
                    'flight_type' => $markup['flight_type'],
                ],
                [
                    'markup_type' => $markup['markup_type'],
                    'markup_amount' => $markup['markup_amount'],
                    'is_active' => $markup['is_active'],
                ]
            );
        }
    }
}
