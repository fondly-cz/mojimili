<?php

namespace Database\Seeders;

use App\Models\Company;
use Illuminate\Database\Seeder;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = [
            [
                'name' => 'Acme Corporation',
                'email' => 'contact@acmecorp.com',
                'phone' => '+1-555-0123',
                'website' => 'https://acmecorp.com',
                'address' => '123 Business Ave',
                'city' => 'New York',
                'state' => 'NY',
                'postal_code' => '10001',
                'country' => 'USA',
                'industry' => 'Technology',
                'employee_count' => 150,
                'annual_revenue' => 5000000.00,
                'status' => 'active',
                'notes' => 'Long-term client with multiple projects.',
            ],
            [
                'name' => 'TechStart Inc.',
                'email' => 'hello@techstart.io',
                'phone' => '+1-555-0456',
                'website' => 'https://techstart.io',
                'address' => '456 Innovation Blvd',
                'city' => 'San Francisco',
                'state' => 'CA',
                'postal_code' => '94102',
                'country' => 'USA',
                'industry' => 'Software',
                'employee_count' => 25,
                'annual_revenue' => 1200000.00,
                'status' => 'prospect',
                'notes' => 'Potential client for our SaaS product.',
            ],
            [
                'name' => 'Global Manufacturing Ltd.',
                'email' => 'info@globalmanuf.com',
                'phone' => '+1-555-0789',
                'website' => 'https://globalmanuf.com',
                'address' => '789 Industrial Park',
                'city' => 'Chicago',
                'state' => 'IL',
                'postal_code' => '60601',
                'country' => 'USA',
                'industry' => 'Manufacturing',
                'employee_count' => 500,
                'annual_revenue' => 25000000.00,
                'status' => 'active',
                'notes' => 'Major manufacturing client requiring custom solutions.',
            ],
            [
                'name' => 'Local Services Co.',
                'email' => 'contact@localservices.com',
                'phone' => '+1-555-0321',
                'website' => null,
                'address' => '321 Main Street',
                'city' => 'Austin',
                'state' => 'TX',
                'postal_code' => '73301',
                'country' => 'USA',
                'industry' => 'Services',
                'employee_count' => 12,
                'annual_revenue' => 350000.00,
                'status' => 'inactive',
                'notes' => 'Small local business, contract ended last year.',
            ],
        ];

        foreach ($companies as $company) {
            Company::create($company);
        }
    }
}
