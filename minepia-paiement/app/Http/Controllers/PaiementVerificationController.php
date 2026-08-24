<?php

namespace App\Http\Controllers;

use App\Models\PaiementRecu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class PaiementVerificationController extends Controller
{
    /**
     * Authentification dynamique auprès de l'API SYGEAP/CamPost pour obtenir un Bearer Token
     */
    private function getSygeapToken(): ?string
    {
        $baseUrl = rtrim(config('services.sygeap.url') ?: (env('SYGEAP_BASE_URL') ?: env('SYGEAP_API_URL')), '/');
        $username = config('services.sygeap.username') ?: env('SYGEAP_USERNAME');
        $password = config('services.sygeap.password') ?: env('SYGEAP_PASSWORD');

        if (!$baseUrl || !$username || !$password) {
            Log::error('SYGEAP/CamPost API: Identifiants manquants dans le fichier .env (SYGEAP_BASE_URL, SYGEAP_USERNAME, SYGEAP_PASSWORD)');
            return null;
        }

        try {
            $response = Http::withoutVerifying()->timeout(10)->post("{$baseUrl}/api/account/auth", [
                'username' => $username,
                'password' => $password,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['accessToken'] ?? $data['access_token'] ?? $data['token'] ?? null;
            }

            Log::error("SYGEAP Authentification Token échouée: Status " . $response->status() . " - " . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error("SYGEAP Exception Authentification: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 1. ROUTE WEB (Pour l'Apprenant) : Vérification du reçu saisi et validation de la scolarité
     * POST /paiement/verifier-recu
     */
    public function verifierRecuApprenant(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'matricule'   => 'required|string',
            'numero_recu' => 'required|string|min:3|max:100',
            'tranche_id'  => 'nullable|string',
        ], [
            'matricule.required'   => 'Le matricule de l\'apprenant est obligatoire.',
            'numero_recu.required' => 'Veuillez saisir le numéro du reçu délivré par CamPost.',
            'numero_recu.min'      => 'Le numéro de reçu semble incomplet.',
        ]);

        if ($validator->fails()) {
            return back()->withErrors($validator)->withInput();
        }

        $matricule   = trim($request->input('matricule'));
        $numeroRecu  = trim($request->input('numero_recu'));
        $trancheId   = $request->input('tranche_id');

        Log::info("Vérification Reçu Apprenant: Matricule [{$matricule}], Reçu [{$numeroRecu}], Tranche [{$trancheId}]");

        // 1. Récupération du Bearer Token
        $token = $this->getSygeapToken();
        if (!$token) {
            return back()
                ->withErrors(['erreur_recu' => "Échec de connexion au serveur de vérification SYGEAP/CamPost. Veuillez réessayer."])
                ->withInput();
        }

        $baseUrl = rtrim(config('services.sygeap.url') ?: (env('SYGEAP_BASE_URL') ?: env('SYGEAP_API_URL')), '/');

        try {
            // 2. Appel de l'endpoint de vérification CamPost/SYGEAP
            // On tente l'endpoint principal GET /api/campost/verifier-recu/{numero_recu}
            $urlVerification = "{$baseUrl}/api/campost/verifier-recu/" . urlencode($numeroRecu);

            $response = Http::withoutVerifying()
                ->timeout(15)
                ->withToken($token)
                ->get($urlVerification);

            // Fallback éventuel sur /api/recu/verifier/{numero_recu} si le premier endpoint renvoie 404
            if ($response->status() === 404) {
                $urlFallback = "{$baseUrl}/api/recu/verifier/" . urlencode($numeroRecu);
                $response = Http::withoutVerifying()
                    ->timeout(15)
                    ->withToken($token)
                    ->get($urlFallback);
            }

            Log::info("Réponse Vérification Reçu [{$numeroRecu}]: Status HTTP " . $response->status() . " Body: " . $response->body());

            if (!$response->successful()) {
                return back()
                    ->withErrors(['erreur_recu' => "Numéro de reçu invalide ou paiement non trouvé auprès de CamPost."])
                    ->withInput();
            }

            $data = $response->json();
            $recuData = $data['data'] ?? $data;

            // 3. Analyse de la validité du reçu
            $statut  = strtoupper($recuData['statut'] ?? $recuData['status'] ?? ($data['status'] ?? ''));
            $valide  = (bool) ($recuData['valide'] ?? $recuData['valid'] ?? $data['valide'] ?? ($statut === 'PAID' || $statut === 'SUCCESS' || $statut === 'VALIDE'));
            $montant = $recuData['montant'] ?? $recuData['amount'] ?? 0;
            $datePaiement = $recuData['datePaiement'] ?? $recuData['date_paiement'] ?? now()->toDateTimeString();
            $refCampost   = $recuData['reference'] ?? $recuData['transactionId'] ?? $numeroRecu;

            if (!$valide && $statut !== 'PAID' && $statut !== 'SUCCESS') {
                return back()
                    ->withErrors(['erreur_recu' => "Le reçu {$numeroRecu} n'est pas au statut 'PAID' (Statut actuel: " . ($statut ?: 'INCONNU') . ")."])
                    ->withInput();
            }

            // 4. Enregistrement / Mise à jour en Base de Données locale
            $paiement = PaiementRecu::updateOrCreate(
                ['numero_recu' => $numeroRecu],
                [
                    'matricule'         => $matricule,
                    'tranche_id'        => $trancheId,
                    'montant'           => $montant,
                    'statut'            => 'PAID',
                    'date_paiement'     => $datePaiement,
                    'reference_campost' => $refCampost,
                    'operateur'         => 'CAMPOST',
                    'metadata'          => $recuData,
                ]
            );

            // 5. Notification de synchronisation vers SYGEAP si nécessaire
            try {
                Http::withoutVerifying()
                    ->timeout(10)
                    ->withToken($token)
                    ->post("{$baseUrl}/api/campost/notify-payment", [
                        'reference'     => $numeroRecu,
                        'matricule'     => $matricule,
                        'tranche_id'    => $trancheId,
                        'transactionId' => $refCampost,
                        'status'        => 'PAID',
                        'montant'       => $montant,
                        'date_paiement' => now()->toIso8601String(),
                    ]);
            } catch (\Exception $syncEx) {
                Log::warning("Notification SYGEAP post-validation ignorée ou échouée: " . $syncEx->getMessage());
            }

            // 6. Succès et redirection
            Session::flash('paiement_valide', true);
            Session::flash('numero_recu_valide', $numeroRecu);
            Session::flash('montant_valide', $montant);

            return redirect()
                ->route('apprenant.traiter', ['code_apprenant' => $matricule])
                ->with('success', "Paiement validé avec succès ! Votre reçu CamPost N° {$numeroRecu} a été authentifié et votre scolarité est mise à jour.");

        } catch (\Exception $e) {
            Log::error("Exception vérification reçu [{$numeroRecu}]: " . $e->getMessage());
            return back()
                ->withErrors(['erreur_recu' => "Une erreur de communication est survenue lors de la vérification du reçu. Veuillez réessayer."])
                ->withInput();
        }
    }

    /**
     * 2. ROUTE API (Si nous sommes le serveur qui fournit l'endpoint de vérification à CamPost / SYGEAP)
     * GET /api/recu/verifier/{numero_recu}
     */
    public function verifierRecuApi(Request $request, $numero_recu)
    {
        $numeroRecu = trim($numero_recu);

        if (empty($numeroRecu)) {
            return response()->json([
                'success' => false,
                'message' => 'Le numéro de reçu est requis'
            ], 400);
        }

        // Recherche du reçu en BDD
        $paiement = PaiementRecu::where('numero_recu', $numeroRecu)->first();

        if (!$paiement) {
            return response()->json([
                'success' => false,
                'message' => 'Numéro de reçu introuvable ou invalide',
            ], 404);
        }

        return response()->json([
            'success'      => true,
            'numeroRecu'   => $paiement->numero_recu,
            'matricule'    => $paiement->matricule,
            'trancheId'    => $paiement->tranche_id,
            'montant'      => (float) $paiement->montant,
            'statut'       => $paiement->statut,
            'datePaiement' => $paiement->date_paiement ? $paiement->date_paiement->format('Y-m-d H:i:s') : $paiement->created_at->format('Y-m-d H:i:s'),
            'operateur'    => $paiement->operateur ?? 'CAMPOST',
            'reference'    => $paiement->reference_campost,
        ], 200);
    }
}
