<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\User;
use App\Models\PaiementRecu;

class CampostApiController extends Controller
{
    /**
     * Authentification dynamique auprès de l'API SYGEAP pour obtenir un Bearer Token
     */
    private function getSygeapToken(): ?string
    {
        $baseUrl = rtrim(config('services.sygeap.url') ?: (env('SYGEAP_BASE_URL') ?: env('SYGEAP_API_URL')), '/');
        $username = config('services.sygeap.username') ?: env('SYGEAP_USERNAME');
        $password = config('services.sygeap.password') ?: env('SYGEAP_PASSWORD');

        if (!$baseUrl || !$username || !$password) {
            Log::error('SYGEAP API: Configuration manquante dans .env ou config (BASE_URL, USERNAME, PASSWORD)');
            return null;
        }

        try {
            $response = Http::withoutVerifying()->post("{$baseUrl}/api/account/auth", [
                'username' => $username,
                'password' => $password,
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['accessToken'] ?? $data['access_token'] ?? $data['token'] ?? null;
            }

            Log::error("SYGEAP Authentification échouée: Status " . $response->status() . " - " . $response->body());
            return null;
        } catch (\Exception $e) {
            Log::error("SYGEAP Exception Authentification: " . $e->getMessage());
            return null;
        }
    }

    /**
     * 1. Authentification Machine — SYGEAP / Systèmes Tiers
     * POST /api/account/auth
     *
     * Permet au système externe SYGEAP de s'authentifier et de récupérer un Bearer Token Sanctum.
     * Accepte : username (nom d'utilisateur ou email) + password.
     * Retourne : { success, accessToken, dateExpiration }
     */
    public function auth(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ], [
            'username.required' => 'Le champ username est obligatoire.',
            'password.required' => 'Le champ password est obligatoire.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Paramètres invalides',
                'errors'  => $validator->errors(),
            ], 400);
        }

        // Recherche de l'utilisateur par username OU email, avec le flag is_campost = true
        $user = User::where(function ($query) use ($request) {
                        $query->where('name', $request->username)
                              ->orWhere('email', $request->username);
                    })
                    ->where('is_campost', true)
                    ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            Log::warning('Tentative d\'authentification API échouée pour : ' . $request->username);
            return response()->json([
                'success' => false,
                'message' => 'Identifiants incorrects ou accès non autorisé.',
            ], 401);
        }

        // Révocation des anciens tokens pour éviter la prolifération
        $user->tokens()->where('name', 'sygeap_api_token')->delete();

        $tokenExpiration = now()->addYear();
        $tokenResult = $user->createToken('sygeap_api_token', ['*'], $tokenExpiration);
        $token = $tokenResult->plainTextToken;

        Log::info('Authentification API réussie pour : ' . $user->name . ' (email: ' . $user->email . ')');

        return response()->json([
            'success'        => true,
            'accessToken'    => $token,
            'dateExpiration' => $tokenExpiration->toIso8601String(),
        ], 200);
    }

    /**
     * 2. Validation du Token / Infos Utilisateur
     * GET /api/account/me
     */
    public function me(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'username' => $user->username ?? $user->email ?? 'campost_api',
            'ministere' => [
                'code' => 'MINEPIA',
                'libelle' => "Ministère de l'Élevage, des Pêches et des Industries Animales"
            ],
            'application' => [
                'nom' => 'Portail de Paiement CamPost MINEPIA',
                'version' => '1.0.0',
                'env' => env('APP_ENV', 'production')
            ]
        ], 200);
    }

    /**
     * 3. Recherche dynamique du Candidat via l'API SYGEAP (Sans aucune donnée mockée)
     * GET /api/apprenants/matricule/{matricule}
     */
    public function getCandidate($matricule)
    {
        $token = $this->getSygeapToken();

        if (!$token) {
            Log::error("SYGEAP API: Impossible d'obtenir le token d'authentification pour matricule {$matricule}");
            return response()->json([
                'success' => false,
                'message' => 'Candidat introuvable sur la plateforme SYGEAP'
            ], 404);
        }

        $baseUrl = rtrim(config('services.sygeap.url') ?: (env('SYGEAP_BASE_URL') ?: env('SYGEAP_API_URL')), '/');
        $urlApprenant = "{$baseUrl}/api/apprenants/matricule/{$matricule}";

        try {
            $response = Http::withoutVerifying()->withToken($token)->get($urlApprenant);

            if (!$response->successful()) {
                Log::warning("SYGEAP API: Candidat {$matricule} non trouvé. Code HTTP: " . $response->status());
                return response()->json([
                    'success' => false,
                    'message' => 'Candidat introuvable sur la plateforme SYGEAP'
                ], 404);
            }

            $raw = $response->json();
            $data = $raw['data'] ?? $raw;

            if (empty($data)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Candidat introuvable sur la plateforme SYGEAP'
                ], 404);
            }

            // ---------------------------------------------------------
            // DEMANDE DU CLIENT : AUCUNE DONNÉE EN DUR.
            // On renvoie EXATEMENT ce que l'API SYGEAP a fourni.
            // (La réponse SYGEAP contient déjà ministere, parcours, etablissement, typeServices)
            // ---------------------------------------------------------
            
            // On s'assure d'avoir 'success' => true
            if (is_array($data)) {
                $data['success'] = true;
            } else {
                $data = ['success' => true, 'data' => $data];
            }

            return response()->json($data, 200);

        } catch (\Exception $e) {
            Log::error("SYGEAP Exception pour matricule {$matricule} : " . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Candidat introuvable sur la plateforme SYGEAP'
            ], 404);
        }
    }

    /**
     * 4. Webhook de Notification de Paiement avec Synchronisation SYGEAP
     * POST /api/campost/notify-payment
     */
    public function notifyPayment(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'reference' => 'required|string',
            'status' => 'required|string|in:PAID,FAILED,CANCELLED,EXPIRED',
            'transactionId' => 'required|string',
        ]);

        if ($validator->fails()) {
            Log::warning('CAMPOST Webhook Erreur Validation:', $validator->errors()->toArray());
            return response()->json(false, 400);
        }

        try {
            Log::info("CAMPOST Webhook Reçu: Reference {$request->reference}, Status [{$request->status}], TXID {$request->transactionId}");

            $sygeapValid = false;

            if ($request->status === 'PAID') {
                $token = $this->getSygeapToken();

                if ($token) {
                    $baseUrl = rtrim(config('services.sygeap.url') ?: (env('SYGEAP_BASE_URL') ?: env('SYGEAP_API_URL')), '/');

                    $sygeapResponse = Http::withoutVerifying()
                        ->withToken($token)
                        ->post("{$baseUrl}/api/campost/notify-payment", [
                            'reference' => $request->reference,
                            'transactionId' => $request->transactionId,
                            'status' => $request->status,
                            'montant' => $request->input('montant', 25000),
                            'date_paiement' => now()->toIso8601String(),
                        ]);

                    if (!$sygeapResponse->successful()) {
                        // Fallback vers /api/payments/notify si nécessaire
                        $sygeapResponse = Http::withoutVerifying()
                            ->withToken($token)
                            ->post("{$baseUrl}/api/payments/notify", [
                                'reference' => $request->reference,
                                'transactionId' => $request->transactionId,
                                'status' => $request->status,
                                'montant' => $request->input('montant', 25000),
                            ]);
                    }

                    $sygeapValid = $sygeapResponse->successful();

                    if ($sygeapValid) {
                        Log::info("SYGEAP Notification Paiement Validée avec Succès pour {$request->reference}");
                    } else {
                        Log::error("SYGEAP Notification Échouée. Status: " . $sygeapResponse->status() . " Body: " . $sygeapResponse->body());
                    }
                } else {
                    Log::error("SYGEAP Token Indisponible pour la notification de paiement {$request->reference}");
                }
            } else {
                // Pour les échecs / annulations
                $sygeapValid = true;
            }

            // Enregistrement local de la transaction si le modèle existe
            if (class_exists(\App\Models\Transaction::class)) {
                try {
                    \App\Models\Transaction::create([
                        'reference_dossier' => $request->reference,
                        'transaction_id' => $request->transactionId,
                        'statut' => $request->status,
                        'montant' => $request->input('montant', 25000),
                        'plateforme' => 'CAMPOST'
                    ]);
                } catch (\Exception $ex) {
                    Log::warning("Transaction BDD enregistrement échoué: " . $ex->getMessage());
                }
            }

            return response()->json($sygeapValid, $sygeapValid ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('CAMPOST Webhook Exception: ' . $e->getMessage());
            return response()->json(false, 500);
        }
    }

    /**
     * 5. Enregistrement d'un Paiement effectué au Guichet par un Agent CamPost
     * POST /api/campost/paiement
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function enregistrerPaiementGuichet(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'matricule'        => 'required|string',
            'tranche_id'       => 'required|string',
            'montant'          => 'required|numeric|min:100',
            'reference_agent'  => 'nullable|string',
        ], [
            'matricule.required'  => 'Le matricule du candidat/apprenant est obligatoire.',
            'tranche_id.required' => 'L\'identifiant de la tranche de paiement est obligatoire.',
            'montant.required'    => 'Le montant du paiement est obligatoire.',
            'montant.numeric'     => 'Le montant doit être un nombre valide.',
            'montant.min'         => 'Le montant minimum est de 100 FCFA.',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Erreur de validation des données fournies.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        try {
            $matricule = trim($request->input('matricule'));
            $trancheId = trim((string) $request->input('tranche_id'));
            $montant = (float) $request->input('montant');
            $referenceAgent = $request->input('reference_agent');
            $datePaiement = now();
            $annee = $datePaiement->format('Y');

            // Génération Sécurisée et Imprévisible du Numéro de Reçu avec vérification d'unicité
            $maxAttempts = 10;
            $attempts = 0;
            $numeroRecu = null;

            do {
                $attempts++;
                $randomBytes = bin2hex(random_bytes(8));
                $secretKey = config('app.key') ?: env('APP_KEY', 'base64:minepia-campost-secret');
                $hash = strtoupper(substr(hash('sha256', $matricule . microtime(true) . $randomBytes . $secretKey), 0, 12));
                $candidateRecu = "REC-{$annee}-{$hash}";

                if (!PaiementRecu::where('numero_recu', $candidateRecu)->exists()) {
                    $numeroRecu = $candidateRecu;
                    break;
                }
            } while ($attempts < $maxAttempts);

            if (!$numeroRecu) {
                Log::error("Échec de génération d'un numéro de reçu unique après {$maxAttempts} tentatives.");
                return response()->json([
                    'success' => false,
                    'message' => 'Erreur lors de la génération du numéro de reçu sécurisé. Veuillez réessayer.',
                ], 500);
            }

            // Enregistrement en base de données dans la table paiements_recus
            $user = $request->user();
            $paiement = PaiementRecu::create([
                'numero_recu'       => $numeroRecu,
                'matricule'         => $matricule,
                'tranche_id'        => $trancheId,
                'montant'           => $montant,
                'statut'            => 'PAID',
                'date_paiement'     => $datePaiement,
                'reference_campost' => $referenceAgent,
                'operateur'         => 'CAMPOST',
                'metadata'          => [
                    'agent_id'        => $user?->id,
                    'agent_name'      => $user?->name,
                    'agent_email'     => $user?->email,
                    'reference_agent' => $referenceAgent,
                    'ip'              => $request->ip(),
                    'user_agent'      => $request->userAgent(),
                ],
            ]);

            Log::info("Paiement Guichet CamPost enregistré: Reçu [{$numeroRecu}], Matricule [{$matricule}], Tranche [{$trancheId}], Montant [{$montant}], Agent [{$user?->email}]");

            // Notification / Synchronisation vers SYGEAP si possible (en tâche de fond / sans bloquer la réponse)
            try {
                $token = $this->getSygeapToken();
                if ($token) {
                    $baseUrl = rtrim(config('services.sygeap.url') ?: (env('SYGEAP_BASE_URL') ?: env('SYGEAP_API_URL')), '/');
                    Http::withoutVerifying()
                        ->timeout(10)
                        ->withToken($token)
                        ->post("{$baseUrl}/api/campost/notify-payment", [
                            'reference'     => $numeroRecu,
                            'matricule'     => $matricule,
                            'tranche_id'    => $trancheId,
                            'transactionId' => $referenceAgent ?: $numeroRecu,
                            'status'        => 'PAID',
                            'montant'       => $montant,
                            'date_paiement' => $datePaiement->toIso8601String(),
                        ]);
                }
            } catch (\Exception $syncEx) {
                Log::warning("Notification SYGEAP post-paiement guichet ignorée ou échouée: " . $syncEx->getMessage());
            }

            return response()->json([
                'success'      => true,
                'message'      => 'Paiement enregistré avec succès',
                'numeroRecu'   => $numeroRecu,
                'matricule'    => $matricule,
                'trancheId'    => $trancheId,
                'montant'      => $montant,
                'statut'       => 'PAID',
                'datePaiement' => $datePaiement->format('Y-m-d H:i:s'),
            ], 201);

        } catch (\Exception $e) {
            Log::error("Erreur enregistrement paiement guichet CamPost: " . $e->getMessage(), [
                'exception' => $e,
                'request'   => $request->except(['password']),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Une erreur interne est survenue lors de l\'enregistrement du paiement.',
            ], 500);
        }
    }
}
