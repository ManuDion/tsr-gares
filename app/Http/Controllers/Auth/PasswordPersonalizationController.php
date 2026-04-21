<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PasswordPersonalizationController extends Controller
{
    public function edit(): View
    {
        return view('auth.personalize-password');
    }

    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [], [
            'current_password' => 'mot de passe actuel',
            'password' => 'nouveau mot de passe',
        ]);

        $user = $request->user();

        if (! Hash::check((string) $request->input('current_password'), (string) $user->password)) {
            return back()->withErrors([
                'current_password' => 'Le mot de passe actuel est incorrect.',
            ]);
        }

        $user->update([
            'password' => (string) $request->input('password'),
            'must_change_password' => false,
        ]);

        return redirect()->route('dashboard')->with('status', 'Votre mot de passe a été personnalisé avec succès.');
    }
}
