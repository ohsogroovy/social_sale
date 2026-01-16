<?php

use App\Clients\Shopify;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\CommentsController;
use App\Http\Controllers\ImportCsvController;
use App\Http\Controllers\FacebookOAuthController;
use App\Http\Controllers\FacebookStreamController;
use App\Http\Controllers\ShopifyWebhookController;

Route::get('/', function () {
    $user = \App\Models\User::first();
    $userExists = $user !== null;

    return view(
        'welcome',
        compact('userExists')
    );
});

Route::get('/privacy-policy', function () {
    return view('privacy-policy');
});

Route::get('/terms-of-service', function () {
    return view('terms-of-service');
});

Route::get('/authorized', function () {
    return view('authorized');
});

Route::get('/auth/facebook', [FacebookOAuthController::class, 'begin']);
Route::get('/auth/facebook/callback', [FacebookOAuthController::class, 'callback']);
Route::get('/register-webhook', [ShopifyWebhookController::class, 'subscribeWebhooks']);
Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::get('/dashboard', [CommentsController::class, 'index'])->name('dashboard');

    Route::get('/facebook/subscribe-webhooks', [FacebookOAuthController::class, 'subscribeWebhooks'])->name('facebook.subscribeWebhooks');

    Route::get('/current-live-stream', [FacebookStreamController::class, 'getLiveStream'])->name('current-live-streams');
    Route::get('/latest-comments', [FacebookStreamController::class, 'getLatestComments'])->name('latest-comments');
    Route::get('/sync-manual-live-stream', [FacebookStreamController::class, 'syncManualLiveStream'])->name('sync-manual-live-stream');
    Route::get('/show-post/comment/{post}', [FacebookStreamController::class, 'showPostWithComments']);
    Route::post('/post-comment/{post}', [FacebookStreamController::class, 'postComment']);

    Route::get('/past-streams', [FacebookStreamController::class, 'getPastStreams'])->name('past-streams');
    Route::get('/search-comments/{post}', [FacebookStreamController::class, 'searchComments']);

    Route::get('/search-product', [ProductController::class, 'searchProducts']);
    Route::get('/generated-tags', [ProductController::class, 'generatedTags'])->name('generated-tags');
    Route::delete('/generated-tags', [ProductController::class, 'deleteTags']);
    Route::delete('/generated-tags/all', [ProductController::class, 'deleteAllTags'])->name('delete-all-tags');

    Route::get('/import-csv/{post}', [ImportCsvController::class, 'importCsv']);

    Route::post('auto-trigger', function (Request $request) {
        $request->validate([
            'auto_trigger' => ['required', 'boolean'],
        ]);

        /**
         * @var \App\Models\User $user
         */
        $user = Auth::user();
        $user->update([
            'auto_trigger' => $request->boolean('auto_trigger'),
        ]);

        return back()->with('success', 'Auto trigger setting updated successfully.');
    });
    Route::get('/user', function (Request $request) {
        return response()->json([
            'user' => $request->user(),
        ]);
    });
});

Route::get('/shopify/subscribe-webhooks', function (Shopify $shopify) {
    $registeredWebhooks = $shopify->webhookSubscriptions();
    foreach (\App\Http\Controllers\ShopifyWebhookController::getWebhooks() as $webhook) {
        if (\array_search($webhook['name'], \array_column($registeredWebhooks, 'topic')) === false) {
            $shopify->createWebhookSubscription($webhook['name'], $webhook['callback']);
        }
    }

    return 'done';
});

require __DIR__.'/auth.php';

// Facebook Webhook Endpoint
use App\Http\Controllers\FacebookWebhookController;
Route::match(['get', 'post'], '/webhook/facebook', [FacebookWebhookController::class, 'handle']);
