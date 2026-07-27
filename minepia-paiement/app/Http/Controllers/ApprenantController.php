<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Session;
use Barryvdh\DomPDF\Facade\Pdf;

class ApprenantController extends Controller
{
    /**
     * Connexion à l'API SYGEAP avec les vrais identifiants
     */
    private function getSygeapToken()
    {
        $url = env('SYGEAP_BASE_URL') . '/api/account/auth';

        $response = Http::withoutVerifying()->post($url, [
            'username' => env('SYGEAP_USERNAME'),
            'password' => env('SYGEAP_PASSWORD'),
        ]);

        if ($response->successful()) {
            // C'est ici qu'on récupère le accessToken que tu as vu sur Postman
            return $response->json()['accessToken'];
        }

        return null;
    }

    /**
     * Recherche Réelle de l'apprenant
     */
    public function rechercher(Request $request)
    {
        $request->validate(['code_apprenant' => 'required']);
        $matricule = $request->code_apprenant;

        // 1. Récupération du jeton valide
        $token = $this->getSygeapToken();

        if (!$token) {
            return back()->withErrors(['erreur' => 'Échec d\'authentification avec le serveur SYGEAP. Vérifiez le fichier .env']);
        }

        // 2. Appel de l'endpoint pour récupérer l'apprenant
        $urlApprenant = env('SYGEAP_BASE_URL') . "/api/apprenants/matricule/{$matricule}";

        // On passe le Token dans le Header de la requête
        $response = Http::withoutVerifying()->withToken($token)->get($urlApprenant);

        if ($response->successful()) {
            $donnees = $response->json();

            // On sauvegarde le matricule en session pour l'étape du paiement
            Session::put('matricule', $matricule);

            // On sauvegarde aussi les services à payer pour construire les options de tranches dynamiquement
            $typeServices = $donnees['typeServices'] ?? $donnees['data']['typeServices'] ?? [];
            Session::put('typeServices', $typeServices);

            // On envoie les VRAIES données à la vue fiche.blade.php
            return view('fiche', ['donnees' => $donnees]);
        }

        return back()->withErrors(['erreur' => 'Aucun apprenant trouvé avec le matricule : ' . $matricule]);
    }

