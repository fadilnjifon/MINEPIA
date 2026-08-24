{{-- resources/views/fiche.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Situation financière - SYGEAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: #f4f7fc;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }
        .page-header {
            background: #ffffff;
            border-radius: 1.5rem;
            padding: 1.2rem 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 8px rgba(0,0,0,0.03);
            border-left: 5px solid #0f3b2c;
        }
        .info-card {
            border: none;
            border-radius: 1.25rem;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
            transition: 0.2s;
            background: #ffffff;
        }
        .info-card .card-body {
            padding: 1.8rem;
        }
        .info-label {
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #5b6e8c;
            font-weight: 600;
        }
        .info-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: #1e2a3e;
            word-break: break-word;
        }
        .status-badge {
            display: inline-block;
            padding: 0.35rem 1rem;
            border-radius: 2rem;
            font-size: 0.8rem;
            font-weight: 600;
        }
        .status-solde {
            background: #d1fae5;
            color: #065f46;
        }
        .status-impaye {
            background: #fee2e2;
            color: #991b1b;
        }
        .table-custom {
            border-collapse: separate;
            border-spacing: 0;
            width: 100%;
        }
        .table-custom th {
            background: #f8fafc;
            border-bottom: 2px solid #e2e8f0;
            padding: 1rem 1rem;
            font-weight: 600;
            color: #1f2a44;
        }
        .table-custom td {
            padding: 1rem;
            vertical-align: middle;
            border-bottom: 1px solid #eef2f6;
        }
        .table-custom tr:last-child td {
            border-bottom: none;
        }
        .btn-primary-custom {
            background: #0f3b2c;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 2.5rem;
            font-weight: 600;
            transition: 0.2s;
            color: white;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
        }
        .btn-primary-custom:hover {
            background: #0a2c21;
            transform: translateY(-2px);
            color: white;
        }
        .alert-solde {
            background-color: #e0f2fe;
            border-left: 5px solid #0284c7;
            border-radius: 1rem;
            padding: 1rem 1.5rem;
            color: #0c4a6e;
        }
        .back-link {
            color: #5b6e8c;
            text-decoration: none;
            font-weight: 500;
        }
        .back-link:hover {
            color: #0f3b2c;
        }
        @media (max-width: 768px) {
            .info-card .card-body {
                padding: 1.2rem;
            }
            .table-custom th, .table-custom td {
                padding: 0.75rem;
            }
            .btn-primary-custom {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <div class="container py-4 py-lg-5">
        {{-- En-tête avec navigation --}}
        <div class="page-header d-flex flex-wrap justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-0 fw-semibold" style="color: #0e2b20;">
                    <i class="fas fa-coins me-2 text-success"></i>Situation financière
                </h1>
                <p class="text-muted mt-1 mb-0">SYGEAP – État des frais de scolarité</p>
            </div>
            <a href="/" class="back-link mt-2 mt-sm-0">
                <i class="fas fa-arrow-left me-1"></i> Nouvelle recherche
            </a>
        </div>

        {{-- Messages Flash --}}
        @if(session('success'))
            <div class="alert alert-success alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center mb-4" role="alert" style="background-color: #d1fae5; color: #065f46;">
                <i class="fas fa-check-circle fa-2x me-3 text-success"></i>
                <div>
                    <h6 class="alert-heading mb-1 fw-bold">Paiement Validé !</h6>
                    <p class="mb-0">{{ session('success') }}</p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger alert-dismissible fade show rounded-4 shadow-sm border-0 d-flex align-items-center mb-4" role="alert">
                <i class="fas fa-exclamation-triangle fa-2x me-3 text-danger"></i>
                <div>
                    <h6 class="alert-heading mb-1 fw-bold">Attention</h6>
                    <ul class="mb-0 ps-3">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        @endif

        {{-- Carte Informations apprenant --}}
        <div class="card info-card mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="fas fa-user-graduate fs-5" style="color: #0f3b2c;"></i>
                    <h5 class="card-title mb-0 fw-semibold">Informations de l'apprenant</h5>
                </div>
                <div class="row g-4">
                    <div class="col-md-6 col-lg-3">
                        <div class="info-label">Nom complet</div>
                        <div class="info-value">
                            {{ is_string($donnees['nom'] ?? null) ? $donnees['nom'] : '' }}
                            {{ is_string($donnees['prenom'] ?? null) ? $donnees['prenom'] : '' }}
                            @if(empty($donnees['nom']) && empty($donnees['prenom'])) Non renseigné @endif
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="info-label">Matricule / Code</div>
                        <div class="info-value">
                            {{ is_string($donnees['matricule'] ?? null) ? $donnees['matricule'] : session('matricule') }}
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="info-label">Établissement</div>
                        <div class="info-value">
                            @if(isset($donnees['ecole']) && is_array($donnees['ecole']))
                                {{ $donnees['ecole']['libelle'] ?? 'Non spécifié' }}
                            @else
                                {{ is_string($donnees['ecole'] ?? null) ? $donnees['ecole'] : 'Non spécifié' }}
                            @endif
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3">
                        <div class="info-label">Statut d'inscription</div>
                        <div class="info-value">
                            @php
                                $statut = $donnees['statut_inscription'] ?? 'NON_SOLDE';
                                if (!is_string($statut)) { $statut = 'NON_SOLDE'; }
                            @endphp
                            @if($statut === 'SOLDE')
                                <span class="status-badge status-solde"><i class="fas fa-check-circle me-1"></i> SCOLARITÉ SOLDÉE</span>
                            @else
                                <span class="status-badge status-impaye"><i class="fas fa-exclamation-triangle me-1"></i> {{ str_replace('_', ' ', $statut) }}</span>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>

        {{-- Historique des paiements --}}
        <div class="card info-card">
            <div class="card-body">
                <div class="d-flex align-items-center gap-2 mb-3">
                    <i class="fas fa-receipt fs-5" style="color: #0f3b2c;"></i>
                    <h5 class="card-title mb-0 fw-semibold">Historique des paiements validés</h5>
                </div>

                @if(isset($donnees['paiements_valides']) && is_array($donnees['paiements_valides']) && count($donnees['paiements_valides']) > 0)
                    <div class="table-responsive">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Motif</th>
                                    <th>Date</th>
                                    <th>Référence</th>
                                    <th class="text-end">Montant (FCFA)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($donnees['paiements_valides'] as $paiement)
                                <tr>
                                    <td><i class="fas fa-tag me-2 text-secondary"></i>{{ $paiement['motif'] ?? 'N/A' }}</td>
                                    <td><i class="far fa-calendar-alt me-2 text-secondary"></i>{{ $paiement['date'] ?? 'N/A' }}</td>
                                    <td><code>{{ $paiement['reference'] ?? 'N/A' }}</code></td>
                                    <td class="text-end fw-semibold">{{ number_format($paiement['montant'] ?? 0, 0, ',', ' ') }} FCFA</td>
                                </tr>
                                @endforeach
                            </tbody>
                            @php
                                $totalPaye = collect($donnees['paiements_valides'])->sum('montant');
                            @endphp
                            <tfoot>
                                <tr style="border-top: 2px solid #e2e8f0;">
                                    <td colspan="3" class="fw-bold text-end">Total versé :</td>
                                    <td class="text-end fw-bold text-success">{{ number_format($totalPaye, 0, ',', ' ') }} FCFA</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                @else
                    <div class="alert alert-light text-center py-4">
                        <i class="fas fa-info-circle fa-2x text-muted mb-2 d-block"></i>
                        <p class="mb-0">Aucun paiement validé n'a été enregistré pour cet apprenant.</p>
                    </div>
                @endif

                {{-- Actions selon le statut --}}
                {{-- Actions selon le statut --}}
<div class="mt-4 pt-2">
    @php
        $statutBrut = $donnees['statut_inscription'] ?? 'NON_SOLDE';
    @endphp

    {{-- Si le statut n'est pas SOLDÉE ou s'il contient "NON", on affiche le bouton pour payer --}}
    @if(strtoupper($statutBrut) !== 'SOLDE' && strtoupper($statutBrut) !== 'SOLDEE')
        <div class="d-flex justify-content-start">
            <a href="{{ route('paiement.choix') }}" class="btn-primary-custom">
                <i class="fas fa-credit-card"></i> Compléter ma scolarité
            </a>
        </div>
    @else
        {{-- Sinon (si c'est vraiment SOLDE), on félicite l'apprenant --}}
        <div class="alert-solde d-flex align-items-center gap-3">
            <i class="fas fa-trophy fa-2x"></i>
            <div>
                <strong>Félicitations !</strong> Votre scolarité est entièrement soldée.
                <br><small>Aucune action requise.</small>
            </div>
        </div>
    @endif
</div>
            </div>
        </div>

        {{-- Pied de page --}}
        <div class="text-center mt-4 text-muted small">
            <i class="fas fa-lock me-1"></i> Données confidentielles — SYGEAP
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
