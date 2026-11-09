<?php

namespace Bahari\Laravel\Console;

use Bahari\Laravel\BahariManager;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Registers this app with Bahari using a one-time setup or update code from Connected apps, and writes the returned
 * credentials to .env without printing the secret.
 */
class ConnectCommand extends Command
{
    protected $signature = 'bahari:connect {code : Setup or update code from Bahari admin › Connected apps}
        {--admin-url= : Admin origin when the admin panel runs on another host, for example https://admin.example.com}
        {--no-admin : The app has no admin sign-in}
        {--billing-webhook= : HTTPS URL that receives Bahari billing events}
        {--logo= : HTTPS URL of a square logo}';

    protected $description = 'Connect this app to Bahari Accounts with a setup code';

    public function handle(BahariManager $bahari): int
    {
        $appUrl = rtrim((string) config('app.url'), '/');
        $adminOrigin = rtrim((string) ($this->option('admin-url') ?: $appUrl), '/');
        $onAdmin = fn (string $url): string => $adminOrigin.(parse_url($url, PHP_URL_PATH) ?: '/');
        $hasAdmin = ! $this->option('no-admin') && array_key_exists('admin', config('bahari.kinds', []));
        $kinds = config('bahari.kinds', []);
        $payload = array_filter([
            'setup_code' => $this->argument('code'),
            'app_url' => $appUrl,
            'admin_url' => $hasAdmin && $adminOrigin !== $appUrl ? $adminOrigin : null,
            'client_callback' => route($bahari->route('callback'), ['kind' => 'client']),
            'admin_callback' => $hasAdmin ? $onAdmin(route($bahari->route('callback'), ['kind' => 'admin'])) : null,
            'backchannel_logout' => route($bahari->route('backchannel')),
            'post_logout_redirect' => config('bahari.post_logout_redirect') ?: url('/'),
            'client_panel' => url($kinds['client']['path'] ?? '/'),
            'admin_panel' => $hasAdmin ? $adminOrigin.'/'.ltrim((string) ($kinds['admin']['path'] ?? '/admin'), '/') : null,
            'billing_webhook' => $this->option('billing-webhook'),
            'logo_url' => $this->option('logo'),
            'framework' => 'laravel',
        ]);
        $endpoint = $bahari->client()->discovery()->get('api.apps_connect') ?: $bahari->client()->config()->issuer.'/api/v1/apps/connect';
        $response = Http::acceptJson()->asJson()->withoutRedirecting()->timeout(20)->withHeaders(['X-Bahari-SDK' => \Bahari\Laravel\Sdk::PACKAGE.'/'.\Bahari\Laravel\Sdk::VERSION])->post($endpoint, $payload);
        if (! $response->successful()) {
            $this->error('Bahari refused the request: '.($response->json('message') ?: 'HTTP '.$response->status()));
            foreach ((array) $response->json('errors', []) as $field => $messages) {
                $this->line('  '.$field.': '.implode(' ', (array) $messages));
            }

            return self::FAILURE;
        }
        $values = array_filter([
            'BAHARI_ACCOUNTS_ISSUER' => $response->json('issuer'),
            'BAHARI_CLIENT_ID' => $response->json('client_id'),
            'BAHARI_CLIENT_SECRET' => $response->json('client_secret'),
            'BAHARI_PLATFORM_KEY' => $response->json('platform_key'),
            'BAHARI_WEBHOOK_SECRET' => $response->json('billing_webhook_secret'),
        ], fn ($value): bool => is_string($value) && $value !== '' && ! preg_match('/[\r\n"]/', $value));
        $this->writeEnv($values);
        $this->info($response->json('status') === 'updated'
            ? 'Updated. Bahari recorded the app\'s URLs; the existing secret is unchanged.'
            : 'Connected (client ID '.$response->json('client_id').'). The credentials are in .env. Sign-in works once an administrator approves the app.');
        $this->line('Run php artisan config:clear (or optimize) to load them.');

        return self::SUCCESS;
    }

    private function writeEnv(array $values): void
    {
        $path = $this->laravel->environmentFilePath();
        $env = is_file($path) ? (string) file_get_contents($path) : '';
        foreach ($values as $key => $value) {
            $line = $key.'="'.$value.'"';
            $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
            $env = preg_match($pattern, $env) ? preg_replace_callback($pattern, fn () => $line, $env) : rtrim($env).PHP_EOL.$line.PHP_EOL;
        }
        file_put_contents($path, $env);
    }
}
