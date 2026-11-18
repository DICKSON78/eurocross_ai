<?php

namespace App\Http\Controllers;

use App\Models\EuroCrossSettings;
use App\Models\User;
use App\Services\ConnectedSiteService;
use App\Services\TranslationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ConnectController extends Controller
{
    private const SESSION_KEY = 'eurocross_connect';

    private const CODE_TTL_MINUTES = 10;

    public function __construct(
        private ConnectedSiteService $connectedSites,
        private TranslationService $translationService,
    ) {}

    public function start(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'site_url' => ['required', 'url'],
            'site_name' => ['sometimes', 'string', 'max:120'],
            'return_url' => ['required', 'url'],
            'state' => ['required', 'string', 'max:255'],
        ]);

        $siteUrl = $this->normalizeSiteUrl((string) $validated['site_url']);
        $returnUrl = $this->validateReturnUrl((string) $validated['return_url'], $siteUrl);

        $request->session()->put(self::SESSION_KEY, [
            'site_url' => $siteUrl,
            'site_name' => trim((string) ($validated['site_name'] ?? '')) !== ''
                ? trim((string) $validated['site_name'])
                : parse_url($siteUrl, PHP_URL_HOST),
            'return_url' => $returnUrl,
            'state' => (string) $validated['state'],
            'expires_at' => now()->addMinutes(self::CODE_TTL_MINUTES)->timestamp,
        ]);

        return redirect()->route('connect.confirm');
    }

    public function confirm(Request $request): View
    {
        return view('connect.confirm', [
            'connection' => $this->connection($request),
            'user' => $request->user(),
        ]);
    }

    public function approve(Request $request): RedirectResponse
    {
        $request->validate([
            'confirm' => ['required', 'accepted'],
        ]);

        $connection = $this->connection($request);
        /** @var User $user */
        $user = $request->user();
        $settings = $this->settingsFor($user);
        $code = Str::random(64);
        $secret = Str::random(48);

        Cache::put($this->cacheKey($code), [
            'user_id' => $user->id,
            'settings_id' => $settings->id,
            'site_url' => $connection['site_url'],
            'state' => $connection['state'],
            'site_secret' => $secret,
        ], now()->addMinutes(self::CODE_TTL_MINUTES));

        $request->session()->forget(self::SESSION_KEY);

        return redirect()->to($this->appendQuery($connection['return_url'], [
            'action' => 'eurocross_connect',
            'code' => $code,
            'state' => $connection['state'],
            'site_url' => $connection['site_url'],
        ]));
    }

    public function exchange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:255'],
            'site_url' => ['required', 'url'],
            'state' => ['required', 'string', 'max:255'],
        ]);

        $code = (string) $validated['code'];
        $siteUrl = $this->normalizeSiteUrl((string) $validated['site_url']);
        $invalidCode = fn (): JsonResponse => response()->json([
            'success' => false,
            'error' => 'The connection code is invalid, expired, or belongs to another site.',
        ], 422);

        $lock = Cache::lock('eurocross:connect:exchange:'.hash('sha256', $code), 10);

        if (! $lock->get()) {
            return $invalidCode();
        }

        try {
            $payload = Cache::pull($this->cacheKey($code));

            if (! is_array($payload)
                || ! hash_equals((string) ($payload['state'] ?? ''), (string) $validated['state'])
                || ! hash_equals((string) ($payload['site_url'] ?? ''), $siteUrl)) {
                return $invalidCode();
            }

            $settings = EuroCrossSettings::find($payload['settings_id'] ?? null);
            $userId = $payload['user_id'] ?? null;

            if (! $settings instanceof EuroCrossSettings || $settings->user_id !== $userId) {
                return response()->json([
                    'success' => false,
                    'error' => 'The connected account could not be found.',
                ], 422);
            }

            $token = 'ecx_live_'.Str::random(48);
            $keyName = 'WordPress: '.$siteUrl;
            $secret = (string) ($payload['site_secret'] ?? '');

            DB::transaction(function () use ($settings, $token, $keyName, $secret, $siteUrl): void {
                $settings->apiKeys()
                    ->where('provider', 'eurocross')
                    ->where('key_name', $keyName)
                    ->update(['active' => false]);

                $settings->apiKeys()->create([
                    'provider' => 'eurocross',
                    'key_name' => $keyName,
                    'encrypted_key' => $token,
                    'key_hash' => hash('sha256', $token),
                    'active' => true,
                ]);

                if ($secret !== '') {
                    $this->connectedSites->attach($settings->id, $siteUrl, $secret);
                }
            });

            return response()->json([
                'success' => true,
                'data' => $this->connectionPayload($settings, $token, $secret, $siteUrl),
            ]);
        } finally {
            $lock->release();
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function connectionPayload(EuroCrossSettings $settings, string $token, string $secret, string $siteUrl): array
    {
        $allowedLanguages = $this->translationService->allowedLanguages($settings);

        return [
            'api_key' => $token,
            'site_secret' => $secret,
            'site_url' => $siteUrl,
            'plan' => $settings->plan,
            'plan_name' => $settings->getPlanDisplayName(),
            'status' => $settings->accessStatus()['status'],
            'access_allowed' => $settings->hasActiveAccess(),
            'limits' => [
                'languages' => $settings->planLanguageLimit(),
                'words' => $settings->planWordLimit(),
                'rate_limit' => $settings->rateLimit(),
                'vat' => (string) config("payment.plans.{$settings->plan}.features.vat", 'basic'),
                'chatbot' => (bool) config("payment.plans.{$settings->plan}.features.chatbot", false),
            ],
            'allowed_languages' => $allowedLanguages,
            'languages' => $allowedLanguages,
            'all_languages' => $this->translationService->languages(),
        ];
    }

    private function settingsFor(User $user): EuroCrossSettings
    {
        return $user->euroCrossSettings()->firstOrCreate([
            'billing_email' => $user->email,
        ], [
            'locale' => 'en',
            'plan' => 'starter',
            'trial' => true,
            'trial_started_at' => now(),
            'trial_expires' => now()->addDays((int) config('payment.trial.days', 7)),
            'grace_expires' => now()->addDays((int) config('payment.trial.days', 7) + 3),
        ]);
    }

    /**
     * @return array{site_url: string, site_name: string, return_url: string, state: string, expires_at: int}
     */
    private function connection(Request $request): array
    {
        $connection = $request->session()->get(self::SESSION_KEY);

        if (! is_array($connection)
            || ! is_string($connection['site_url'] ?? null)
            || ! is_string($connection['return_url'] ?? null)
            || ! is_string($connection['state'] ?? null)
            || (int) ($connection['expires_at'] ?? 0) < now()->timestamp) {
            $request->session()->forget(self::SESSION_KEY);
            throw ValidationException::withMessages([
                'connect' => 'The connection request expired. Start again from your WordPress site.',
            ]);
        }

        /** @var array{site_url: string, site_name: string, return_url: string, state: string, expires_at: int} $connection */
        return $connection;
    }

    private function normalizeSiteUrl(string $url): string
    {
        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));

        if ($parts === false
            || ! in_array($scheme, ['http', 'https'], true)
            || empty($parts['host'])
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
            || (! app()->isLocal() && $scheme !== 'https')) {
            throw ValidationException::withMessages(['site_url' => 'Enter a valid public WordPress site URL.']);
        }

        $host = strtolower((string) $parts['host']);

        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.test')) {
            throw ValidationException::withMessages(['site_url' => 'Local site URLs cannot be connected.']);
        }

        if (filter_var($host, FILTER_VALIDATE_IP)
            && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            throw ValidationException::withMessages(['site_url' => 'Private IP addresses cannot be connected.']);
        }

        $port = isset($parts['port']) ? ':'.(int) $parts['port'] : '';
        $path = rtrim((string) ($parts['path'] ?? ''), '/');

        return $scheme.'://'.$host.$port.$path;
    }

    private function validateReturnUrl(string $returnUrl, string $siteUrl): string
    {
        $parts = parse_url($returnUrl);
        $siteParts = parse_url($siteUrl);

        if ($parts === false
            || $siteParts === false
            || strtolower((string) ($parts['scheme'] ?? '')) !== strtolower((string) ($siteParts['scheme'] ?? ''))
            || strtolower((string) ($parts['host'] ?? '')) !== strtolower((string) ($siteParts['host'] ?? ''))
            || (int) ($parts['port'] ?? 0) !== (int) ($siteParts['port'] ?? 0)
            || isset($parts['user'])
            || isset($parts['pass'])
            || isset($parts['fragment'])
            || ! str_ends_with(rtrim((string) ($parts['path'] ?? ''), '/'), 'admin-post.php')) {
            throw ValidationException::withMessages(['return_url' => 'The return URL must point to the site admin callback.']);
        }

        return $returnUrl;
    }

    /**
     * @param  array<string, string>  $query
     */
    private function appendQuery(string $url, array $query): string
    {
        $separator = str_contains($url, '?') ? '&' : '?';

        return $url.$separator.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    private function cacheKey(string $code): string
    {
        return 'eurocross:connect:'.hash('sha256', $code);
    }
}
