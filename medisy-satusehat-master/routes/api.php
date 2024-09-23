<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\SatuSehatTesting;
use App\Http\Controllers\SatuSehatController;

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

Route::post('/generateSatuSehatUrl', [SatuSehatController::class, 'getKycLink'])->name('satusehatapi.generateUrl');
Route::get('/satusehat/bundle/visit/{visitId}', [SatuSehatController::class, 'bundle'])->name('satusehatapi.bundle');
Route::post('/satusehat/bundleBatch/send', [SatuSehatController::class, 'bundleBatch'])->name('satusehatapi.bundleBatch');

// Route::get('/testing', [SatuSehatController::class, 'testGetRacik']);
Route::get('/perusahaan', function () {
    // dd(DB::table('kk_perusahaan')->first());
});
