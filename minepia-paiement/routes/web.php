
<?php
use App\Http\Controllers\ApprenantController;
use Illuminate\Support\Facades\Route;

// 1. Affichage de la page d'accueil (GET)
Route::get('/', function () {
    return view('recherche');
})->name('apprenant.recherche');

// 2. Traitement du formulaire (C'est ici qu'on change pour accepter le POST sur "/")
Route::post('/', [ApprenantController::class, 'rechercher'])->name('apprenant.traiter');

// 3. L'écran pour choisir la tranche
Route::get('/paiement/choix', [ApprenantController::class, 'choisirTranche'])->name('paiement.choix');
Route::get('/paiement/bordereau/{matricule}/{trancheId}', [ApprenantController::class, 'telechargerBordereau'])->name('paiement.bordereau');

// CAMPOST Portal Web Routes
use App\Http\Controllers\Web\CampostPortalController;
use App\Http\Controllers\PaiementVerificationController;

Route::get('/campost/portal', [CampostPortalController::class, 'index'])->name('campost.portal');
Route::post('/campost/portal/request-otp', [CampostPortalController::class, 'requestOtp'])->name('campost.portal.request-otp');
Route::post('/campost/portal/confirm-otp', [CampostPortalController::class, 'confirmOtp'])->name('campost.portal.confirm-otp');
Route::post('/campost/portal/login', [CampostPortalController::class, 'login'])->name('campost.portal.login');

// 4. Vérification et Validation par Numéro de Reçu CamPost
Route::post('/paiement/verifier-recu', [PaiementVerificationController::class, 'verifierRecuApprenant'])->name('paiement.verifier-recu');
