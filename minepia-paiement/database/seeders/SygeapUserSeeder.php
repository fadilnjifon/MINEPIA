<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

/**
 * Seeder pour créer le compte d'accès API de SYGEAP.
 *
 * Cet utilisateur est le compte machine que SYGEAP utilise pour :
 *   1. S'authentifier via POST /api/account/auth
 *   2. Vérifier les reçus via GET /api/recu/verifier/{numero_recu}
 *
 * Usage :
 *   php artisan db:seed --class=SygeapUserSeeder
 *   (ou via DatabaseSeeder en production)
 */
class SygeapUserSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Paramètres du compte SYGEAP ────────────────────────────────────────
        // Le mot de passe est lu depuis le .env pour ne jamais apparaître en clair dans le code.
        // Valeur par défaut forte si la variable n'est pas définie.
        $username = env('SYGEAP_API_CLIENT_USERNAME', 'sygeap_app');
        $email    = env('SYGEAP_API_CLIENT_EMAIL',    'api@sygeap.minepia.cm');
        $password = env('SYGEAP_API_CLIENT_PASSWORD', 'SygeapSecretMINEPIA#2026!');

        // ─── Création ou mise à jour (idempotent) ────────────────────────────────
        $user = User::updateOrCreate(
            ['email' => $email],
            [
                'name'       => $username,
                'password'   => Hash::make($password),
                'is_campost' => true,   // flag qui autorise l'accès à l'endpoint POST /api/account/auth
            ]
        );

        $action = $user->wasRecentlyCreated ? 'CRÉÉ' : 'MIS À JOUR';

        $this->command->info("✅ Compte SYGEAP API [{$action}]");
        $this->command->info("   → Nom d'utilisateur : {$username}");
        $this->command->info("   → Email             : {$email}");
        $this->command->info("   → is_campost        : true");
        $this->command->warn("   ⚠️  Mot de passe défini via SYGEAP_API_CLIENT_PASSWORD dans .env");
        $this->command->line('');
        $this->command->line('   Testez l\'authentification avec :');
        $this->command->line('   POST /api/account/auth');
        $this->command->line('   Body JSON: {"username": "' . $username . '", "password": "VOTRE_MOT_DE_PASSE"}');

        Log::info("SygeapUserSeeder : compte [{$username}] {$action} en base de données.");
    }
}
