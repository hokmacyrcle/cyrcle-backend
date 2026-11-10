<?php

/**
 * Test Company Context Middleware
 * 
 * This test verifies that:
 * 1. When a user calls select-company/{company_id}, a new token is created with company context
 * 2. The SetCompanyContext middleware extracts the company_id from the token name
 * 3. Invoices are automatically filtered by the user's company context
 */

// Test 1: Select Company and Get Token
echo "=== Test 1: Select Company and Get Token ===\n";

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
        
        // Test 2: Get Invoices with Company Context
        echo "=== Test 2: Get Invoices with Company Context ===\n";
        
        $invoicesResponse = makeApiRequest('GET', '/api/invoices', [], $companyToken);
        echo "Invoices Response: " . json_encode($invoicesResponse, JSON_PRETTY_PRINT) . "\n\n";
        
        // Test 3: Create Invoice with Company Context
        echo "=== Test 3: Create Invoice with Company Context ===\n";
        
        $invoiceData = [
            'sent_to' => 1, // Assuming contact ID 1 exists
            'invoice_date' => date('Y-m-d'),
            'invoice_due_date' => date('Y-m-d', strtotime('+30 days')),
            'invoice_status' => 'Draft',
            'invoice_payment_status' => 'Unpaid',
            'items' => [
                [
                    'item_description' => 'Test Item',
                    'item_quantity' => 1,
                    'item_rate' => 100.00,
                    'item_gst_rate' => 10
                ]
            ],
            'notes' => 'Test invoice created with company context'
        ];
        
        $createInvoiceResponse = makeApiRequest('POST', '/api/invoices', $invoiceData, $companyToken);
        echo "Create Invoice Response: " . json_encode($createInvoiceResponse, JSON_PRETTY_PRINT) . "\n\n";
        
        // Test 4: Verify Company Context in Token
        echo "=== Test 4: Verify Company Context in Token ===\n";
        
        $userResponse = makeApiRequest('GET', '/api/user', [], $companyToken);
        echo "User Response: " . json_encode($userResponse, JSON_PRETTY_PRINT) . "\n\n";
        
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
?>
