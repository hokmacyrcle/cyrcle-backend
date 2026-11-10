<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Contact;
use App\Models\Company;

class ContactSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $companies = Company::all();

        foreach ($companies as $company) {
            // Create individual contacts
            Contact::create([
                'company_id' => $company->company_id,
                'contact_type' => 'Individual',
                'contact_first_name' => $this->getFirstName($company->company_id),
                'contact_middle_name' => $this->getMiddleName($company->company_id),
                'contact_last_name' => $this->getLastName($company->company_id),
                'contact_phone_number' => $this->getPhoneNumber($company->company_id),
                'contact_mobile_number' => $this->getMobileNumber($company->company_id),
                'contact_email_address' => $this->getEmailAddress($company->company_id),
                'contact_address_line_1' => $this->getContactAddress($company->company_id),
                'contact_city' => $this->getContactCity($company->company_id),
                'contact_state' => $this->getContactState($company->company_id),
                'contact_post_code' => $this->getContactPostcode($company->company_id),
                'contact_country' => 'Australia',
                'contact_category' => 'Supplier',
                'contact_status' => 'Active',
            ]);

            // Create company contacts for some companies
            if (in_array($company->company_id, [1, 2, 5])) {
                Contact::create([
                    'company_id' => $company->company_id,
                    'contact_type' => 'Company',
                    'contact_company_name' => $this->getSupplierCompany($company->company_id),
                    'contact_phone_number' => $this->getSupplierPhone($company->company_id),
                    'contact_email_address' => $this->getSupplierEmail($company->company_id),
                    'contact_address_line_1' => $this->getSupplierAddress($company->company_id),
                    'contact_city' => $this->getSupplierCity($company->company_id),
                    'contact_state' => $this->getSupplierState($company->company_id),
                    'contact_post_code' => $this->getSupplierPostcode($company->company_id),
                    'contact_country' => 'Australia',
                    'contact_category' => 'Vendor',
                    'contact_status' => 'Active',
                ]);
            }

            // Create sole trader contacts for some companies
            if (in_array($company->company_id, [3, 4])) {
                Contact::create([
                    'company_id' => $company->company_id,
                    'contact_type' => 'Sole Trader',
                    'contact_first_name' => $this->getSoleTraderFirstName($company->company_id),
                    'contact_last_name' => $this->getSoleTraderLastName($company->company_id),
                    'contact_phone_number' => $this->getSoleTraderPhone($company->company_id),
                    'contact_mobile_number' => $this->getSoleTraderMobile($company->company_id),
                    'contact_email_address' => $this->getSoleTraderEmail($company->company_id),
                    'contact_address_line_1' => $this->getSoleTraderAddress($company->company_id),
                    'contact_city' => $this->getSoleTraderCity($company->company_id),
                    'contact_state' => $this->getSoleTraderState($company->company_id),
                    'contact_post_code' => $this->getSoleTraderPostcode($company->company_id),
                    'contact_country' => 'Australia',
                    'contact_category' => 'Other',
                    'contact_status' => 'Active',
                ]);
            }
        }
    }

    private function getFirstName($companyId)
    {
        $names = [
            1 => 'John',
            2 => 'Sarah',
            3 => 'Michael',
            4 => 'Emma',
            5 => 'David',
        ];

        return $names[$companyId] ?? 'James';
    }

    private function getMiddleName($companyId)
    {
        $names = [
            1 => 'Robert',
            2 => 'Elizabeth',
            3 => 'Thomas',
            4 => 'Grace',
            5 => 'William',
        ];

        return $names[$companyId] ?? 'Alexander';
    }

    private function getLastName($companyId)
    {
        $names = [
            1 => 'Smith',
            2 => 'Johnson',
            3 => 'Williams',
            4 => 'Brown',
            5 => 'Jones',
        ];

        return $names[$companyId] ?? 'Davis';
    }

    private function getPhoneNumber($companyId)
    {
        $phones = [
            1 => '02 9123 4567',
            2 => '03 8234 5678',
            3 => '07 7345 6789',
            4 => '08 6456 7890',
            5 => '08 5567 8901',
        ];

        return $phones[$companyId] ?? '02 9000 0000';
    }

    private function getMobileNumber($companyId)
    {
        $mobiles = [
            1 => '0412 345 678',
            2 => '0423 456 789',
            3 => '0434 567 890',
            4 => '0445 678 901',
            5 => '0456 789 012',
        ];

        return $mobiles[$companyId] ?? '0400 000 000';
    }

    private function getEmailAddress($companyId)
    {
        $emails = [
            1 => 'john.smith@techcorp.com',
            2 => 'sarah.johnson@globalinnovations.com',
            3 => 'michael.williams@digitaldynamics.com',
            4 => 'emma.brown@futuresystems.com',
            5 => 'david.jones@smartsolutions.com',
        ];

        return $emails[$companyId] ?? 'contact@company.com';
    }

    private function getContactAddress($companyId)
    {
        $addresses = [
            1 => '123 Main Street',
            2 => '456 Oak Avenue',
            3 => '789 Pine Road',
            4 => '321 Elm Street',
            5 => '654 Maple Drive',
        ];

        return $addresses[$companyId] ?? '100 Contact Street';
    }

    private function getContactCity($companyId)
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

    private function getContactState($companyId)
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

    private function getContactPostcode($companyId)
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

    private function getSupplierCompany($companyId)
    {
        $suppliers = [
            1 => 'Tech Supplies Co',
            2 => 'Global Equipment Ltd',
            5 => 'Smart Parts Pty Ltd',
        ];

        return $suppliers[$companyId] ?? 'General Supplies';
    }

    private function getSupplierPhone($companyId)
    {
        $phones = [
            1 => '02 9876 5432',
            2 => '03 8765 4321',
            5 => '08 7654 3210',
        ];

        return $phones[$companyId] ?? '02 9000 0000';
    }

    private function getSupplierEmail($companyId)
    {
        $emails = [
            1 => 'sales@techsupplies.com',
            2 => 'info@globalequipment.com',
            5 => 'contact@smartparts.com',
        ];

        return $emails[$companyId] ?? 'sales@supplier.com';
    }

    private function getSupplierAddress($companyId)
    {
        $addresses = [
            1 => '456 Supply Street',
            2 => '789 Equipment Road',
            5 => '321 Parts Avenue',
        ];

        return $addresses[$companyId] ?? '100 Supply Street';
    }

    private function getSupplierCity($companyId)
    {
        $cities = [
            1 => 'Sydney',
            2 => 'Melbourne',
            5 => 'Adelaide',
        ];

        return $cities[$companyId] ?? 'Sydney';
    }

    private function getSupplierState($companyId)
    {
        $states = [
            1 => 'NSW',
            2 => 'VIC',
            5 => 'SA',
        ];

        return $states[$companyId] ?? 'NSW';
    }

    private function getSupplierPostcode($companyId)
    {
        $postcodes = [
            1 => '2000',
            2 => '3000',
            5 => '5000',
        ];

        return $postcodes[$companyId] ?? '2000';
    }

    private function getSoleTraderFirstName($companyId)
    {
        $names = [
            3 => 'Lisa',
            4 => 'Peter',
        ];

        return $names[$companyId] ?? 'Alex';
    }

    private function getSoleTraderLastName($companyId)
    {
        $names = [
            3 => 'Anderson',
            4 => 'Wilson',
        ];

        return $names[$companyId] ?? 'Taylor';
    }

    private function getSoleTraderPhone($companyId)
    {
        $phones = [
            3 => '07 7123 4567',
            4 => '08 6234 5678',
        ];

        return $phones[$companyId] ?? '02 9000 0000';
    }

    private function getSoleTraderMobile($companyId)
    {
        $mobiles = [
            3 => '0434 123 456',
            4 => '0445 234 567',
        ];

        return $mobiles[$companyId] ?? '0400 000 000';
    }

    private function getSoleTraderEmail($companyId)
    {
        $emails = [
            3 => 'lisa.anderson@freelance.com',
            4 => 'peter.wilson@consultant.com',
        ];

        return $emails[$companyId] ?? 'contact@freelance.com';
    }

    private function getSoleTraderAddress($companyId)
    {
        $addresses = [
            3 => '123 Freelance Street',
            4 => '456 Consultant Road',
        ];

        return $addresses[$companyId] ?? '100 Freelance Street';
    }

    private function getSoleTraderCity($companyId)
    {
        $cities = [
            3 => 'Brisbane',
            4 => 'Perth',
        ];

        return $cities[$companyId] ?? 'Sydney';
    }

    private function getSoleTraderState($companyId)
    {
        $states = [
            3 => 'QLD',
            4 => 'WA',
        ];

        return $states[$companyId] ?? 'NSW';
    }

    private function getSoleTraderPostcode($companyId)
    {
        $postcodes = [
            3 => '4000',
            4 => '6000',
        ];

        return $postcodes[$companyId] ?? '2000';
    }
}
