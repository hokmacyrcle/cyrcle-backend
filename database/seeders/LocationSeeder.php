<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Location;
use App\Models\Company;

class LocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();

        foreach ($companies as $company) {
            // Create primary location for each company
            Location::create([
                'company_id' => $company->company_id,
                'address_line_1' => $this->getAddressLine1($company->company_name),
                'address_line_2' => 'Suite ' . rand(100, 999),
                'city' => $this->getCity($company->company_id),
                'state' => $this->getState($company->company_id),
                'postcode' => $this->getPostcode($company->company_id),
                'country' => 'Australia',
                'location_status' => 'Active',
            ]);

            // Create additional locations for some companies
            if (in_array($company->company_id, [1, 2, 5])) {
                Location::create([
                    'company_id' => $company->company_id,
                    'address_line_1' => $this->getSecondaryAddress($company->company_name),
                    'address_line_2' => 'Level ' . rand(1, 20),
                    'city' => $this->getSecondaryCity($company->company_id),
                    'state' => $this->getSecondaryState($company->company_id),
                    'postcode' => $this->getSecondaryPostcode($company->company_id),
                    'country' => 'Australia',
                    'location_status' => 'Active',
                ]);
            }
        }
    }

    private function getAddressLine1($companyName)
    {
        $addresses = [
            'TechCorp Solutions Pty Ltd' => '123 Innovation Drive',
            'Global Innovations Ltd' => '456 Technology Boulevard',
            'Digital Dynamics' => '789 Digital Street',
            'Future Systems Inc' => '321 Future Avenue',
            'Smart Solutions Group' => '654 Smart Road',
        ];

        return $addresses[$companyName] ?? '100 Business Street';
    }

    private function getCity($companyId)
    {
        $cities = [
            1 => 'Sydney',
            2 => 'Melbourne',
            3 => 'Brisbane',
            4 => 'Perth',
            5 => 'Adelaide',
        ];

        return $cities[$companyId] ?? 'Sydney';
    }

    private function getState($companyId)
    {
        $states = [
            1 => 'NSW',
            2 => 'VIC',
            3 => 'QLD',
            4 => 'WA',
            5 => 'SA',
        ];

        return $states[$companyId] ?? 'NSW';
    }

    private function getPostcode($companyId)
    {
        $postcodes = [
            1 => '2000',
            2 => '3000',
            3 => '4000',
            4 => '6000',
            5 => '5000',
        ];

        return $postcodes[$companyId] ?? '2000';
    }

    private function getSecondaryAddress($companyName)
    {
        $addresses = [
            'TechCorp Solutions Pty Ltd' => '456 Secondary Street',
            'Global Innovations Ltd' => '789 Backup Avenue',
            'Smart Solutions Group' => '321 Alternative Road',
        ];

        return $addresses[$companyName] ?? '200 Secondary Street';
    }

    private function getSecondaryCity($companyId)
    {
        $cities = [
            1 => 'Melbourne',
            2 => 'Brisbane',
            5 => 'Perth',
        ];

        return $cities[$companyId] ?? 'Melbourne';
    }

    private function getSecondaryState($companyId)
    {
        $states = [
            1 => 'VIC',
            2 => 'QLD',
            5 => 'WA',
        ];

        return $states[$companyId] ?? 'VIC';
    }

    private function getSecondaryPostcode($companyId)
    {
        $postcodes = [
            1 => '3000',
            2 => '4000',
            5 => '6000',
        ];

        return $postcodes[$companyId] ?? '3000';
    }
}
