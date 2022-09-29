<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ImportController;
/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', [ImportController::class, 'index'])->name('home');
Route::get('/import-ouvrage', [ImportController::class, 'importOuvrage'])->name('import.ouvrage');
Route::get('/import-customer', [ImportController::class, 'importCustomer'])->name('import.customer');
Route::get('/import-detailing-item', [ImportController::class, 'importDetailingItem'])->name('import.detailing.item');
