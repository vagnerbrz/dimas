<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});

use App\Http\Controllers\ProductController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\AddressController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\HumanConversationController;
use App\Http\Controllers\WhatsAppInteractivePreviewController;
use App\Http\Controllers\SalesReportController;

Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/reports/sales', [SalesReportController::class, 'index'])->name('reports.sales');
    Route::resource('products', ProductController::class);
    Route::resource('orders', OrderController::class);
    Route::get('orders/{order}/print', [OrderController::class, 'print'])->name('orders.print');
    Route::post('orders/{order}/accept', [OrderController::class, 'accept'])->name('orders.accept');
    Route::resource('customers', CustomerController::class);
    Route::get('human-conversations', [HumanConversationController::class, 'index'])->name('human-conversations.index');
    Route::get('human-conversations/{humanConversation}', [HumanConversationController::class, 'show'])->name('human-conversations.show');
    Route::post('human-conversations/{humanConversation}/assign', [HumanConversationController::class, 'assign'])->name('human-conversations.assign');
    Route::post('human-conversations/{humanConversation}/messages', [HumanConversationController::class, 'sendMessage'])->name('human-conversations.messages.store');
    Route::post('human-conversations/{humanConversation}/close', [HumanConversationController::class, 'close'])->name('human-conversations.close');
    Route::post('human-conversations/{humanConversation}/reopen', [HumanConversationController::class, 'reopen'])->name('human-conversations.reopen');

    // Gestão de Endereços do Cliente
    Route::get('customers/{customer}/address/create', [AddressController::class, 'create'])->name('customers.address.create');
    Route::post('customers/{customer}/address', [AddressController::class, 'store'])->name('customers.address.store');
    Route::put('addresses/{address}', [AddressController::class, 'update'])->name('addresses.update');
    Route::delete('addresses/{address}', [AddressController::class, 'destroy'])->name('addresses.destroy');
    Route::put('addresses/{address}/primary', [AddressController::class, 'setPrimary'])->name('addresses.primary');

    // Configurações do WhatsApp
    Route::get('/whatsapp-settings', function() { return view('whatsapp.settings'); })->name('whatsapp.settings');
    Route::get('/whatsapp-preview', [WhatsAppInteractivePreviewController::class, 'index'])->name('whatsapp.preview');
    Route::post('/whatsapp-preview/buttons', [WhatsAppInteractivePreviewController::class, 'sendButtons'])->name('whatsapp.preview.buttons');
    Route::post('/whatsapp-preview/boolean', [WhatsAppInteractivePreviewController::class, 'sendBooleanButtons'])->name('whatsapp.preview.boolean');
    Route::post('/whatsapp-preview/list', [WhatsAppInteractivePreviewController::class, 'sendList'])->name('whatsapp.preview.list');
});
