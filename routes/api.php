<?php

use App\Http\Controllers\EuroCross\Api\AccountController;
use App\Http\Controllers\EuroCross\Api\ApiKeyController;
use App\Http\Controllers\EuroCross\Api\TranslationController;
use App\Http\Controllers\EuroCross\Api\VatController;
use App\Http\Controllers\EuroCross\ChatbotController;
use App\Http\Controllers\EuroCross\PaymentController;
use App\Http\Controllers\EuroCross\SettingsController;
use App\Http\Controllers\EuroCross\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| EuroCross AI API Routes
|--------------------------------------------------------------------------
|
| These routes are stateless and are served under the /eurocross prefix.
| Protected endpoints use the hashed API key issued by
| `php artisan app:issue-euro-cross-api-key`.
|
*/

Route::get('/health', function () {
    $hasAiProvider = (bool) config('services.openai.key') || (bool) config('services.deepl.key');

    return response()->json([
        'status' => 'ok',
        'version' => '1.0.0',
        'demo_mode' => ! $hasAiProvider,
    ]);
})->name('eurocross.health');

Route::prefix('webhook')->middleware('throttle:60,1')->group(function (): void {
    Route::post('/gumroad', [WebhookController::class, 'gumroad'])
        ->name('eurocross.webhook.gumroad');
    Route::post('/lemon-squeezy', [WebhookController::class, 'lemonSqueezy'])
        ->name('eurocross.webhook.lemon-squeezy');
    Route::post('/stripe', [WebhookController::class, 'stripe'])
        ->name('eurocross.webhook.stripe');
});

Route::middleware('eurocross.api')->group(function (): void {
    Route::get('/settings', [SettingsController::class, 'index'])
        ->name('eurocross.settings');
    Route::put('/settings', [SettingsController::class, 'update'])
        ->name('eurocross.settings.update');

    Route::post('/trial/start', [SettingsController::class, 'startTrial'])
        ->name('eurocross.trial.start');

    Route::get('/api-keys', [ApiKeyController::class, 'index'])
        ->name('eurocross.api-keys.index');
    Route::post('/api-keys', [ApiKeyController::class, 'store'])
        ->name('eurocross.api-keys.store');
    Route::delete('/api-keys/{apiKey}', [ApiKeyController::class, 'destroy'])
        ->name('eurocross.api-keys.destroy');

    // Backwards-compatible single-key endpoint. Keys are stored encrypted.
    Route::post('/api-key', [ApiKeyController::class, 'store'])
        ->name('eurocross.api-key.update');

    Route::get('/account/summary', [AccountController::class, 'summary'])
        ->name('eurocross.account.summary');
    Route::get('/account/analytics', [AccountController::class, 'analytics'])
        ->name('eurocross.account.analytics');
    Route::get('/accounts', [AccountController::class, 'index'])
        ->name('eurocross.accounts.index');

    Route::get('/languages', [TranslationController::class, 'languages'])
        ->name('eurocross.languages');
    Route::post('/translate', [TranslationController::class, 'translate'])
        ->name('eurocross.translate');
    Route::post('/translate/batch', [TranslationController::class, 'translateBatch'])
        ->name('eurocross.translate.batch');

    Route::get('/vat/config', [VatController::class, 'config'])
        ->name('eurocross.vat.config');
    Route::put('/vat/config', [VatController::class, 'updateConfig'])
        ->name('eurocross.vat.update-config');
    Route::get('/vat/rates', [VatController::class, 'getRates'])
        ->name('eurocross.vat.rates');
    Route::post('/vat/validate-vies', [VatController::class, 'validateVies'])
        ->name('eurocross.vat.validate-vies');
    Route::post('/vat/check', [VatController::class, 'checkVat'])
        ->name('eurocross.vat.check');
    Route::post('/vat/report', [VatController::class, 'generateReport'])
        ->name('eurocross.vat.report');
    Route::get('/vat/report/download', [VatController::class, 'downloadReport'])
        ->name('eurocross.report.download');

    Route::get('/chatbot/status', [ChatbotController::class, 'status'])
        ->name('eurocross.chatbot.status');
    Route::get('/chatbot/history', [ChatbotController::class, 'history'])
        ->name('eurocross.chatbot.history');
    Route::post('/chatbot/message', [ChatbotController::class, 'message'])
        ->name('eurocross.chatbot.message');

    Route::post('/checkout', [PaymentController::class, 'checkout'])
        ->name('eurocross.checkout');
    Route::get('/checkout/{plan}', [PaymentController::class, 'checkoutRedirect'])
        ->name('eurocross.checkout.redirect');
    Route::get('/subscription/{subscriptionId}', [PaymentController::class, 'subscription'])
        ->name('eurocross.subscription');
    Route::post('/subscription/change-plan', [PaymentController::class, 'changePlan'])
        ->name('eurocross.subscription.change-plan');
    Route::post('/subscription/cancel', [PaymentController::class, 'cancel'])
        ->name('eurocross.subscription.cancel');
    Route::get('/portal', [PaymentController::class, 'portal'])
        ->name('eurocross.portal');
});
