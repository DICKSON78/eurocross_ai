<?php

namespace App\Http\Controllers;

use App\Models\EuroCrossChatMessage;
use App\Models\EuroCrossSettings;
use App\Models\EuroCrossTransaction;
use App\Models\EuroCrossTranslationUsage;
use App\Models\EuroCrossVatSettings;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ClientDashboardController extends Controller
{
    public function index(Request $request, string $module = 'overview'): View
    {
        $settings = $this->settings();
        $module = in_array($module, [
            'overview', 'translation', 'vat', 'billing', 'keys', 'chatbot', 'settings',
        ], true) ? $module : 'overview';

        $data = match ($module) {
            'translation' => $this->translationData($settings),
            'vat' => $this->vatData($settings),
            'billing' => $this->billingData($settings),
            'keys' => ['keys' => $settings->apiKeys()->latest()->get()],
            'chatbot' => ['messages' => $this->chatMessages($settings)],
            'settings' => ['vatSettings' => EuroCrossVatSettings::firstOrCreate(['shop_id' => $settings->id])],
            default => $this->overviewData($settings),
        };

        return view('dashboard', array_merge([
            'module' => $module,
            'settings' => $settings,
            'user' => Auth::user(),
            'modules' => [
                'overview' => ['fa-gauge-high', 'Overview'],
                'translation' => ['fa-language', 'Translation'],
                'vat' => ['fa-file-invoice', 'VAT & Compliance'],
                'billing' => ['fa-credit-card', 'Billing'],
                'keys' => ['fa-key', 'API Keys'],
                'chatbot' => ['fa-robot', 'Chatbot'],
                'settings' => ['fa-gear', 'Settings'],
            ],
        ], $data));
    }

    public function generateKey(Request $request): RedirectResponse
    {
        $settings = $this->settings();
        $token = 'ecx_live_'.Str::random(48);

        $settings->apiKeys()->create([
            'provider' => 'eurocross',
            'key_name' => 'WordPress plugin',
            'encrypted_key' => $token,
            'key_hash' => hash('sha256', $token),
            'active' => true,
        ]);

        return redirect()->route('dashboard.module', 'keys')
            ->with('new_api_key', $token);
    }

    private function settings(): EuroCrossSettings
    {
        /** @var User $user */
        $user = Auth::user();

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

    /** @return array<string, mixed> */
    private function overviewData(EuroCrossSettings $settings): array
    {
        $usage = EuroCrossTranslationUsage::where('shop_id', $settings->id)
            ->whereBetween('usage_date', [now()->startOfMonth()->toDateString(), now()->endOfMonth()->toDateString()])
            ->selectRaw('COALESCE(SUM(tokens_used),0) tokens, COALESCE(SUM(strings_translated),0) strings, COALESCE(SUM(words_translated),0) words, COALESCE(SUM(ai_cost),0) cost')
            ->first();

        return [
            'usage' => $usage,
            'access' => $settings->accessStatus(),
            'wordsUsed' => (int) ($usage->words ?? 0),
            'wordsLimit' => $settings->planWordLimit(),
        ];
    }

    /** @return array<string, mixed> */
    private function translationData(EuroCrossSettings $settings): array
    {
        return [
            'usage' => EuroCrossTranslationUsage::where('shop_id', $settings->id)
                ->latest('usage_date')->limit(30)->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function vatData(EuroCrossSettings $settings): array
    {
        $vatSettings = EuroCrossVatSettings::firstOrCreate(['shop_id' => $settings->id]);

        return [
            'vatSettings' => $vatSettings,
            'transactions' => EuroCrossTransaction::where('shop_id', $settings->id)->latest()->limit(20)->get(),
        ];
    }

    /** @return array<string, mixed> */
    private function billingData(EuroCrossSettings $settings): array
    {
        return [
            'transactions' => EuroCrossTransaction::where('shop_id', $settings->id)->latest()->limit(30)->get(),
        ];
    }

    /** @return Collection */
    private function chatMessages(EuroCrossSettings $settings)
    {
        return EuroCrossChatMessage::where('shop_id', $settings->id)->latest()->limit(50)->get();
    }
}
