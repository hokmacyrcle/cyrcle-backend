<?php

/**
 * Test Authentication Error Scenarios
 * 
 * This script demonstrates the different error responses when:
 * 1. No Authorization header is provided
 * 2. Authorization header doesn't start with "Bearer "
 * 3. Token is empty
 * 4. Token is invalid or expired
 */

$baseUrl = 'http://127.0.0.1:8000';

echo "🔐 Testing Authentication Error Scenarios\n";
echo "==========================================\n\n";

// Test 1: No Authorization header
echo "1️⃣ Testing: No Authorization header\n";
echo "Endpoint: GET /api/user\n";
echo "Expected: Access token is missing\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/user');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Response:\n";
echo json_encode(json_decode($response), JSON_PRETTY_PRINT);
echo "\n\n";

// Test 2: Invalid Authorization header format
echo "2️⃣ Testing: Invalid Authorization header format\n";
echo "Endpoint: GET /api/user\n";
echo "Header: Authorization: InvalidToken\n";
echo "Expected: Invalid token format\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/user');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: InvalidToken'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Response:\n";
echo json_encode(json_decode($response), JSON_PRETTY_PRINT);
echo "\n\n";

// Test 3: Empty token
echo "3️⃣ Testing: Empty token\n";
echo "Endpoint: GET /api/user\n";
echo "Header: Authorization: Bearer \n";
echo "Expected: Token is empty\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/user');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer '
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Response:\n";
echo json_encode(json_decode($response), JSON_PRETTY_PRINT);
echo "\n\n";

// Test 4: Invalid/expired token
echo "4️⃣ Testing: Invalid/expired token\n";
echo "Endpoint: GET /api/user\n";
echo "Header: Authorization: Bearer invalid_token_here\n";
echo "Expected: Invalid or expired token\n\n";

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/user');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'Authorization: Bearer invalid_token_here'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "Response:\n";
echo json_encode(json_decode($response), JSON_PRETTY_PRINT);
echo "\n\n";

echo "✅ Authentication error testing completed!\n";
echo "All scenarios should return proper error messages with status_code: 401\n";
?> 