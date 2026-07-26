{{-- resources/views/recherche-facture.blade.php --}}
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MINEPIA Paiement - Rechercher ma facture</title>
    <!-- Bootstrap 5 + Icons + Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            background: #f4f7fc;
            font-family: 'Inter', system-ui, -apple-system, 'Segoe UI', Roboto, sans-serif;
        }
        .search-card {
            border: none;
            border-radius: 1.5rem;
            box-shadow: 0 20px 35px -12px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s ease, box-shadow 0.2s;
            backdrop-filter: blur(0px);
            background: #ffffff;
        }
        .search-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 25px 40px -14px rgba(0, 0, 0, 0.15);
        }
        .card-header-custom {
            background: transparent;
            border-bottom: 2px solid #eef2f6;
            padding: 1.5rem 2rem 0.6rem 2rem;
        }
        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 0.4rem;
            color: #1e2a3e;
            letter-spacing: -0.2px;
        }
        .form-control, .input-group-text {
            border-radius: 0.9rem;
            border: 1px solid #e2e8f0;
            padding: 0.7rem 1rem;
            font-size: 0.95rem;
            transition: all 0.2s;
            background-color: #ffffff;
        }
        .form-control:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59,130,246,0.15);
            outline: none;
        }
        .input-group-text {
            background-color: #ffffff;
            border-right: none;
            color: #6c86a3;
        }
        .input-group .form-control {
            border-left: none;
        }
        .input-group .form-control:focus {
            border-left: none;
        }
        .btn-primary {
            background: #0f3b2c;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 2.5rem;
            font-weight: 600;
            letter-spacing: 0.3px;
            transition: 0.2s;
            box-shadow: 0 2px 6px rgba(15,59,44,0.2);
        }
        .btn-primary:hover {
            background: #0a2c21;
            transform: scale(1.02);
            box-shadow: 0 8px 18px rgba(15,59,44,0.25);
        }
        .required-field::after {
            content: "*";
            color: #e53e3e;
            margin-left: 4px;
            font-weight: bold;
        }
        .optional-badge {
            font-size: 0.7rem;
            font-weight: normal;
            background: #eef2ff;
            color: #2c5f2d;
            padding: 2px 8px;
            border-radius: 30px;
            margin-left: 8px;
        }
        .error-feedback {
            font-size: 0.75rem;
            margin-top: 6px;
            color: #dc2626;
        }
        .form-text {
            font-size: 0.7rem;
            color: #6c757d;
            margin-top: 6px;
        }
        .brand-icon {
            background: #eef6f2;
            width: 65px;
            height: 65px;
            line-height: 65px;
            border-radius: 60px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        @media (max-width: 640px) {
            .card-body {
                padding: 1.8rem !important;
            }
            .btn-primary {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-7 col-md-9">
                {{-- En-tête avec identité MINEPIA --}}
                <div class="text-center mb-4">
                    <div class="brand-icon mx-auto mb-3">
                        <i class="fas fa-chalkboard-user fa-2x text-success" style="color:#1e6f3f;"></i>
                    </div>
                    <h1 class="display-6 fw-semibold" style="color: #0e2b20;">MINEPIA PAIE</h1>
                    <p class="text-muted">Système de gestion scolaire — Paiement et facturation</p>
                </div>
                @if($errors->any())
    <div style="color: red;">
        {{ $errors->first() }}
    </div>
@endif
                {{-- Carte formulaire --}}
                <div class="card search-card">
                    <div class="card-header-custom">
                        <div class="d-flex align-items-center gap-2">
                            <i class="fas fa-file-invoice-dollar fs-4 text-primary"></i>
                            <h3 class="h4 mb-0 fw-semibold">Rechercher ma facture</h3>
                        </div>
                        <p class="text-muted small mt-2 mb-0">Remplissez au moins le code apprenant pour accéder à votre facture.</p>
                    </div>
                    <div class="card-body p-4 p-lg-5">
                        {{-- Formulaire Laravel --}}
                        <form method="POST" action="{{ route('apprenant.recherche') }}" id="rechercheFacture">
                            @csrf

                            {{-- Code apprenant (obligatoire) --}}
                            <div class="mb-4">
                                <label for="code_apprenant" class="form-label required-field">Code apprenant</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-qrcode"></i></span>
                                    <input type="text"
                                           class="form-control @error('code_apprenant') is-invalid @enderror"
                                           id="code_apprenant"
                                           name="code_apprenant"
                                           value="{{ old('code_apprenant') }}"
                                           placeholder="Ex: 2023A02000041"
                                           required>
                                </div>
                                @error('code_apprenant')
                                    <div class="error-feedback"><i class="fas fa-exclamation-circle me-1"></i> {{ $message }}</div>
                                @enderror
                                <div class="form-text"><i class="fas fa-info-circle me-1"></i> Identifiant unique fourni lors de l'inscription.</div>
                            </div>

                            {{-- Ligne deux champs optionnels --}}
                            <div class="row g-4">
                                <div class="col-md-6">
                                    <label for="nom" class="form-label">
                                        Nom complet <span class="optional-badge">Facultatif</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-user-graduate"></i></span>
                                        <input type="text"
                                               class="form-control @error('nom') is-invalid @enderror"
                                               id="nom"
                                               name="nom"
                                               value="{{ old('nom') }}"
                                               placeholder="ex: Mbarga Jean">
                                    </div>
                                    @error('nom')
                                        <div class="error-feedback">{{ $message }}</div>
                                    @enderror
                                </div>

                                <div class="col-md-6">
                                    <label for="date_naissance" class="form-label">
                                        Date de naissance <span class="optional-badge">Facultatif</span>
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-calendar-alt"></i></span>
                                        <input type="text"
                                               class="form-control @error('date_naissance') is-invalid @enderror"
                                               id="date_naissance"
                                               name="date_naissance"
                                               value="{{ old('date_naissance') }}"
                                               placeholder="jj/mm/aaaa">
                                    </div>
                                    @error('date_naissance')
                                        <div class="error-feedback">{{ $message }}</div>
                                    @enderror
                                    <div class="form-text">Format JJ/MM/AAAA (ex: 15/08/2005)</div>
                                </div>
                            </div>

                            {{-- Bouton de recherche --}}
                            <div class="mt-5 d-flex justify-content-center justify-content-md-start">
                                <button type="submit" class="btn btn-primary px-5 py-2">
                                    <i class="fas fa-search me-2"></i>Lancer la recherche
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                {{-- Pied de page discret --}}
                <div class="text-center mt-4 text-muted small">
                    <i class="fas fa-shield-alt text-success"></i> Données sécurisées — MINEPIA
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
