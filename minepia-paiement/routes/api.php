<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Http\Controllers\Api\CampostApiController;

// 1. Authentification Machine
Route::post('/account/auth', [CampostApiController::class, 'auth']);

// 4. Webhook de Notification
Route::post('/campost/notify-payment', [CampostApiController::class, 'notifyPayment']);

// Endpoints sécurisés
Route::middleware('auth:sanctum')->group(function () {
    // 2. Infos Utilisateur
    Route::get('/account/me', [CampostApiController::class, 'me']);
    // 3. Recherche du Candidat
    Route::get('/apprenants/matricule/{matricule}', [CampostApiController::class, 'getCandidate']);
});
