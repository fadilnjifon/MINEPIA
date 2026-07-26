{{-- resources/views/choix_tranche.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mode de paiement - SYGEAP</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body { background: #f4f7fc; font-family: 'Inter', sans-serif; }
        .page-header { background: #ffffff; border-radius: 1.5rem; padding: 1.2rem 2rem; margin-bottom: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.03); border-left: 5px solid #0f3b2c; }
        .payment-card { border: none; border-radius: 1.25rem; box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05); background: #ffffff; transition: 0.3s; height: 100%; }
        .payment-card:hover { transform: translateY(-5px); }
        .btn-sprintpay { background: #0f3b2c; color: white; border-radius: 2rem; font-weight: 600; padding: 0.75rem 2rem; width: 100%; text-decoration: none; display: inline-block; text-align: center; }
        .btn-sprintpay:hover { background: #0a2c21; color: white; }
        .btn-pdf { background: #dc3545; color: white; border-radius: 2rem; font-weight: 600; padding: 0.75rem 2rem; width: 100%; text-decoration: none; display: inline-block; text-align: center; }
        .btn-pdf:hover { background: #bd2130; color: white; }
        .badge-amount { background: #e0f2fe; color: #0369a1; font-weight: bold; font-size: 1.1rem; padding: 0.5rem 1rem; border-radius: 0.5rem; }
    </style>
</head>
<body>
    <div class="container py-5">
        {{-- En-tête --}}
        <div class="page-header d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-0 fw-semibold" style="color: #0e2b20;">
                    <i class="fas fa-wallet me-2 text-success"></i>Finalisation du Paiement
                </h1>
                <p class="text-muted mt-1 mb-0">Matricule concerné : <strong>{{ $matricule }}</strong></p>
            </div>
            <a href="{{ url()->previous() }}" class="btn btn-outline-secondary btn-sm rounded-pill">
                <i class="fas fa-arrow-left me-1"></i> Retour à la fiche
            </a>
        </div>

        {{-- Sélection de la tranche --}}
        <div class="card payment-card mb-4">
            <div class="card-body p-4">
                <h5 class="fw-bold mb-3"><i class="fas fa-list-ol me-2 text-success"></i>1. Choisissez la tranche à s'acquitter</h5>
                <form id="paymentForm">
                    <div class="row g-3">
                        @foreach($options as $option)
                        <div class="col-md-6">
                            <div class="border rounded p-3 d-flex align-items-center justify-content-between">
                                <div class="form-check">
                                    <input class="form-check-input option-tranche" type="radio" name="tranche" id="{{ $option['id'] }}" value="{{ $option['id'] }}" data-label="{{ $option['label'] }}" data-montant="{{ $option['montant'] }}" {{ $loop->first ? 'checked' : '' }}>
                                    <label class="form-check-label fw-semibold" for="{{ $option['id'] }}">
                                        {{ $option['label'] }}
                                    </label>
                                </div>
                                <span class="badge-amount">{{ number_format($option['montant'], 0, ',', ' ') }} F</span>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </form>
            </div>
        </div>

        {{-- Les deux Options de Paiement --}}
        <h5 class="fw-bold mb-3"><i class="fas fa-hand-holding-usd me-2 text-success"></i>2. Choisissez votre mode de règlement</h5>
        <div class="row g-4">
            {{-- OPTION 1 : SPRINTPAY --}}
            <div class="col-md-6">
                <div class="card payment-card border-top border-success border-4">
                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h4 class="fw-bold text-success mb-0">Option A : En Ligne</h4>
                                <i class="fas fa-mobile-alt fa-2x text-success"></i>
                            </div>
                            <p class="text-muted">Payez instantanément via votre téléphone portable. Simple, sécurisé et automatique.</p>
                            <ul class="small text-muted ps-3">
                                <li>Orange Money / MTN Mobile Money</li>
                                <li>Express Union Mobile / Visa / Mastercard</li>
                                <li>Mise à jour immédiate de votre statut sur SYGEAP</li>
                            </ul>
                        </div>
                        <div class="mt-4">
                            <button onclick="payerEnLigne()" class="btn-sprintpay">
                                <i class="fas fa-qrcode me-2"></i>Payer via SprintPay
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            {{-- OPTION 2 : BORDEREAU BANCAIRE PDF --}}
            <div class="col-md-6">
                <div class="card payment-card border-top border-danger border-4">
                    <div class="card-body p-4 d-flex flex-column justify-content-between">
                        <div>
                            <div class="d-flex align-items-center justify-content-between mb-3">
                                <h4 class="fw-bold text-danger mb-0">Option B : À la Banque</h4>
                                <i class="file-pdf fas fa-file-pdf fa-2x text-danger"></i>
                            </div>
                            <p class="text-muted">Téléchargez votre bordereau de versement officiel pré-rempli pour effectuer un dépôt directement au guichet.</p>
                            <ul class="small text-muted ps-3">
                                <li>Document officiel accepté dans nos banques partenaires</li>
                                <li>Idéal si vous préférez payer en espèces</li>
                                <li>Nécessite la validation manuelle de l'administration après dépôt</li>
                            </ul>
                        </div>
                        <div class="mt-4">
                            <button onclick="telechargerBordereau()" class="btn-pdf">
                                <i class="fas fa-download me-2"></i>Télécharger le Bordereau (PDF)
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function getSelectedTranche() {
            const selected = document.querySelector('input[name="tranche"]:checked');
            return {
                id: selected.value,
                label: selected.getAttribute('data-label'),
                montant: selected.getAttribute('data-montant')
            };
        }

        function payerEnLigne() {
            const tranche = getSelectedTranche();
            alert('Redirection SprintPay pour la ' + tranche.label + ' (' + tranche.montant + ' FCFA)... En cours de codage.');
        }

        function telechargerBordereau() {
            const tranche = getSelectedTranche();
            // On redirige vers notre route Laravel qui génère le PDF
            window.location.href = "/paiement/bordereau/{{ $matricule }}/" + tranche.id;
        }
    </script>
</body>
</html>
