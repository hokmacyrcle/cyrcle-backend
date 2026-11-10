<?php

require_once 'vendor/autoload.php';

$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\User;
use App\Models\Company;

echo "=== Select Company API Test ===\n\n";

// Test: Show available companies for user ID 6
echo "1. Available companies for User ID 6:\n";
$companies = Company::where('user_id', 6)
    ->select('company_id', 'company_name', 'company_type')
    ->get();

foreach ($companies->take(5) as $company) {
    echo "   Company ID: {$company->company_id} - {$company->company_name} ({$company->company_type})\n";
}
echo "   ... and " . ($companies->count() - 5) . " more companies\n\n";

// Test: Show current user state
echo "2. Current User State:\n";
$user = User::find(6);
if ($user) {
    echo "   User ID: {$user->id}\n";
    echo "   Email: {$user->email}\n";
    echo "   Current Company ID: " . ($user->company_id ?? 'Not set') . "\n";
} else {
    echo "   User not found\n";
}

echo "\n=== New Select Company API ===\n\n";

echo "Endpoint: POST /api/select-company\n";
echo "Authentication: Required (Bearer token)\n";
echo "Request Body:\n";
echo "{\n";
echo "  \"company_id\": 1\n";
echo "}\n\n";

echo "Response Format:\n";
echo "{\n";
echo "  \"status\": true,\n";
echo "  \"status_code\": 200,\n";
echo "  \"message\": \"Company selected successfully\",\n";
echo "  \"data\": {\n";
echo "    \"user\": {\n";
echo "      \"id\": 6,\n";
echo "      \"email\": \"venkat@gmail.com\",\n";
echo "      \"company_id\": 1,\n";
echo "      ...\n";
echo "    },\n";
echo "    \"selected_company\": {\n";
echo "      \"company_id\": 1,\n";
echo "      \"company_name\": \"TechCorp Solutions Pty Ltd\",\n";
echo "      \"company_type\": \"Subscriber\",\n";
echo "      \"company_abn\": \"12345678901\",\n";
echo "      \"company_acn\": \"123456789\"\n";
echo "    },\n";
echo "    \"access_token\": \"new_token_with_company_context\",\n";
echo "    \"token_type\": \"Bearer\",\n";
echo "    \"company_context\": true\n";
echo "  }\n";
echo "}\n\n";

echo "=== Workflow ===\n";
echo "1. User logs in → Gets list of all companies\n";
echo "2. User selects a company → POST /api/select-company\n";
echo "3. System validates company access\n";
echo "4. System revokes old token\n";
echo "5. System creates new token with company context\n";
echo "6. System updates user's company_id\n";
echo "7. User gets new token for Angular app\n\n";

echo "=== Security Features ===\n";
echo "✅ Validates company_id exists in database\n";
echo "✅ Checks user has access to selected company\n";
echo "✅ Revokes old token for security\n";
echo "✅ Creates new token with company context\n";
echo "✅ Updates user's company_id for future requests\n";
echo "✅ Returns company details with new token\n";
