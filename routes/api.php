<?php

use App\Http\Controllers\N8nAssistantController;
use App\Http\Controllers\N8nCustomerController;
use App\Http\Controllers\WhatsAppController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/whatsapp/webhook', [WhatsAppController::class, 'webhook'])
    ->withoutMiddleware('throttle:api')
    ->name('api.whatsapp.webhook');

Route::prefix('n8n')->group(function () {
    Route::get('/menu', [N8nAssistantController::class, 'menu'])->name('api.n8n.menu');
    Route::post('/orders', [N8nAssistantController::class, 'createOrder'])->name('api.n8n.orders');
    Route::post('/suspend', [N8nAssistantController::class, 'suspend'])->name('api.n8n.suspend');
    Route::get('/whatsapp-config', [N8nAssistantController::class, 'whatsappConfig'])->name('api.n8n.whatsapp-config');
    Route::post('/messages/text', [N8nAssistantController::class, 'sendText'])->name('api.n8n.messages.text');
    Route::get('/customers', [N8nCustomerController::class, 'index'])->name('api.n8n.customers.index');
    Route::post('/customers', [N8nCustomerController::class, 'store'])->name('api.n8n.customers.store');
    Route::get('/customers/{customer}', [N8nCustomerController::class, 'show'])->name('api.n8n.customers.show');
    Route::patch('/customers/{customer}', [N8nCustomerController::class, 'update'])->name('api.n8n.customers.update');
    Route::get('/customers/{customer}/addresses', [N8nCustomerController::class, 'listAddresses'])->name('api.n8n.customers.addresses.index');
    Route::post('/customers/{customer}/addresses', [N8nCustomerController::class, 'upsertAddress'])->name('api.n8n.customers.addresses.store');
});

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});
