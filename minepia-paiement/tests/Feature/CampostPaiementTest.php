<?php

namespace Tests\Feature;

use App\Models\PaiementRecu;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CampostPaiementTest extends TestCase
{
    use RefreshDatabase;

    public function test_route_requires_authentication(): void
    {
        $response = $this->postJson('/api/campost/paiement', [
            'matricule' => 'CONC-2026-00015',
            'tranche_id' => '1',
            'montant' => 25000,
        ]);

        $response->assertStatus(401);
    }

    public function test_validation_errors_when_payload_is_invalid(): void
    {
        $user = User::factory()->create(['is_campost' => true]);
        Sanctum::actingAs($user);

        $response = $this->postJson('/api/campost/paiement', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['matricule', 'tranche_id', 'montant']);
    }

    public function test_enregistrer_paiement_guichet_success(): void
    {
        $user = User::factory()->create(['is_campost' => true]);
        Sanctum::actingAs($user);

        $payload = [
            'matricule' => 'CONC-2026-00015',
            'tranche_id' => '1',
            'montant' => 25000,
            'reference_agent' => 'AGENT-TXN-9988',
        ];

        $response = $this->postJson('/api/campost/paiement', $payload);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Paiement enregistré avec succès',
                'matricule' => 'CONC-2026-00015',
                'trancheId' => '1',
                'montant' => 25000,
                'statut' => 'PAID',
            ]);

        $responseData = $response->json();
        $this->assertArrayHasKey('numeroRecu', $responseData);
        $this->assertMatchesRegularExpression('/^REC-\d{4}-[A-Z0-9]{12}$/', $responseData['numeroRecu']);
        $this->assertArrayHasKey('datePaiement', $responseData);

        $this->assertDatabaseHas('paiements_recus', [
            'numero_recu' => $responseData['numeroRecu'],
            'matricule' => 'CONC-2026-00015',
            'tranche_id' => '1',
            'montant' => '25000.00',
            'statut' => 'PAID',
            'operateur' => 'CAMPOST',
            'reference_campost' => 'AGENT-TXN-9988',
        ]);
    }
}
