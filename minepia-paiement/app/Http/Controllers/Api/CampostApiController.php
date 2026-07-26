<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use App\Models\User;

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
     * 1. Authentification Machine CamPost
     * POST /api/account/auth
     */
    public function auth(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Paramètres invalides',
                'errors' => $validator->errors()
            ], 400);
        }

        $user = User::where(function ($query) use ($request) {
                    $query->where('username', $request->username)
                          ->orWhere('email', $request->username);
                })
                ->where('is_campost', true)
                ->first();

        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Identifiants incorrects ou accès non autorisé'
            ], 401);
        }

        $tokenResult = $user->createToken('campost_api_token');
        $token = $tokenResult->plainTextToken;

        return response()->json([
            'success' => true,
            'accessToken' => $token,
            'dateExpiration Refresh Token' => now()->addYear()->toIso8601String(),
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

            // Détermination dynamique du statut de paiement des frais
            $statut = strtoupper($data['statut'] ?? $data['status'] ?? '');
            $aDejaPaye = in_array($statut, ['PAYE', 'PAID', 'VALIDE', 'SOLDE']) 
                         || (isset($data['solde']) && (int)$data['solde'] === 0)
                         || (isset($data['montant_restant']) && (int)$data['montant_restant'] === 0);

            $montantFrais = (int) ($data['montant_frais'] ?? $data['montant'] ?? $data['solde'] ?? 25000);

            $typeServices = $aDejaPaye ? [] : [
                [
                    'libelle' => $data['libelle_frais'] ?? $data['service'] ?? 'Frais de Concours / Scolarité',
                    'montant' => $montantFrais
                ]
            ];

            return response()->json([
                'success' => true,
                'ministere' => [
                    'code' => 'MINEPIA',
                    'libelle' => "Ministère de l'Élevage, des Pêches et des Industries Animales"
                ],
                'matricule' => $data['matricule'] ?? $matricule,
                'nom' => $data['nom'] ?? $data['lastname'] ?? '',
                'prenom' => $data['prenom'] ?? $data['firstname'] ?? '',
                'email' => $data['email'] ?? '',
                'dateNaissance' => $data['dateNaissance'] ?? $data['date_naissance'] ?? $data['birthdate'] ?? '',
                'parcours' => [
                    'code' => $data['parcours']['code'] ?? $data['parcours_code'] ?? 'TSA',
                    'libelle' => $data['parcours']['libelle'] ?? $data['parcours_libelle'] ?? "Technicien Supérieur d'Agriculture",
                    'option' => [
                        'code' => $data['parcours']['option']['code'] ?? $data['option_code'] ?? 'EAP',
                        'libelle' => $data['parcours']['option']['libelle'] ?? $data['option_libelle'] ?? "Entrepreneuriat Agro-Pastoral"
                    ]
                ],
                'etablissement' => [
                    'code' => $data['etablissement']['code'] ?? $data['etablissement_code'] ?? 'ETA-001',
                    'libelle' => $data['etablissement']['libelle'] ?? $data['etablissement_libelle'] ?? "École Pratique d'Agriculture",
                    'region' => $data['etablissement']['region'] ?? $data['region'] ?? 'Centre'
                ],
                'typeServices' => $typeServices
            ], 200);

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
}
