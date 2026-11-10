<?php

/**
 * Test All Entities Company Context System
 * 
 * This test verifies that all entities work correctly with the company context system:
 * 1. Quotes - filtered by company_id
 * 2. Employees - filtered by emp_company_id  
 * 3. Contacts - filtered by company_id
 * 4. Clients - filtered by company_id
 * 5. Bills - filtered by company_id
 * 6. Invoices - filtered by company_id
 */

echo "=== Testing All Entities Company Context System ===\n\n";

// Test 1: Login and Select Company
echo "=== Test 1: Login and Select Company ===\n";

$loginData = [
    'email' => 'test@example.com',
    'password' => 'password123'
];

// First, login to get initial token
$loginResponse = makeApiRequest('POST', '/api/login', $loginData);
echo "Login Response: " . json_encode($loginResponse, JSON_PRETTY_PRINT) . "\n\n";

if ($loginResponse['status']) {
    $initialToken = $loginResponse['data']['access_token'];
    
    // Now select company 6
    $selectCompanyResponse = makeApiRequest('GET', '/api/select-company/6', [], $initialToken);
    echo "Select Company Response: " . json_encode($selectCompanyResponse, JSON_PRETTY_PRINT) . "\n\n";
    
    if ($selectCompanyResponse['status']) {
        $companyToken = $selectCompanyResponse['data']['access_token'];
        $selectedCompany = $selectCompanyResponse['data']['selected_company'];
        
        echo "Selected Company ID: " . $selectedCompany['company_id'] . "\n";
        echo "Selected Company Name: " . $selectedCompany['company_name'] . "\n";
        echo "New Token: " . substr($companyToken, 0, 20) . "...\n\n";
        
        // Test 2: Test Quotes with Company Context
        echo "=== Test 2: Quotes with Company Context ===\n";
        
        $quotesResponse = makeApiRequest('GET', '/api/quotes', [], $companyToken);
        echo "Quotes Response: " . json_encode($quotesResponse, JSON_PRETTY_PRINT) . "\n\n";
        
        // Test 3: Test Employees with Company Context
        echo "=== Test 3: Employees with Company Context ===\n";
        
        $employeesResponse = makeApiRequest('GET', '/api/employees', [], $companyToken);
        echo "Employees Response: " . json_encode($employeesResponse, JSON_PRETTY_PRINT) . "\n\n";
        
        // Test 4: Test Contacts with Company Context
        echo "=== Test 4: Contacts with Company Context ===\n";
        
        $contactsResponse = makeApiRequest('GET', '/api/contacts', [], $companyToken);
        echo "Contacts Response: " . json_encode($contactsResponse, JSON_PRETTY_PRINT) . "\n\n";
        
        // Test 5: Test Clients with Company Context
        echo "=== Test 5: Clients with Company Context ===\n";
        
        $clientsResponse = makeApiRequest('GET', '/api/clients', [], $companyToken);
        echo "Clients Response: " . json_encode($clientsResponse, JSON_PRETTY_PRINT) . "\n\n";
        
        // Test 6: Test Invoices with Company Context
        echo "=== Test 6: Invoices with Company Context ===\n";
        
        $invoicesResponse = makeApiRequest('GET', '/api/invoices', [], $companyToken);
        echo "Invoices Response: " . json_encode($invoicesResponse, JSON_PRETTY_PRINT) . "\n\n";
        
        // Test 7: Test Quote Statistics with Company Context
        echo "=== Test 7: Quote Statistics with Company Context ===\n";
        
        $quoteStatsResponse = makeApiRequest('GET', '/api/quotes/statistics', [], $companyToken);
        echo "Quote Statistics Response: " . json_encode($quoteStatsResponse, JSON_PRETTY_PRINT) . "\n\n";
        
        // Test 8: Test Invoice Statistics with Company Context
        echo "=== Test 8: Invoice Statistics with Company Context ===\n";
        
        $invoiceStatsResponse = makeApiRequest('GET', '/api/invoices/statistics', [], $companyToken);
        echo "Invoice Statistics Response: " . json_encode($invoiceStatsResponse, JSON_PRETTY_PRINT) . "\n\n";
        
        // Test 9: Test Creating Quote with Company Context
        echo "=== Test 9: Create Quote with Company Context ===\n";
        
        $quoteData = [
            'contact_id' => 1, // Assuming contact ID 1 exists
            'quote_date' => date('Y-m-d'),
            'expiration_date' => date('Y-m-d', strtotime('+30 days')),
            'quote_status' => 'Pending',
            'items' => [
                [
                    'item_description' => 'Test Quote Item',
                    'item_quantity' => 1,
                    'item_rate' => 500.00,
                    'item_gst_rate' => 10
                ]
            ],
            'notes' => 'Test quote created with company context'
        ];
        
        $createQuoteResponse = makeApiRequest('POST', '/api/quotes', $quoteData, $companyToken);
        echo "Create Quote Response: " . json_encode($createQuoteResponse, JSON_PRETTY_PRINT) . "\n\n";
        
        // Test 10: Test Creating Invoice with Company Context
        echo "=== Test 10: Create Invoice with Company Context ===\n";
        
        $invoiceData = [
            'sent_to' => 1, // Assuming contact ID 1 exists
            'invoice_date' => date('Y-m-d'),
            'invoice_due_date' => date('Y-m-d', strtotime('+30 days')),
            'invoice_status' => 'Draft',
            'invoice_payment_status' => 'Unpaid',
            'items' => [
                [
                    'item_description' => 'Test Invoice Item',
                    'item_quantity' => 1,
                    'item_rate' => 750.00,
                    'item_gst_rate' => 10
                ]
            ],
            'notes' => 'Test invoice created with company context'
        ];
        
        $createInvoiceResponse = makeApiRequest('POST', '/api/invoices', $invoiceData, $companyToken);
        echo "Create Invoice Response: " . json_encode($createInvoiceResponse, JSON_PRETTY_PRINT) . "\n\n";
        
        // Test 11: Test Company Context Verification
        echo "=== Test 11: Verify Company Context ===\n";
        
        $userResponse = makeApiRequest('GET', '/api/user', [], $companyToken);
        echo "User Response: " . json_encode($userResponse, JSON_PRETTY_PRINT) . "\n\n";
        
        // Test 12: Test Without Company Context (should fail)
        echo "=== Test 12: Test Without Company Context (should fail) ===\n";
        
        $noContextResponse = makeApiRequest('GET', '/api/quotes', [], $initialToken);
        echo "No Company Context Response: " . json_encode($noContextResponse, JSON_PRETTY_PRINT) . "\n\n";
        
    } else {
        echo "Failed to select company: " . $selectCompanyResponse['message'] . "\n";
    }
} else {
    echo "Failed to login: " . $loginResponse['message'] . "\n";
}

/**
 * Helper function to make API requests
 */
function makeApiRequest($method, $endpoint, $data = [], $token = null) {
    $url = 'http://localhost:8000' . $endpoint;
    
    $headers = [
        'Content-Type: application/json',
        'Accept: application/json'
    ];
    
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($method === 'POST' && !empty($data)) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return json_decode($response, true);
}

echo "=== Test Complete ===\n";
echo "Summary:\n";
echo "- All entities should be filtered by company context\n";
echo "- Creating entities should automatically set company_id\n";
echo "- Statistics should only show data for the selected company\n";
echo "- Requests without company context should return 403 error\n";
?>
