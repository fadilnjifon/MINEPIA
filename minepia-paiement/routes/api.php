<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

use App\Http\Controllers\Api\CampostApiController;
use App\Http\Controllers\PaiementVerificationController;

// 1. Authentification Machine
Route::post('/account/auth', [CampostApiController::class, 'auth']);

// 4. Webhook de Notification (Legacy / Fallback)
Route::post('/campost/notify-payment', [CampostApiController::class, 'notifyPayment']);

// Endpoints sécurisés
Route::middleware('auth:sanctum')->group(function () {
    // 2. Infos Utilisateur
    Route::get('/account/me', [CampostApiController::class, 'me']);
    // 3. Recherche du Candidat
    Route::get('/apprenants/matricule/{matricule}', [CampostApiController::class, 'getCandidate']);
    // 4. Enregistrement d'un Paiement au Guichet par l'Agent CamPost
    Route::post('/campost/paiement', [CampostApiController::class, 'enregistrerPaiementGuichet']);
    // 5. Vérification d'un Reçu CamPost par les systèmes tiers (Sanctum Bearer)
    Route::get('/recu/verifier/{numero_recu}', [PaiementVerificationController::class, 'verifierRecuApi']);
    Route::get('/campost/verifier-recu/{numero_recu}', [PaiementVerificationController::class, 'verifierRecuApi']);
});
