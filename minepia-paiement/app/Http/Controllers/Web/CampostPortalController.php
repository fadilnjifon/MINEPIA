<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\CampostOtp;
use App\Models\User;
use App\Mail\SendCampostOtpMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class CampostPortalController extends Controller
{
    public function index()
    {
        return view('campost.portal');
    }

    public function requestOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'purpose' => 'nullable|string'
        ]);

        $email = strtolower(trim($request->email));
        $purpose = $request->input('purpose') ?: 'set_password';
        $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Invalider les anciens codes non utilisés pour cet email
        CampostOtp::where('email', $email)->where('used', false)->update(['used' => true]);

        // Créer le nouvel OTP
        CampostOtp::create([
            'email' => $email,
            'code_hash' => Hash::make($code),
            'purpose' => $purpose,
            'expires_at' => now()->addMinutes(15),
            'used' => false,
        ]);

        Log::info("CAMPOST OTP généré pour {$email} : {$code} (purpose: {$purpose})");

        try {
            Mail::to($email)->send(new SendCampostOtpMail($code));
        } catch (\Exception $e) {
            Log::error("Échec de l'envoi d'email OTP pour {$email} : " . $e->getMessage());
        }

        return back()->with('success', 'Code OTP généré et envoyé avec succès à ' . $email . '. Passer à l\'Étape 2 pour confirmer.')->with('step', 2)->withInput();
    }

    public function confirmOtp(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'code' => 'required|string',
            'password' => 'required|string|min:6',
            'purpose' => 'nullable|string'
        ]);

        $email = strtolower(trim($request->email));
        $code = trim($request->code);

        Log::info("CAMPOST Tentative de confirmation OTP pour {$email} avec le code : {$code}");

        // Récupérer les OTP récents non utilisés pour cet e-mail
        $activeOtps = CampostOtp::where('email', $email)
            ->where('used', false)
            ->latest('id')
            ->get();

        if ($activeOtps->isEmpty()) {
            return back()->withErrors(['code' => 'Aucun code OTP actif trouvé pour ' . $email . '. Veuillez faire l\'Étape 1.'])->with('step', 1)->withInput();
        }

        $validOtpRecord = null;
        foreach ($activeOtps as $otpRecord) {
            if (Hash::check($code, $otpRecord->code_hash)) {
                if ($otpRecord->expires_at && $otpRecord->expires_at->isPast()) {
                    return back()->withErrors(['code' => 'Le code OTP a expiré. Veuillez refaire l\'Étape 1.'])->with('step', 1)->withInput();
                }
                $validOtpRecord = $otpRecord;
                break;
            }
        }

        if (!$validOtpRecord) {
            return back()->withErrors(['code' => 'Le code OTP saisi (' . $code . ') est incorrect pour l\'adresse ' . $email . '.'])->with('step', 2)->withInput();
        }

        // Marquer l'OTP comme utilisé
        $validOtpRecord->update(['used' => true]);

        // Créer ou mettre à jour l'utilisateur
        $user = User::where('email', $email)->first();
        if (!$user) {
            $user = new User();
            $user->email = $email;
            $user->name = 'Agent CAMPOST';
        }
        $user->password = $request->password;
        $user->is_campost = true;
        $user->save();

        Log::info("CAMPOST Mot de passe configuré avec succès pour {$email}");

        return back()->with('success', 'Mot de passe configuré avec succès ! Passer à l\'Étape 3 pour obtenir votre token JWT.')->with('step', 3)->withInput();
    }

    public function login(Request $request)
    {
        $emailInput = $request->input('login') ?: $request->input('email');

        if (!$emailInput) {
            return back()->withErrors(['login' => 'L\'adresse email est requise.'])->with('step', 3)->withInput();
        }

        $request->validate([
            'password' => 'required|string'
        ]);

        $email = strtolower(trim($emailInput));
        $user = User::where('email', $email)->first();

        if (!$user) {
            return back()->withErrors(['login' => 'Aucun compte trouvé pour ' . $email . '. Veuillez effectuer l\'Étape 1 & 2 d\'abord.'])->with('step', 1)->withInput();
        }

        if (!Hash::check($request->password, $user->password)) {
            return back()->withErrors(['login' => 'Mot de passe incorrect pour ' . $email . '.'])->with('step', 3)->withInput();
        }

        if (!$user->is_campost) {
            $user->is_campost = true;
            $user->save();
        }

        // Authentifie l'utilisateur CamPost avec son email et mot de passe.
        $tokenResult = $user->createToken('campost_portal_token');
        $token = $tokenResult->plainTextToken;

        return back()->with('token', $token)->with('success', 'Authentification réussie ! Votre token JWT est disponible ci-dessous.')->with('step', 3)->withInput();
    }
}
