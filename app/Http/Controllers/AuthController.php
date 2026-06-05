<?php

namespace App\Http\Controllers;

use App\Models\PatientProfile;
use App\Models\User;
use App\Services\CodiceFiscaleService;
use App\Support\ComuniItalianiCatalog;
use App\Support\ValidationRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use InvalidArgumentException;

class AuthController extends Controller
{
    public function showLogin(): View
    {
        return view('auth.login');
    }

    public function showRegister(): View
    {
        $comuni = ComuniItalianiCatalog::names();

        return view('auth.register', compact('comuni'));
    }

    public function register(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'first_name' => ['required', 'string', 'max:150', 'regex:/\A\pL+(?: \pL+)*\z/u'],
            'last_name' => ['required', 'string', 'max:150', 'regex:/\A\pL+(?: \pL+)*\z/u'],
            'email' => [...ValidationRules::email(max: 150), 'unique:users,email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'date_of_birth' => ['required', 'date', 'before:today'],
            'place_of_birth' => ['required', 'string', 'max:160'],
            'gender' => ['required', 'in:M,F'],
            'phone' => ValidationRules::phone(),
        ], [
            'first_name.required' => 'Inserisci il nome.',
            'first_name.max' => 'Il nome non puo superare 150 caratteri.',
            'first_name.regex' => 'Il nome puo contenere solo lettere e spazi.',
            'last_name.required' => 'Inserisci il cognome.',
            'last_name.max' => 'Il cognome non puo superare 150 caratteri.',
            'last_name.regex' => 'Il cognome puo contenere solo lettere e spazi.',
            'email.required' => 'Inserisci un indirizzo email.',
            'email.email' => 'Inserisci un indirizzo email valido.',
            'email.regex' => 'Inserisci un indirizzo email valido.',
            'email.max' => 'L\'indirizzo email non puo superare 150 caratteri.',
            'email.unique' => 'Esiste gia un utente con questo indirizzo email.',
            'password.required' => 'Inserisci una password.',
            'password.min' => 'La password deve contenere almeno 8 caratteri.',
            'password.confirmed' => 'La conferma della password non corrisponde.',
            'date_of_birth.required' => 'Inserisci la data di nascita.',
            'date_of_birth.date' => 'Inserisci una data di nascita valida.',
            'date_of_birth.before' => 'La data di nascita deve essere precedente a oggi.',
            'place_of_birth.required' => 'Inserisci il luogo di nascita.',
            'place_of_birth.max' => 'Il luogo di nascita non puo superare 160 caratteri.',
            'gender.required' => 'Seleziona il sesso.',
            'gender.in' => 'Seleziona un sesso valido.',
            'phone.required' => 'Inserisci il numero di telefono.',
            'phone.max' => 'Il numero di telefono non puo superare 32 caratteri.',
            'phone.regex' => 'Inserisci un numero di telefono valido.',
        ]);

        try {
            $user = DB::transaction(function () use ($validated): User {
                $user = User::create([
                    'email' => $validated['email'],
                    'first_name' => $validated['first_name'] ?? '',
                    'last_name' => $validated['last_name'] ?? '',
                    'password' => $validated['password'],
                ]);
                $user->assignRole(User::ROLE_PATIENT);

                $profile = PatientProfile::create([
                    'user_id' => $user->id,
                    'date_of_birth' => $validated['date_of_birth'],
                    'place_of_birth' => Str::upper($validated['place_of_birth']),
                    'gender' => $validated['gender'],
                    'phone' => $validated['phone'],
                ]);

                $profile->update([
                    'codice_fiscale' => CodiceFiscaleService::calcola(
                        $user->last_name,
                        $user->first_name,
                        $profile->date_of_birth,
                        $profile->gender,
                        $profile->place_of_birth,
                    ),
                ]);

                return $user;
            });
        } catch (InvalidArgumentException $exception) {
            throw ValidationException::withMessages([
                'place_of_birth' => $exception->getMessage(),
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect('/patient');
    }

    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ValidationRules::email(),
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'Inserisci un indirizzo email.',
            'email.email' => 'Inserisci un indirizzo email valido.',
            'email.regex' => 'Inserisci un indirizzo email valido.',
        ]);

        $user = User::query()
            ->where('email', $validated['email'])
            ->first();
        if (! $user || ! Hash::check($validated['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => 'Credenziali non valide.',
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();

        return redirect($user->portalRoute() ?? '/unsupported-role');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }

    public function unsupported(): View
    {
        return view('auth.unsupported');
    }
}
