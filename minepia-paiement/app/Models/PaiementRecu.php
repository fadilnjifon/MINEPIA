<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaiementRecu extends Model
{
    protected $table = 'paiements_recus';

    protected $fillable = [
        'numero_recu',
        'matricule',
        'tranche_id',
        'montant',
        'statut',
        'date_paiement',
        'reference_campost',
        'operateur',
        'metadata',
    ];

    protected $casts = [
        'date_paiement' => 'datetime',
        'metadata' => 'array',
        'montant' => 'decimal:2',
    ];
}
