<?php

namespace App\Http\Controllers;

use App\Models\User;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function showLogin(): View|RedirectResponse
    {
        $intended = request()->string('intended')->toString();

        if ($intended !== '' && str_starts_with($intended, '/')) {
            request()->session()->put('url.intended', $intended);
        }

        if (Auth::check()) {
            return redirect()->intended(route('dashboard'));
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $email = mb_strtolower(trim($credentials['email']));
        $user = User::whereRaw('lower(email) = ?', [$email])->first();

        if (! $user instanceof User || ! Hash::check($credentials['password'], $user->password)) {
            return back()->withErrors(['email' => 'These credentials do not match our records.'])->onlyInput('email');
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();
        $this->ensureSettings($user);

        return $this->afterAuthRedirect($request);
    }

    public function showRegister(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }

        return view('auth.register');
    }

    public function register(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'email' => [
                'required',
                'email',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $email = mb_strtolower(trim((string) $value));

                    if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
                        $fail('The email has already been taken.');
                    }
                },
            ],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => mb_strtolower(trim($data['email'])),
            'password' => Hash::make($data['password']),
        ]);

        $this->ensureSettings($user);
        Auth::login($user);
        $request->session()->regenerate();

        return $this->afterAuthRedirect($request);
    }

    public function firebaseLogin(Request $request): JsonResponse
    {
        $data = $request->validate(['id_token' => ['required', 'string']]);
        $apiKey = (string) config('services.firebase.api_key');

        if ($apiKey === '') {
            return response()->json(['success' => false, 'error' => 'Firebase is not configured.'], 503);
        }

        try {
            $response = Http::connectTimeout(5)->timeout(15)
                ->withQueryParameters(['key' => $apiKey])
                ->post('https://identitytoolkit.googleapis.com/v1/accounts:lookup', [
                    'idToken' => $data['id_token'],
                ]);
        } catch (\Throwable $exception) {
            return response()->json(['success' => false, 'error' => 'Could not verify Google account.'], 502);
        }

        $account = $response->json('users.0');

        if (! $response->successful() || ! is_array($account) || empty($account['email'])) {
            return response()->json(['success' => false, 'error' => 'Google account could not be verified.'], 401);
        }

        $email = mb_strtolower(trim((string) $account['email']));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user instanceof User) {
            $user = User::create([
                'name' => $account['displayName'] ?? explode('@', $email)[0],
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
            ]);
        }

        $this->ensureSettings($user);
        Auth::login($user);
        $request->session()->regenerate();

        return response()->json([
            'success' => true,
            'redirect' => $this->afterAuthRedirect($request)->getTargetUrl(),
        ]);
    }

    public function redirectToGoogle(): RedirectResponse
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect()->route('login')->withErrors(['email' => 'Google login is not configured yet.']);
        }

        return Socialite::driver('google')->redirect();
    }

    public function handleGoogleCallback(): RedirectResponse
    {
        if (! config('services.google.client_id') || ! config('services.google.client_secret')) {
            return redirect()->route('login')->withErrors(['email' => 'Google login is not configured yet.']);
        }

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (\Throwable $exception) {
            return redirect()->route('login')->withErrors(['email' => 'Google sign-in failed. Please try again.']);
        }

        $email = mb_strtolower(trim((string) $googleUser->getEmail()));
        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();

        if (! $user instanceof User) {
            $user = User::create([
                'name' => $googleUser->getName() ?: explode('@', $email)[0],
                'email' => $email,
                'password' => Hash::make(Str::random(32)),
            ]);
        }

        $this->ensureSettings($user);
        Auth::login($user);
        request()->session()->regenerate();

        return $this->afterAuthRedirect(request());
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function afterAuthRedirect(Request $request): RedirectResponse
    {
        if ($request->session()->has('eurocross_connect')) {
            return redirect()->route('connect.confirm');
        }

        return redirect()->intended(route('dashboard'));
    }

    private function ensureSettings(User $user): void
    {
        if ($user->euroCrossSettings()->doesntExist()) {
            $user->euroCrossSettings()->create([
                'locale' => 'en',
                'plan' => 'starter',
                'trial' => true,
                'trial_started_at' => now(),
                'trial_expires' => now()->addDays((int) config('payment.trial.days', 7)),
                'grace_expires' => now()->addDays((int) config('payment.trial.days', 7) + 3),
                'billing_email' => $user->email,
            ]);
        }
    }
}
