<?php

use App\Http\Controllers\SatuSehatController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SatuSehatTesting;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::get('/generateSatuSehatUrl', [SatuSehatTesting::class, 'index'])->name('satusehatapi.generateUrl');
Route::get('/satusehat/bundle/visit/{visitId}', [SatuSehatController::class, 'bundle'])->name('satusehatapi.bundle');
Route::get('/testing', [SatuSehatController::class, 'testGetRacik']);
