<?php

namespace Database\Seeders;

use App\Models\Calculation;
use App\Models\Service;
use Illuminate\Database\Seeder;

class CalculationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $services = Service::all();

        if ($services->isEmpty()) {
            $this->command->warn('No services found. Run ServiceSeeder first.');

            return;
        }

        $customers = [
            [
                'name' => 'Jan Novák',
                'email' => 'novak@priklad.cz',
                'phone' => '+420 777 111 222',
                'company' => 'Stavby s.r.o.',
            ],
            [
                'name' => 'Petra Svobodová',
                'email' => 'svobodova@firma.cz',
                'phone' => '+420 606 333 444',
                'company' => null,
            ],
            [
                'name' => 'Karel Gott',
                'email' => 'karel@zlatyslavik.cz',
                'phone' => '+420 222 333 444',
                'company' => 'Vila Bertramka',
            ],
        ];

        foreach ($customers as $customer) {
            $selectedServices = $services->random(rand(2, 4));

            $calculation = Calculation::create([
                'customer_name' => $customer['name'],
                'customer_email' => $customer['email'],
                'customer_phone' => $customer['phone'],
                'customer_company' => $customer['company'],
                'total_price' => 0,
                'total_days' => 0,
                'note' => 'Automaticky generovaná testovací kalkulace.',
                'status' => 'new',
            ]);

            foreach ($selectedServices as $svc) {
                $price = $svc->cost * (1 + ($svc->margin / 100));
                $calculation->items()->create([
                    'service_id' => $svc->id,
                    'name' => $svc->name,
                    'description' => $svc->description,
                    'cost' => $svc->cost,
                    'margin' => $svc->margin,
                    'price' => $price,
                    'days' => $svc->days,
                    'is_accepted' => false,
                ]);
            }
        }
    }
}
