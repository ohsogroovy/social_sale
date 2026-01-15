<?php

namespace Tests\Feature\Clients;

use Tests\TestCase;
use App\Clients\SmartCart;
use Illuminate\Http\Client\RequestException;
use Illuminate\Foundation\Testing\RefreshDatabase;

class SmartCartTest extends TestCase
{
    use RefreshDatabase;

    public function testDryRun(): void
    {
        $this->markTestIncomplete('For testing only.');
        \dd(\range(1, 2));
        $smartCart = new SmartCart;
        try {
            $res = $smartCart->reserveProduct(1, 1);
        } catch (RequestException $e) {
            if ($e->response->json('errors.facebook_user_id', null)) {
                \dd('facebook id is invalid, try another one.');
            }
        }
        \dd($res);
    }
}
