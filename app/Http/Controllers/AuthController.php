<?php

namespace App\Http\Controllers;

use App\Models\PatientProfile;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
    return view('auth.register');
  }

  public function register(Request $request): RedirectResponse
  {
    $validated = $request->validate([
      'first_name' => ['nullable', 'string', 'max:150'],
      'last_name' => ['nullable', 'string', 'max:150'],
      'username' => ['required', 'email', 'max:150', 'unique:users,username'],
      'password' => ['required', 'string', 'min:8'],
      'phone' => ['required', 'string', 'max:32'],
    ], [
      'username.unique' => 'Esiste gia un utente con questo username.',
      'password.min' => 'La password deve contenere almeno 8 caratteri.',
    ]);

    $user = User::create([
      'username' => $validated['username'],
      'email' => $validated['username'],
      'first_name' => $validated['first_name'] ?? '',
      'last_name' => $validated['last_name'] ?? '',
      'password' => $validated['password'],
      'role' => User::ROLE_PATIENT,
    ]);

    PatientProfile::create([
      'user_id' => $user->id,
      'phone' => $validated['phone'],
    ]);

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
