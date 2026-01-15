<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\FacebookWebhookController;

Route::post('/shopify/webhook', \App\Http\Controllers\ShopifyWebhookController::class)->name('shopify.webhook');

Route::get('/facebook/webhook', [FacebookWebhookController::class, 'verify']);
Route::post('/facebook/webhook', [FacebookWebhookController::class, 'handle']);
