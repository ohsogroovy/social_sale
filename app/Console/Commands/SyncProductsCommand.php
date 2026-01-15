<?php

namespace App\Console\Commands;

use App\Clients\Shopify;
use App\Actions\SyncProducts;
use Illuminate\Console\Command;

class SyncProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sync-products';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync products from Shopify to local database';

    /**
     * Execute the console command.
     */
    public function handle(Shopify $shopify, SyncProducts $syncProducts): void
    {
        $this->output->title('Syncing products from Shopify to local database');
        $progressBar = $this->output->createProgressBar($shopify->productCount());
        $syncProducts->execute($progressBar);
        $this->output->newLine();
    }
}
