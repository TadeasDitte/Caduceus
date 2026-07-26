<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;

class AuthentikController extends Controller
{
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('authentik')->redirect();
    }

    public function callback(): RedirectResponse
    {
        $authentikUser = Socialite::driver('authentik')->user();

        $user = DB::transaction(function () use ($authentikUser) {
            $identity = DB::table('oauth_identities')
                ->where('provider', 'authentik')
                ->where('provider_sub', $authentikUser->getId())
                ->first();

            if ($identity) {
                return User::findOrFail($identity->user_id);
            }

            $user = User::firstOrNew(['email' => $authentikUser->getEmail()]);

            if (! $user->exists) {
                $user->name = $authentikUser->getName() ?? $authentikUser->getNickname() ?? $authentikUser->getEmail();
            }

            $user->email_verified_at ??= now();
            $user->save();

            DB::table('oauth_identities')->insert([
                'user_id' => $user->id,
                'provider' => 'authentik',
                'provider_sub' => $authentikUser->getId(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            return $user;
        });

        Auth::login($user, remember: true);

        return redirect()->intended(route('dashboard'));
    }
}
