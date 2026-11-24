<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;

class FirmApprove extends Command
{
    protected $signature = 'firm:approve {company_id? : Company id to approve} {--reject : Reject instead of approve}';
    protected $description = 'List pending firm sign-ups, or approve/reject one';

    public function handle(): int
    {
        $id = $this->argument('company_id');

        if (!$id) {
            $rows = Company::where('company_type', 'Subscriber')->where('status', 'pending')
                ->get(['company_id', 'company_name', 'company_abn', 'state', 'client_count_band', 'created_at']);
            $this->table(['id', 'firm', 'ABN', 'state', 'clients', 'requested'], $rows->map(fn ($c) => $c->only(['company_id', 'company_name', 'company_abn', 'state', 'client_count_band', 'created_at']))->all());
            return self::SUCCESS;
        }

        $company = Company::where('company_id', $id)->where('company_type', 'Subscriber')->first();
        if (!$company) {
            $this->error('No firm with that id.');
            return self::FAILURE;
        }

        $company->forceFill($this->option('reject')
            ? ['status' => 'rejected']
            : ['status' => 'active', 'approved_at' => now()])->save();

        $this->info("{$company->company_name}: {$company->status}");
        return self::SUCCESS;
    }
}
