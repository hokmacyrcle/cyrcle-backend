<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Employee;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\InvoiceTransaction;
use App\Models\PaymentTerm;
use App\Models\TaxRate;
use App\Observers\CompanyObserver;
use App\Observers\ContactObserver;
use App\Observers\EmployeeObserver;
use App\Observers\InvoiceObserver;
use App\Observers\InvoiceItemObserver;
use App\Observers\InvoiceTransactionObserver;
use App\Observers\PaymentTermObserver;
use App\Observers\TaxRateObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Register model observers for automatic audit logging
        Company::observe(CompanyObserver::class);
        Contact::observe(ContactObserver::class);
        Employee::observe(EmployeeObserver::class);
        Invoice::observe(InvoiceObserver::class);
        InvoiceItem::observe(InvoiceItemObserver::class);
        InvoiceTransaction::observe(InvoiceTransactionObserver::class);
        PaymentTerm::observe(PaymentTermObserver::class);
        TaxRate::observe(TaxRateObserver::class);
    }
}
