<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portail d'Authentification Étape par Étape - CamPost</title>
    <!-- Bootstrap 5 CSS & FontAwesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8fafc;
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            padding: 2rem 1rem;
            color: #0f172a;
        }
        .wizard-container {
            max-width: 650px;
            margin: 0 auto;
        }
        .portal-header {
            background: linear-gradient(135deg, #0f3b2c 0%, #175e46 100%);
            color: #ffffff;
            border-radius: 1.25rem;
            padding: 2rem;
            text-align: center;
            box-shadow: 0 10px 25px rgba(15, 59, 44, 0.15);
            margin-bottom: 2rem;
        }
        .portal-header i {
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            color: #ffc107;
        }
        /* Stepper Navigation */
        .stepper-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            margin-bottom: 2rem;
            padding: 0 0.5rem;
        }
        .stepper-nav::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 15%;
            right: 15%;
            height: 3px;
            background-color: #e2e8f0;
            z-index: 1;
            transform: translateY(-50%);
        }
        .step-item {
            position: relative;
            z-index: 2;
            background: #ffffff;
            border: 2px solid #cbd5e1;
            border-radius: 50%;
            width: 48px;
            height: 48px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            color: #64748b;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        .step-item.active {
            border-color: #0f3b2c;
            background-color: #0f3b2c;
            color: #ffffff;
            box-shadow: 0 0 0 5px rgba(15, 59, 44, 0.15);
            transform: scale(1.1);
        }
        .step-item.completed {
            border-color: #10b981;
            background-color: #10b981;
            color: #ffffff;
        }
        .step-label {
            position: absolute;
            top: 54px;
            left: 50%;
            transform: translateX(-50%);
            white-space: nowrap;
            font-size: 0.75rem;
            font-weight: 600;
            color: #64748b;
        }
        .step-item.active + .step-label, .step-item.completed + .step-label {
            color: #0f3b2c;
            font-weight: 700;
        }
        .step-card {
            border: 1px solid #e2e8f0;
            border-radius: 1.25rem;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05);
            background: #ffffff;
            padding: 2rem;
            transition: all 0.3s ease;
        }
        .step-card-header {
            border-bottom: 2px solid #f1f5f9;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
        }
        .form-label {
            font-weight: 600;
            font-size: 0.85rem;
            margin-bottom: 0.4rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        .form-control, .form-select {
            border-radius: 0.75rem;
            padding: 0.7rem 1rem;
            border: 1px solid #cbd5e1;
            font-size: 0.95rem;
        }
        .form-control:focus, .form-select:focus {
            border-color: #0f3b2c;
            box-shadow: 0 0 0 0.25rem rgba(15, 59, 44, 0.15);
        }
        .btn-campost {
            background-color: #0f3b2c;
            color: #ffffff;
            border-radius: 0.75rem;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            width: 100%;
            transition: all 0.2s ease;
            border: none;
        }
        .btn-campost:hover {
            background-color: #0a291f;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(15, 59, 44, 0.25);
        }
        .token-box {
            background-color: #ecfdf5;
            border: 2px dashed #10b981;
            border-radius: 1rem;
            padding: 1.25rem;
            margin-top: 1.5rem;
        }
        .token-textarea {
            font-family: monospace;
            font-size: 0.85rem;
            background-color: #ffffff;
            border: 1px solid #a7f3d0;
            border-radius: 0.5rem;
            width: 100%;
            height: 90px;
            padding: 0.75rem;
            color: #065f46;
            resize: none;
        }
    </style>
</head>
<body>

<div class="wizard-container">
    
    {{-- Header --}}
    <div class="portal-header">
        <i class="fa-solid fa-building-columns"></i>
        <h3 class="fw-bold mb-1">CamPost Portal</h3>
        <p class="mb-0 text-white-50 small font-semibold">Assistant d'Authentification Étape par Étape</p>
    </div>

    {{-- Alerts --}}
    @if(session('success'))
        <div class="alert alert-success alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i> {{ session('success') }}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @if(session('err') || $errors->any())
        <div class="alert alert-danger alert-dismissible fade show rounded-3 shadow-sm mb-4" role="alert">
            <div class="fw-bold mb-1"><i class="fa-solid fa-triangle-exclamation me-2"></i> [ERREUR]</div>
            @if(session('err'))
                <div>{{ session('err') }}</div>
            @endif
            @if($errors->any())
                <ul class="mb-0 ps-3">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            @endif
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    @endif

    @php
        // Détermination dynamique de l'étape active (1, 2 ou 3)
        $currentStep = session('step') ?? 1;
        if (session('token')) {
            $currentStep = 3;
        }
    @endphp

    {{-- Stepper Progress Bar --}}
    <div class="stepper-nav mb-5">
        <div class="text-center position-relative">
            <div class="step-item {{ $currentStep == 1 ? 'active' : ($currentStep > 1 ? 'completed' : '') }}" onclick="switchStep(1)">
                @if($currentStep > 1) <i class="fa-solid fa-check"></i> @else 1 @endif
            </div>
            <div class="step-label">1. Code OTP</div>
        </div>
        <div class="text-center position-relative">
            <div class="step-item {{ $currentStep == 2 ? 'active' : ($currentStep > 2 ? 'completed' : '') }}" onclick="switchStep(2)">
                @if($currentStep > 2) <i class="fa-solid fa-check"></i> @else 2 @endif
            </div>
            <div class="step-label">2. Mot de passe</div>
        </div>
        <div class="text-center position-relative">
            <div class="step-item {{ $currentStep == 3 ? 'active' : '' }}" onclick="switchStep(3)">
                3
            </div>
            <div class="step-label">3. Token JWT</div>
        </div>
    </div>

    {{-- STEP 1: Demander un code OTP --}}
    <div id="step-panel-1" class="step-card {{ $currentStep != 1 ? 'd-none' : '' }}">
        <div class="step-card-header d-flex align-items-center justify-content-between">
            <h5 class="fw-bold mb-0 text-success">
                <i class="fa-solid fa-1 me-2"></i> Étape 1 : Demander un code OTP
            </h5>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">Étape 1 / 3</span>
        </div>

        <form action="{{ route('campost.portal.request-otp') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label">Adresse Email CamPost</label>
                <input type="email" name="email" class="form-control" required placeholder="votre.nom@campost.cm" value="{{ old('email') }}">
            </div>
            <div class="mb-4">
                <label class="form-label">Action</label>
                <select name="purpose" class="form-select" required>
                    <option value="set_password" {{ old('purpose') == 'set_password' ? 'selected' : '' }}>Définir un mot de passe (Nouveau compte)</option>
                    <option value="reset_password" {{ old('purpose') == 'reset_password' ? 'selected' : '' }}>Réinitialiser le mot de passe</option>
                </select>
            </div>
            <button type="submit" class="btn btn-campost">
                <i class="fa-solid fa-paper-plane me-2"></i> Envoyer le code OTP
            </button>
        </form>
    </div>

    {{-- STEP 2: Confirmer le code + définir le mot de passe --}}
    <div id="step-panel-2" class="step-card {{ $currentStep != 2 ? 'd-none' : '' }}">
        <div class="step-card-header d-flex align-items-center justify-content-between">
            <h5 class="fw-bold mb-0 text-success">
                <i class="fa-solid fa-2 me-2"></i> Étape 2 : Confirmer OTP & Créer Mot de passe
            </h5>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">Étape 2 / 3</span>
        </div>

        <form action="{{ route('campost.portal.confirm-otp') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label">Adresse Email CamPost</label>
                <input type="email" name="email" class="form-control" required placeholder="votre.nom@campost.cm" value="{{ old('email') }}">
            </div>
            <div class="mb-3">
                <label class="form-label">Code OTP (6 chiffres reçus par email)</label>
                <input type="text" name="code" class="form-control text-center font-monospace fs-4 tracking-widest" maxlength="6" required placeholder="000000" value="{{ old('code') }}">
            </div>
            <div class="mb-3">
                <label class="form-label">Action</label>
                <select name="purpose" class="form-select" required>
                    <option value="set_password" {{ old('purpose') == 'set_password' ? 'selected' : '' }}>Définition initiale</option>
                    <option value="reset_password" {{ old('purpose') == 'reset_password' ? 'selected' : '' }}>Réinitialisation</option>
                </select>
            </div>
            <div class="mb-4">
                <label class="form-label">Nouveau Mot de passe</label>
                <input type="password" name="password" class="form-control" required minlength="6" placeholder="••••••••">
            </div>
            <div class="d-flex gap-2">
                <button type="button" class="btn btn-outline-secondary w-25" onclick="switchStep(1)">
                    <i class="fa-solid fa-arrow-left"></i>
                </button>
                <button type="submit" class="btn btn-campost w-75">
                    <i class="fa-solid fa-check-circle me-2"></i> Valider et enregistrer
                </button>
            </div>
        </form>
    </div>

    {{-- STEP 3: Connexion & Génération Token JWT --}}
    <div id="step-panel-3" class="step-card {{ $currentStep != 3 ? 'd-none' : '' }}">
        <div class="step-card-header d-flex align-items-center justify-content-between">
            <h5 class="fw-bold mb-0 text-success">
                <i class="fa-solid fa-3 me-2"></i> Étape 3 : Authentification & Token JWT
            </h5>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill">Étape 3 / 3</span>
        </div>

        <form action="{{ route('campost.portal.login') }}" method="POST">
            @csrf
            <div class="mb-3">
                <label class="form-label">Identifiant (Email CamPost)</label>
                <input type="email" name="login" class="form-control" required placeholder="votre.nom@campost.cm" value="{{ old('login') ?? old('email') }}">
            </div>
            <div class="mb-4">
                <label class="form-label">Mot de passe</label>
                <input type="password" name="password" class="form-control" required placeholder="••••••••">
            </div>
            <div class="d-flex gap-2 mb-3">
                <button type="button" class="btn btn-outline-secondary w-25" onclick="switchStep(2)">
                    <i class="fa-solid fa-arrow-left"></i>
                </button>
                <button type="submit" class="btn btn-campost w-75">
                    <i class="fa-solid fa-key me-2"></i> Obtenir mon Token JWT
                </button>
            </div>
        </form>

        {{-- Display JWT Token if present --}}
        @if(session('token'))
            <div class="token-box">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <span class="fw-bold text-success"><i class="fa-solid fa-shield-halved me-1"></i> [VOTRE TOKEN JWT BEARER]</span>
                    <button class="btn btn-sm btn-success" onclick="copyToken()">
                        <i class="fa-regular fa-copy me-1"></i> Copier
                    </button>
                </div>
                <textarea id="jwtTokenBox" class="token-textarea" readonly onclick="this.select()">{{ session('token') }}</textarea>
                <small class="text-muted d-block mt-2">En-tête HTTP : <code>Authorization: Bearer {{ session('token') }}</code></small>
            </div>
        @endif
    </div>

    <div class="text-center mt-4">
        <a href="{{ url('/') }}" class="text-decoration-none text-muted small">
            <i class="fa-solid fa-arrow-left me-1"></i> Retour à l'accueil MINEPIA
        </a>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<script>
    function switchStep(stepNumber) {
        // Cacher tous les panneaux
        document.getElementById('step-panel-1').classList.add('d-none');
        document.getElementById('step-panel-2').classList.add('d-none');
        document.getElementById('step-panel-3').classList.add('d-none');

        // Afficher le panneau demandé
        document.getElementById('step-panel-' + stepNumber).classList.remove('d-none');
    }

    function copyToken() {
        const tokenBox = document.getElementById('jwtTokenBox');
        if (tokenBox) {
            tokenBox.select();
            navigator.clipboard.writeText(tokenBox.value).then(() => {
                alert('Token JWT copié dans le presse-papier !');
            });
        }
    }
</script>
</body>
</html>
