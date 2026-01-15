<?php

namespace Tests\Feature\Clients;

use Tests\TestCase;
use App\Clients\Shopify;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ShopifyTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run(): void
    {
        $this->markTestSkipped('For testing only ...');
        /** @var Shopify */
        $client = \app(Shopify::class);

        \dd('done');
    }
}
