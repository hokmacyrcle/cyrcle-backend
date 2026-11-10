<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\AttachmentSeeder;

class SeedAttachments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'seed:attachments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Seed sample attachments for quotes, invoices, and transactions';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting to seed attachments...');
        
        $seeder = new AttachmentSeeder();
        $seeder->setCommand($this);
        $seeder->run();
        
        $this->info('Attachments seeding completed!');
        
        return 0;
    }
}
