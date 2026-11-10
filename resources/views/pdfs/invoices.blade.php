<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoices Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 2px solid #333;
            padding-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            color: #333;
            font-size: 24px;
        }
        .header p {
            margin: 5px 0;
            color: #666;
        }
        .summary-section {
            margin-bottom: 30px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .summary-grid {
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        .summary-item {
            flex: 1;
            min-width: 200px;
            margin: 10px;
            padding: 10px;
            background-color: white;
            border-radius: 3px;
            border: 1px solid #dee2e6;
        }
        .summary-item h3 {
            margin: 0 0 10px 0;
            color: #495057;
            font-size: 14px;
        }
        .summary-item .value {
            font-size: 18px;
            font-weight: bold;
            color: #007bff;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #dee2e6;
            padding: 8px;
            text-align: left;
            font-size: 11px;
        }
        th {
            background-color: #f8f9fa;
            font-weight: bold;
            color: #495057;
        }
        .status-due {
            color: #28a745;
            font-weight: bold;
        }
        .status-overdue {
            color: #dc3545;
            font-weight: bold;
        }
        .status-future {
            color: #ffc107;
            font-weight: bold;
        }
        .amount {
            text-align: right;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            text-align: center;
            font-size: 10px;
            color: #6c757d;
        }
        .page-break {
            page-break-before: always;
        }
        @media print {
            body {
                margin: 0;
                padding: 15px;
            }
            .page-break {
                page-break-before: always;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Invoices Report</h1>
        <p>Generated on: {{ $generated_at }}</p>
        @if(isset($filters) && count($filters) > 0)
            <p>Filters applied: {{ json_encode($filters) }}</p>
        @endif
    </div>

    @if(isset($summary))
    <div class="summary-section">
        <h2>Summary</h2>
        <div class="summary-grid">
            <div class="summary-item">
                <h3>Total Invoices</h3>
                <div class="value">{{ $summary['total_count'] }}</div>
            </div>
            <div class="summary-item">
                <h3>Total Amount</h3>
                <div class="value">${{ number_format($summary['total_amount'], 2) }}</div>
            </div>
            <div class="summary-item">
                <h3>Due Today</h3>
                <div class="value">{{ $summary['status_distribution']['due_today'] }}</div>
            </div>
            <div class="summary-item">
                <h3>Overdue</h3>
                <div class="value">{{ $summary['status_distribution']['overdue'] }}</div>
            </div>
        </div>
        
        <div class="summary-grid">
            <div class="summary-item">
                <h3>Payment Status - Paid</h3>
                <div class="value">{{ $summary['payment_distribution']['paid'] }}</div>
            </div>
            <div class="summary-item">
                <h3>Payment Status - Unpaid</h3>
                <div class="value">{{ $summary['payment_distribution']['unpaid'] }}</div>
            </div>
            <div class="summary-item">
                <h3>Payment Status - Partially Paid</h3>
                <div class="value">{{ $summary['payment_distribution']['partially_paid'] }}</div>
            </div>
        </div>
    </div>
    @endif

    @if(isset($invoices) && count($invoices) > 0)
    <table>
        <thead>
            <tr>
                <th>Invoice ID</th>
                <th>Date</th>
                <th>Customer</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Payment Status</th>
                <th>Due Date</th>
                <th>Days Info</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoices as $invoice)
            <tr>
                <td>{{ $invoice['id'] }}</td>
                <td>{{ $invoice['invoice_date'] }}</td>
                <td>{{ $invoice['customer_name'] }}</td>
                <td class="amount">${{ number_format($invoice['total_amount'], 2) }}</td>
                <td>
                    @if($invoice['is_overdue'])
                        <span class="status-overdue">{{ $invoice['status'] }}</span>
                    @elseif(strpos($invoice['status'], 'Due in') === 0)
                        <span class="status-future">{{ $invoice['status'] }}</span>
                    @else
                        <span class="status-due">{{ $invoice['status'] }}</span>
                    @endif
                </td>
                <td>{{ $invoice['payment_status'] }}</td>
                <td>{{ $invoice['due_date'] }}</td>
                <td>{{ $invoice['days_info'] ?? 'N/A' }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>
    @else
    <p>No invoices found matching the specified criteria.</p>
    @endif

    <div class="footer">
        <p>Report generated by Your Application</p>
        <p>Page 1</p>
    </div>
</body>
</html>
