<?php

namespace App\Http\Controllers;

use App\Models\PatientProfile;
use App\Models\User;
use App\Services\CodiceFiscaleService;
use App\Support\ValidationRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
  public function showLogin(): View
  {
    return view('auth.login');
  }

  public function login(Request $request): RedirectResponse
  {
    $validated = $request->validate([
      'username' => ['required', 'string'],
      'password' => ['required', 'string'],
    ]);

    $login = $validated['username'];
    $user = User::query()
      ->where('username', $login)
      ->when(
        Str::contains($login, '@'),
        fn ($query) => $query->orWhere('email', $login),
      )
      ->first();
    if (! $user || ! Hash::check($validated['password'], $user->password)) {
      throw ValidationException::withMessages([
        'username' => 'Credenziali non valide.',
      ]);
    }

    Auth::login($user);
    $request->session()->regenerate();

    return redirect($user->portalRoute() ?? '/unsupported-role');
  }

  public function showRegister(): View
  {
    $comuni = array_keys(require app_path('Data/ComuniItaliani.php'));
    return view('auth.register', compact('comuni'));
  }

  public function register(Request $request): RedirectResponse
  {
    $validated = $request->validate([
      'first_name' => ['required', 'string', 'max:150', 'regex:/\A\pL+(?: \pL+)*\z/u'],
      'last_name' => ['required', 'string', 'max:150', 'regex:/\A\pL+(?: \pL+)*\z/u'],
      'username' => ['required', 'email', 'max:150', 'unique:users,username'],
      'password' => ['required', 'string', 'min:8', 'confirmed'],
      'date_of_birth' => ['required', 'date', 'before:today'],
      'place_of_birth' => ['required', 'string', 'max:160'],
      'gender' => ['required', 'in:M,F'],
      'phone' => ValidationRules::phone(),
    ], [
      'first_name.regex' => 'Il nome puo contenere solo lettere e spazi.',
      'last_name.regex' => 'Il cognome puo contenere solo lettere e spazi.',
      'phone.regex' => 'Inserisci un numero di telefono valido.',
      'username.unique' => 'Esiste gia un utente con questo username.',
      'password.min' => 'La password deve contenere almeno 8 caratteri.',
    ]);

    try {
      $user = DB::transaction(function () use ($validated): User {
        $user = User::create([
          'username' => $validated['username'],
          'email' => $validated['username'],
          'first_name' => $validated['first_name'] ?? '',
          'last_name' => $validated['last_name'] ?? '',
          'password' => $validated['password'],
          'role' => User::ROLE_PATIENT,
        ]);

        $profile = PatientProfile::create([
          'user_id' => $user->id,
          'date_of_birth' => $validated['date_of_birth'],
          'place_of_birth' => $validated['place_of_birth'],
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
    } catch (\InvalidArgumentException $exception) {
      throw ValidationException::withMessages([
        'place_of_birth' => $exception->getMessage(),
      ]);
    }

    Auth::login($user);
    $request->session()->regenerate();

    return redirect('/patient');
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
