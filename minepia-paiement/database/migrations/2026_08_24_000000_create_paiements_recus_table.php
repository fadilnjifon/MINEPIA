<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('paiements_recus', function (Blueprint $table) {
            $table->id();
            $table->string('numero_recu')->unique()->index();
            $table->string('matricule')->index();
            $table->string('tranche_id')->nullable();
            $table->decimal('montant', 12, 2)->default(0);
            $table->string('statut')->default('PAID'); // PAID, PENDING, CANCELLED
            $table->dateTime('date_paiement')->nullable();
            $table->string('reference_campost')->nullable();
            $table->string('operateur')->default('CAMPOST');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('paiements_recus');
    }
};