    public function choisirTranche()
    {
        // Sécurité : Si pas de matricule en session, on renvoie à l'accueil
        if (!Session::has('matricule')) {
            return redirect('/')->withErrors(['erreur' => 'Veuillez d\'abord rechercher un apprenant.']);
        }

        $matricule = session('matricule');

        // Utilisation des VRAIES données de l'API (enregistrées en session lors de la recherche)
        $typeServices = session('typeServices', []);
        $options = [];

        // Parsing dynamique des tranches depuis SYGEAP
        foreach ($typeServices as $ts) {
            $tsLibelle = $ts['libelle'] ?? 'Frais';
            if (!empty($ts['services'])) {
                foreach ($ts['services'] as $service) {
                    if (empty($service['tranches'])) {
                        // Pas de tranches = paiement total direct
                        $options[] = [
                            'id' => 'service_' . $service['id'],
                            'label' => $tsLibelle,
                            'montant' => $service['montant']
                        ];
                    } else {
                        // S'il y a des tranches, on les liste (triées par ordre)
                        $tranches = $service['tranches'];
                        usort($tranches, function ($a, $b) {
                            return ($a['ordre'] ?? 0) <=> ($b['ordre'] ?? 0);
                        });

                        foreach ($tranches as $tranche) {
                            $options[] = [
                                'id' => 'tranche_' . $tranche['id'],
                                'label' => $tsLibelle . ' - ' . ($tranche['libelle'] ?? 'Tranche ' . ($tranche['ordre'] ?? '')),
                                'montant' => $tranche['montant']
                            ];
                        }
                        
                        // Option de paiement complet
                        $options[] = [
                            'id' => 'service_complet_' . $service['id'],
                            'label' => $tsLibelle . ' (Paiement complet)',
                            'montant' => $service['montant']
                        ];
                    }
                }
            }
        }

        // Sécurité : si aucun frais n'est à payer
        if (empty($options)) {
            return redirect('/')->with('success', 'Cet apprenant a déjà réglé la totalité de ses frais. Aucun paiement requis.');
        }

        return view('choix_tranche', compact('options', 'matricule'));
    }
    // Tout en haut du fichier avec les autres "use"


// ... (reste du code) ...

/**
 * Génération du Bordereau de paiement PDF (Version corrigée pour DomPDF)
 */
public function telechargerBordereau($matricule, $trancheId)
{
    // Sécurité : On vérifie si les informations sont cohérentes
    if (session('matricule') !== $matricule || !Session::has('typeServices')) {
        return redirect('/')->withErrors(['erreur' => 'Session expirée ou invalide.']);
    }

    $typeServices = session('typeServices', []);
    $choix = null;

    // Recherche de l'option correspondante
    foreach ($typeServices as $ts) {
        $tsLibelle = $ts['libelle'] ?? 'Frais';
        if (!empty($ts['services'])) {
            foreach ($ts['services'] as $service) {
                if (empty($service['tranches']) && 'service_' . $service['id'] === $trancheId) {
                    $choix = ['label' => $tsLibelle, 'montant' => $service['montant']];
                                        break 2;
                } elseif (!empty($service['tranches'])) {
                    if ('service_complet_' . $service['id'] === $trancheId) {
                        $choix = ['label' => $tsLibelle . ' (Paiement complet)', 'montant' => $service['montant']];
                        break 2;
                    }
                    foreach ($service['tranches'] as $tranche) {
                        if ('tranche_' . $tranche['id'] === $trancheId) {
                            $choix = [
                                'label' => $tsLibelle . ' - ' . ($tranche['libelle'] ?? 'Tranche ' . ($tranche['ordre'] ?? '')),
                                'montant' => $tranche['montant']
                            ];
                            break 3;
                        }
                    }
                }
            }
        }
    }

    if (!$choix) {
        return redirect('/')->withErrors(['erreur' => 'Option de paiement introuvable ou invalide.']);
    }

    $data = [
        'matricule'   => $matricule,
        'date'        => date('d/m/Y à H:i'),
        'motif'       => $choix['label'],
        'montant'     => $choix['montant'],
        'banque'      => 'Campost',
        'compte_num'  => '10005 00012 34567890123 45',
        'reference'   => 'BORD-' . strtoupper(substr($trancheId, 0, 3)) . '-' . date('ymdHis')
    ];

    // Génération du HTML compatible à 100% avec le moteur de rendu DomPDF
    $pdf = Pdf::loadHTML("
        <div style='font-family: Arial, sans-serif; padding: 15px; border: 3px double #0f3b2c; min-height: 95%;'>

            <!-- EN-TÊTE -->
            <div style='text-align: center; margin-bottom: 15px;'>
                <h2 style='color: #0f3b2c; margin-bottom: 5px;'>BORDEREAU DE VERSEMENT BANCAIRE</h2>
                <p style='color: #555; margin-top: 0; font-size: 0.9rem;'>Document officiel généré via la plateforme SYGEAP</p>
                <p style='font-size: 0.8rem; color: #888;'>Date d'émission : {$data['date']}</p>
            </div>

            <hr style='border: 1px solid #0f3b2c; margin-bottom: 20px;'>

            <!-- TABLEAU DES DONNÉES (Fixé pour ne pas déborder) -->
            <table style='width: 100%; border-collapse: collapse; margin-bottom: 40px;' border='1' cellpadding='10' cellspacing='0'>
                <tr style='background-color: #f9fbf9;'>
                    <td style='width: 40%; font-weight: bold;'>Référence Unique :</td>
                    <td><strong style='font-family: monospace; font-size: 1.1rem; color: #c0392b;'>{$data['reference']}</strong></td>
                </tr>
                <tr>
                    <td style='font-weight: bold;'>Matricule de l'Apprenant :</td>
                    <td><strong style='font-size: 1.1rem; color: #0f3b2c;'>{$data['matricule']}</strong></td>
                </tr>
                <tr style='background-color: #f9fbf9;'>
                    <td style='font-weight: bold;'>Motif du Paiement :</td>
                    <td>{$data['motif']}</td>
                </tr>
                <tr>
                    <td style='font-weight: bold;'>Montant à verser :</td>
                    <td style='font-size: 1.3rem; color: #27ae60; font-weight: bold;'>" . number_format($data['montant'], 0, ',', ' ') . " FCFA</td>
                </tr>
                <tr style='background-color: #f9fbf9;'>
                    <td style='font-weight: bold;'>Banque de Destination :</td>
                    <td>{$data['banque']}</td>
                </tr>
                <tr>
                    <td style='font-weight: bold;'>Numéro de Compte (RIB) :</td>
                    <td><strong style='font-family: monospace; font-size: 1rem;'>{$data['compte_num']}</strong></td>
                </tr>
            </table>

            <!-- ESPACE DE SÉPARATION FORCÉ -->
            <div style='clear: both; height: 30px;'></div>

            <!-- ZONE DES SIGNATURES (Correction avec un tableau HTML pur) -->
            <table style='width: 100%; border: none;' cellspacing='0', cellpadding='0'>
                <tr>
                    <!-- Cadre Étudiant -->
                    <td style='width: 45%; border: 1px solid #333; height: 120px; vertical-align: top; padding: 10px; background-color: #fcfcfc;'>
                        <div style='font-size: 0.85rem; font-weight: bold; color: #333; text-align: center; border-bottom: 1px dashed #ccc; padding-bottom: 5px; margin-bottom: 10px;'>
                            Signature de l'Étudiant
                        </div>
                    </td>

                    <!-- Espace vide du milieu pour séparer les deux cadres -->
                    <td style='width: 10%; border: none;'></td>

                    <!-- Cadre Banque -->
                    <td style='width: 45%; border: 1px solid #333; height: 120px; vertical-align: top; padding: 10px; background-color: #fcfcfc;'>
                        <div style='font-size: 0.85rem; font-weight: bold; color: #333; text-align: center; border-bottom: 1px dashed #ccc; padding-bottom: 5px; margin-bottom: 10px;'>
                            Cachet et Signature de la Banque
                        </div>
                    </td>
                </tr>
            </table>

            <!-- PETIT MOT DE FIN -->
            <div style='margin-top: 40px; text-align: center; font-size: 0.75rem; color: #999; font-style: italic;'>
                Important : Ce bordereau doit être imprimé en deux (02) exemplaires et présenté au guichet de la banque.
            </div>

        </div>
    ");

    // Téléchargement automatique du fichier PDF par le navigateur
    return $pdf->download('Bordereau_Paiement_' . $matricule . '.pdf');
}
}
