<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Models\TaxRate;
use App\Models\PaymentTerm;
use Illuminate\Http\JsonResponse;

class DropdownController extends Controller
{
    /**
     * Get simplified dropdown data for invoice page
     */
    public function getInvoiceDropdowns(): JsonResponse
    {
        try {
            // Get contacts grouped by company
            $contacts = Contact::select('contact_id', 'contact_first_name', 'contact_last_name', 'contact_company_name', 'contact_type', 'company_id')
                ->with('company:company_id,company_name')
                ->orderBy('company_id')
                ->orderBy('contact_first_name')
                ->get()
                ->groupBy('company_id')
                ->map(function ($companyContacts, $companyId) {
                    $company = $companyContacts->first()->company;
                    return [
                        'company_id' => $companyId,
                        'company_name' => $company->company_name ?? 'Unknown Company',
                        'contacts' => $companyContacts->map(function ($contact) {
                            // Build display name based on contact type
                            if ($contact->contact_type === 'Company') {
                                $displayName = $contact->contact_company_name ?? 'Unknown Company';
                            } else {
                                $firstName = $contact->contact_first_name ?? '';
                                $lastName = $contact->contact_last_name ?? '';
                                $displayName = trim($firstName . ' ' . $lastName) ?: 'Unknown Person';
                            }

                            return [
                                'id' => $contact->contact_id,
                                'text' => $displayName,
                                'type' => $contact->contact_type,
                            ];
                        })
                    ];
                })
                ->values();

            // Get payment terms (simplified)
            $paymentTerms = PaymentTerm::active()
                ->select('id', 'name', 'description')
                ->orderBy('name')
                ->get()
                ->map(function ($paymentTerm) {
                    return [
                        'id' => $paymentTerm->id,
                        'text' => $paymentTerm->name,
                        'description' => $paymentTerm->description,
                    ];
                });

            // Get common item names (you can customize this list)
            $itemNames = [
                ['id' => 'web_development', 'text' => 'Web Development Services'],
                ['id' => 'consulting', 'text' => 'Consulting Services'],
                ['id' => 'design', 'text' => 'Design Services'],
                ['id' => 'maintenance', 'text' => 'Maintenance Services'],
                ['id' => 'training', 'text' => 'Training Services'],
                ['id' => 'support', 'text' => 'Technical Support'],
                ['id' => 'hosting', 'text' => 'Hosting Services'],
                ['id' => 'software', 'text' => 'Software Development'],
                ['id' => 'mobile_app', 'text' => 'Mobile App Development'],
                ['id' => 'ecommerce', 'text' => 'E-commerce Solutions'],
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'contacts_by_company' => $contacts,
                    'payment_terms' => $paymentTerms,
                    'item_names' => $itemNames,
                ],
                'message' => 'Invoice dropdown data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving dropdown data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get essential dropdown data only (contacts by company, terms, items)
     */
    public function getEssentialDropdowns(): JsonResponse
    {
        try {
            // Get contacts grouped by company
            $contacts = Contact::select('contact_id', 'contact_first_name', 'contact_last_name', 'contact_company_name', 'contact_type', 'company_id')
                ->with('company:company_id,company_name')
                ->orderBy('company_id')
                ->orderBy('contact_first_name')
                ->get()
                ->groupBy('company_id')
                ->map(function ($companyContacts, $companyId) {
                    $company = $companyContacts->first()->company;
                    return [
                        'company_id' => $companyId,
                        'company_name' => $company->company_name ?? 'Unknown Company',
                        'contacts' => $companyContacts->map(function ($contact) {
                            // Build display name based on contact type
                            if ($contact->contact_type === 'Company') {
                                $displayName = $contact->contact_company_name ?? 'Unknown Company';
                            } else {
                                $firstName = $contact->contact_first_name ?? '';
                                $lastName = $contact->contact_last_name ?? '';
                                $displayName = trim($firstName . ' ' . $lastName) ?: 'Unknown Person';
                            }

                            return [
                                'id' => $contact->contact_id,
                                'text' => $displayName,
                                'type' => $contact->contact_type,
                            ];
                        })
                    ];
                })
                ->values();

            // Get payment terms (simplified)
            $paymentTerms = PaymentTerm::active()
                ->select('id', 'name', 'description')
                ->orderBy('name')
                ->get()
                ->map(function ($paymentTerm) {
                    return [
                        'id' => $paymentTerm->id,
                        'text' => $paymentTerm->name,
                        'description' => $paymentTerm->description,
                    ];
                });

            // Get common item names (you can customize this list)
            $itemNames = [
                ['id' => 'web_development', 'text' => 'Web Development Services'],
                ['id' => 'consulting', 'text' => 'Consulting Services'],
                ['id' => 'design', 'text' => 'Design Services'],
                ['id' => 'maintenance', 'text' => 'Maintenance Services'],
                ['id' => 'training', 'text' => 'Training Services'],
                ['id' => 'support', 'text' => 'Technical Support'],
                ['id' => 'hosting', 'text' => 'Hosting Services'],
                ['id' => 'software', 'text' => 'Software Development'],
                ['id' => 'mobile_app', 'text' => 'Mobile App Development'],
                ['id' => 'ecommerce', 'text' => 'E-commerce Solutions'],
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'contacts_by_company' => $contacts,
                    'payment_terms' => $paymentTerms,
                    'item_names' => $itemNames,
                ],
                'message' => 'Essential dropdown data retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving essential dropdown data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get companies dropdown only
     */
    public function getCompanies(): JsonResponse
    {
        try {
            $companies = Company::select('company_id', 'company_name', 'company_email')
                ->orderBy('company_name')
                ->get()
                ->map(function ($company) {
                    return [
                        'id' => $company->company_id,
                        'text' => $company->company_name,
                        'email' => $company->company_email,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $companies,
                'message' => 'Companies retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving companies: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get contacts dropdown only
     */
    public function getContacts(): JsonResponse
    {
        try {
            $contacts = Contact::select('contact_id', 'contact_first_name', 'contact_last_name', 'contact_company_name', 'contact_type', 'contact_email_address', 'company_id')
                ->with('company:company_id,company_name')
                ->orderBy('contact_first_name')
                ->get()
                ->map(function ($contact) {
                    // Build display name based on contact type
                    if ($contact->contact_type === 'Company') {
                        $displayName = $contact->contact_company_name ?? 'Unknown Company';
                    } else {
                        $firstName = $contact->contact_first_name ?? '';
                        $lastName = $contact->contact_last_name ?? '';
                        $displayName = trim($firstName . ' ' . $lastName) ?: 'Unknown Person';
                    }

                    return [
                        'id' => $contact->contact_id,
                        'text' => $displayName,
                        'email' => $contact->contact_email_address ?? 'N/A',
                        'type' => $contact->contact_type,
                        'company' => [
                            'id' => $contact->company->company_id ?? null,
                            'name' => $contact->company->company_name ?? 'N/A',
                        ],
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $contacts,
                'message' => 'Contacts retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving contacts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get tax rates dropdown only
     */
    public function getTaxRates(): JsonResponse
    {
        try {
            $taxRates = TaxRate::active()
                ->select('id', 'tax_name', 'rate', 'type')
                ->orderBy('tax_name')
                ->get()
                ->map(function ($taxRate) {
                    return [
                        'id' => $taxRate->id,
                        'text' => $taxRate->display_name,
                        'rate' => $taxRate->rate,
                        'type' => $taxRate->type,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $taxRates,
                'message' => 'Tax rates retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving tax rates: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get payment terms dropdown only
     */
    public function getPaymentTerms(): JsonResponse
    {
        try {
            $paymentTerms = PaymentTerm::active()
                ->select('id', 'name', 'due_calculation_method', 'fixed_days_value', 'certain_day_value')
                ->orderBy('name')
                ->get()
                ->map(function ($paymentTerm) {
                    return [
                        'id' => $paymentTerm->id,
                        'text' => $paymentTerm->display_name,
                        'due_calculation_method' => $paymentTerm->due_calculation_method,
                        'fixed_days_value' => $paymentTerm->fixed_days_value,
                        'certain_day_value' => $paymentTerm->certain_day_value,
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $paymentTerms,
                'message' => 'Payment terms retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving payment terms: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get contacts by company with payment terms and items
     */
    public function getContactsByCompany($companyId): JsonResponse
    {
        try {
            // Get contacts for the company
            $contacts = Contact::where('company_id', $companyId)
                ->select('contact_id', 'contact_first_name', 'contact_last_name', 'contact_company_name', 'contact_type')
                ->orderBy('contact_first_name')
                ->get()
                ->map(function ($contact) {
                    // Build display name based on contact type
                    if ($contact->contact_type === 'Company') {
                        $displayName = $contact->contact_company_name ?? 'Unknown Company';
                    } else {
                        $firstName = $contact->contact_first_name ?? '';
                        $lastName = $contact->contact_last_name ?? '';
                        $displayName = trim($firstName . ' ' . $lastName) ?: 'Unknown Person';
                    }

                    return [
                        'id' => $contact->contact_id,
                        'Contact Name' => $displayName,
                    ];
                });

            // Get payment terms
            $paymentTerms = PaymentTerm::active()
                ->select('id', 'name', 'description')
                ->orderBy('name')
                ->get()
                ->map(function ($paymentTerm) {
                    return [
                        'id' => $paymentTerm->id,
                        'text' => $paymentTerm->name,
                        'description' => $paymentTerm->description,
                    ];
                });

            // Get common item names
            $itemNames = [
                ['id' => 'web_development', 'text' => 'Web Development Services'],
                ['id' => 'consulting', 'text' => 'Consulting Services'],
                ['id' => 'design', 'text' => 'Design Services'],
                ['id' => 'maintenance', 'text' => 'Maintenance Services'],
                ['id' => 'training', 'text' => 'Training Services'],
                ['id' => 'support', 'text' => 'Technical Support'],
                ['id' => 'hosting', 'text' => 'Hosting Services'],
                ['id' => 'software', 'text' => 'Software Development'],
                ['id' => 'mobile_app', 'text' => 'Mobile App Development'],
                ['id' => 'ecommerce', 'text' => 'E-commerce Solutions'],
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'contacts' => $contacts,
                    'payment_terms' => $paymentTerms,
                    'items' => $itemNames,
                ],
                'message' => 'Contacts, payment terms, and items retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get contact details by contact ID (email and billing address)
     */
    public function getContactDetails($contactId): JsonResponse
    {
        try {
            $contact = Contact::select(
                'contact_id',
                'contact_first_name',
                'contact_last_name',
                'contact_company_name',
                'contact_type',
                'contact_email_address',
                'contact_phone_number',
                'contact_address_line_1',
                'contact_address_line_2',
                'contact_address_line_3',
                'contact_city',
                'contact_state',
                'contact_post_code',
                'contact_country'
            )->findOrFail($contactId);

            // Build billing address
            $billingAddress = [];
            if ($contact->contact_address_line_1) {
                $billingAddress[] = $contact->contact_address_line_1;
            }
            if ($contact->contact_address_line_2) {
                $billingAddress[] = $contact->contact_address_line_2;
            }
            if ($contact->contact_address_line_3) {
                $billingAddress[] = $contact->contact_address_line_3;
            }
            if ($contact->contact_city) {
                $billingAddress[] = $contact->contact_city;
            }
            if ($contact->contact_state) {
                $billingAddress[] = $contact->contact_state;
            }
            if ($contact->contact_post_code) {
                $billingAddress[] = $contact->contact_post_code;
            }
            if ($contact->contact_country) {
                $billingAddress[] = $contact->contact_country;
            }

            $contactData = [
                'id' => $contact->contact_id,
                'name' => $contact->contact_type === 'Company' 
                    ? ($contact->contact_company_name ?? 'Unknown Company')
                    : trim(($contact->contact_first_name ?? '') . ' ' . ($contact->contact_last_name ?? '')),
                'type' => $contact->contact_type,
                'email' => $contact->contact_email_address ?? 'N/A',
                'phone' => $contact->contact_phone_number ?? 'N/A',
                'billing_address' => implode(', ', $billingAddress) ?: 'No address provided',
                'address_components' => [
                    'line_1' => $contact->contact_address_line_1 ?? '',
                    'line_2' => $contact->contact_address_line_2 ?? '',
                    'line_3' => $contact->contact_address_line_3 ?? '',
                    'city' => $contact->contact_city ?? '',
                    'state' => $contact->contact_state ?? '',
                    'post_code' => $contact->contact_post_code ?? '',
                    'country' => $contact->contact_country ?? '',
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $contactData,
                'message' => 'Contact details retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Contact not found or error retrieving details: ' . $e->getMessage()
            ], 404);
        }
    }
}
