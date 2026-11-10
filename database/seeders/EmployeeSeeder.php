<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Employee;
use App\Models\Company;
use Carbon\Carbon;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();

        foreach ($companies as $company) {
            // Create multiple employees for each company
            $employeeCount = rand(3, 8); // Random number of employees per company

            for ($i = 0; $i < $employeeCount; $i++) {
                Employee::create([
                    'emp_first_name' => $this->getFirstName($company->company_id, $i),
                    'emp_middle_name' => $this->getMiddleName($company->company_id, $i),
                    'emp_last_name' => $this->getLastName($company->company_id, $i),
                    'emp_gender' => $this->getGender($company->company_id, $i),
                    'emp_email_address' => $this->getEmailAddress($company->company_id, $i),
                    'emp_phone_number' => $this->getPhoneNumber($company->company_id, $i),
                    'emp_mobile_number' => $this->getMobileNumber($company->company_id, $i),
                    'emp_company_id' => $company->company_id,
                    'emp_status' => $this->getStatus($company->company_id, $i),
                    'emp_start_date' => $this->getStartDate($company->company_id, $i),
                    'emp_tfn' => $this->getTFN($company->company_id, $i),
                    'emp_work_authorization' => $this->getWorkAuthorization($company->company_id, $i),
                    'emp_visa_subclass' => $this->getVisaSubclass($company->company_id, $i),
                    'emp_visa_expiry_date' => $this->getVisaExpiryDate($company->company_id, $i),
                    'emp_employment_type' => $this->getEmploymentType($company->company_id, $i),
                    'emp_address_line_1' => $this->getAddressLine1($company->company_id, $i),
                    'emp_address_line_2' => $this->getAddressLine2($company->company_id, $i),
                    'emp_city' => $this->getCity($company->company_id, $i),
                    'emp_state' => $this->getState($company->company_id, $i),
                    'emp_post_code' => $this->getPostcode($company->company_id, $i),
                    'emp_country' => 'Australia',
                ]);
            }
        }
    }

    private function getFirstName($companyId, $index)
    {
        $names = [
            1 => ['Alice', 'Bob', 'Carol', 'David', 'Eva', 'Frank', 'Grace', 'Henry'],
            2 => ['Ivy', 'Jack', 'Kate', 'Liam', 'Mia', 'Noah', 'Olivia', 'Paul'],
            3 => ['Quinn', 'Ruby', 'Sam', 'Tara', 'Uma', 'Victor', 'Wendy', 'Xavier'],
            4 => ['Yara', 'Zoe', 'Adam', 'Bella', 'Chris', 'Diana', 'Ethan', 'Fiona'],
            5 => ['George', 'Hannah', 'Ian', 'Julia', 'Kevin', 'Laura', 'Mark', 'Nina'],
        ];

        return $names[$companyId][$index] ?? 'John';
    }

    private function getMiddleName($companyId, $index)
    {
        $names = [
            1 => ['Rose', 'James', 'Marie', 'Robert', 'Anne', 'Michael', 'Jane', 'William'],
            2 => ['Elizabeth', 'Thomas', 'Grace', 'Daniel', 'Faith', 'Joseph', 'Hope', 'Christopher'],
            3 => ['Joy', 'Andrew', 'Peace', 'Matthew', 'Love', 'Joshua', 'Mercy', 'Ryan'],
            4 => ['Grace', 'Nathan', 'Faith', 'Tyler', 'Hope', 'Brandon', 'Charity', 'Justin'],
            5 => ['Patience', 'Austin', 'Temperance', 'Evan', 'Prudence', 'Connor', 'Chastity', 'Cameron'],
        ];

        return $names[$companyId][$index] ?? 'Alexander';
    }

    private function getLastName($companyId, $index)
    {
        $names = [
            1 => ['Anderson', 'Brown', 'Clark', 'Davis', 'Evans', 'Fisher', 'Garcia', 'Harris'],
            2 => ['Jackson', 'King', 'Lee', 'Miller', 'Nelson', 'O\'Connor', 'Parker', 'Quinn'],
            3 => ['Roberts', 'Smith', 'Taylor', 'Underwood', 'Vargas', 'Wilson', 'Young', 'Zimmerman'],
            4 => ['Adams', 'Baker', 'Cooper', 'Dixon', 'Edwards', 'Foster', 'Gordon', 'Hayes'],
            5 => ['Irwin', 'Johnson', 'Klein', 'Lewis', 'Morgan', 'Newman', 'Owens', 'Patterson'],
        ];

        return $names[$companyId][$index] ?? 'Smith';
    }

    private function getGender($companyId, $index)
    {
        $genders = ['Male', 'Female', 'Other'];
        return $genders[($companyId + $index) % 3];
    }

    private function getEmailAddress($companyId, $index)
    {
        $firstName = strtolower($this->getFirstName($companyId, $index));
        $lastName = strtolower($this->getLastName($companyId, $index));
        
        $domains = [
            1 => 'techcorp.com',
            2 => 'globalinnovations.com',
            3 => 'digitaldynamics.com',
            4 => 'futuresystems.com',
            5 => 'smartsolutions.com',
        ];

        $domain = $domains[$companyId] ?? 'company.com';
        return "{$firstName}.{$lastName}@{$domain}";
    }

    private function getPhoneNumber($companyId, $index)
    {
        $areaCodes = [
            1 => '02', // Sydney
            2 => '03', // Melbourne
            3 => '07', // Brisbane
            4 => '08', // Perth
            5 => '08', // Adelaide
        ];

        $areaCode = $areaCodes[$companyId] ?? '02';
        $number = str_pad(rand(10000000, 99999999), 8, '0', STR_PAD_LEFT);
        return "{$areaCode} {$number}";
    }

    private function getMobileNumber($companyId, $index)
    {
        $prefixes = ['0412', '0423', '0434', '0445', '0456', '0467', '0478', '0489'];
        $prefix = $prefixes[($companyId + $index) % count($prefixes)];
        $number = str_pad(rand(100000, 999999), 6, '0', STR_PAD_LEFT);
        return "{$prefix} {$number}";
    }

    private function getStatus($companyId, $index)
    {
        $statuses = ['Active', 'Active', 'Active', 'Active', 'Resigned', 'Active', 'Active', 'Active'];
        return $statuses[$index] ?? 'Active';
    }

    private function getStartDate($companyId, $index)
    {
        $baseDate = Carbon::now()->subYears(rand(1, 5));
        return $baseDate->subMonths(rand(0, 12))->format('Y-m-d');
    }

    private function getTFN($companyId, $index)
    {
        return str_pad(rand(100000000, 999999999), 9, '0', STR_PAD_LEFT);
    }

    private function getWorkAuthorization($companyId, $index)
    {
        $authorizations = ['Citizen', 'Permanent Resident', 'Visa', 'Citizen', 'Permanent Resident'];
        return $authorizations[($companyId + $index) % count($authorizations)];
    }

    private function getVisaSubclass($companyId, $index)
    {
        $visaSubclasses = ['482', '189', '190', '491', '485'];
        $authorization = $this->getWorkAuthorization($companyId, $index);
        
        if ($authorization === 'Visa') {
            return $visaSubclasses[($companyId + $index) % count($visaSubclasses)];
        }
        
        return null;
    }

    private function getVisaExpiryDate($companyId, $index)
    {
        $authorization = $this->getWorkAuthorization($companyId, $index);
        
        if ($authorization === 'Visa') {
            return Carbon::now()->addYears(rand(1, 4))->format('Y-m-d');
        }
        
        return null;
    }

    private function getEmploymentType($companyId, $index)
    {
        $types = ['Full Time', 'Part Time', 'Contract', 'Casual', 'Full Time', 'Part Time'];
        return $types[($companyId + $index) % count($types)];
    }

    private function getAddressLine1($companyId, $index)
    {
        $addresses = [
            1 => ['123 Main Street', '456 Oak Avenue', '789 Pine Road', '321 Elm Street', '654 Maple Drive', '987 Cedar Lane', '147 Birch Court', '258 Spruce Way'],
            2 => ['111 Collins Street', '222 Flinders Street', '333 Swanston Street', '444 Bourke Street', '555 Elizabeth Street', '666 La Trobe Street', '777 Russell Street', '888 Exhibition Street'],
            3 => ['100 Queen Street', '200 George Street', '300 Adelaide Street', '400 Edward Street', '500 Ann Street', '600 Mary Street', '700 Charlotte Street', '800 Alice Street'],
            4 => ['10 Hay Street', '20 Murray Street', '30 St Georges Terrace', '40 Barrack Street', '50 Wellington Street', '60 William Street', '70 Pier Street', '80 King Street'],
            5 => ['1 Rundle Street', '2 Hindley Street', '3 Gouger Street', '4 O\'Connell Street', '5 Pulteney Street', '6 Waymouth Street', '7 Currie Street', '8 Grenfell Street'],
        ];

        return $addresses[$companyId][$index] ?? '100 Employee Street';
    }

    private function getAddressLine2($companyId, $index)
    {
        $types = ['Unit ' . rand(1, 999), 'Apartment ' . rand(1, 999), 'Suite ' . rand(1, 999), 'Level ' . rand(1, 20)];
        return $types[($companyId + $index) % count($types)];
    }

    private function getCity($companyId, $index)
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

    private function getState($companyId, $index)
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

    private function getPostcode($companyId, $index)
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
}
