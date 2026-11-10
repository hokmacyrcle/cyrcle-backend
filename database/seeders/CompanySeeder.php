<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Company;
use Carbon\Carbon;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = [
            [
                'company_name' => 'TechCorp Solutions Pty Ltd',
                'company_abn' => '12345678901',
                'company_acn' => '123456789',
                'gst_registered' => true,
                'gst_number' => 'GST123456789',
                'gst_registration_start_date' => '2020-01-01',
                'company_registration_date' => '2019-06-15',
                'company_type' => 'Subscriber',
                'user_id' => 6,
            ],
            [
                'company_name' => 'Global Innovations Ltd',
                'company_abn' => '98765432109',
                'company_acn' => '987654321',
                'gst_registered' => true,
                'gst_number' => 'GST987654321',
                'gst_registration_start_date' => '2018-03-01',
                'company_registration_date' => '2017-11-20',
                'company_type' => 'Subscriber',
                'user_id' => 6,
            ],
            [
                'company_name' => 'Digital Dynamics',
                'company_abn' => '45678912345',
                'company_acn' => '456789123',
                'gst_registered' => false,
                'company_registration_date' => '2021-02-10',
                'company_type' => 'Client',
                'user_id' => 6,
            ],
            [
                'company_name' => 'Future Systems Inc',
                'company_abn' => '78912345678',
                'company_acn' => '789123456',
                'gst_registered' => true,
                'gst_number' => 'GST789123456',
                'gst_registration_start_date' => '2022-07-01',
                'company_registration_date' => '2022-01-15',
                'company_type' => 'Client',
                'user_id' => 6,
            ],
            [
                'company_name' => 'Smart Solutions Group',
                'company_abn' => '32165498732',
                'company_acn' => '321654987',
                'gst_registered' => true,
                'gst_number' => 'GST321654987',
                'gst_registration_start_date' => '2019-09-01',
                'company_registration_date' => '2019-04-22',
                'company_type' => 'Subscriber',
                'user_id' => 6,
            ],
        ];

        foreach ($companies as $companyData) {
            Company::create($companyData);
        }
    }
}
