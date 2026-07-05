<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Exchange a short-lived Instagram Graph API token (IGAA prefix, ~1h lifetime)
 * for a long-lived token (~60 days). Writes the result back to .env in place
 * and clears config cache.
 *
 * Run once after generating a fresh token in Meta dashboard.
 * For renewing an existing long-lived token before expiry, use nia:refresh-token.
 */
class ExchangeInstagramToken extends Command
{
    protected $signature = 'nia:exchange-token';

    protected $description = 'Exchange a short-lived IGAA token for a 60-day long-lived token and update .env.';

    public function handle(): int
    {
        $appSecret = config('services.meta.app_secret');
        if (empty($appSecret)) {
            $this->error('META_APP_SECRET is not set in .env. Rotate and add it first.');
            return self::FAILURE;
        }

        // secret() hides input from terminal echo and shell history.
        $shortToken = $this->secret('Paste the short-lived IGAA token (input is hidden)');
        if (empty($shortToken)) {
            $this->error('No token provided.');
            return self::FAILURE;
        }

        $shortToken = trim($shortToken);
        if (!str_starts_with($shortToken, 'IGAA')) {
            $this->warn('Token does not start with "IGAA" — continuing anyway, but check this is the right token.');
        }

        $this->info('Calling graph.instagram.com to exchange...');

        try {
            $response = Http::timeout(15)->get('https://graph.instagram.com/access_token', [
                'grant_type'    => 'ig_exchange_token',
                'client_secret' => $appSecret,
                'access_token'  => $shortToken,
            ]);
        } catch (\Throwable $e) {
            $this->error('Network error: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($response->failed()) {
            $this->error('Exchange failed (HTTP ' . $response->status() . ').');
            $this->line($response->body());
            $this->newLine();
            $this->warn('Common causes:');
            $this->line(' - Short-lived token already expired (1h limit) — generate a new one.');
            $this->line(' - App secret in .env does not match the app the token was issued for.');
            $this->line(' - Token was already exchanged once (cannot exchange twice).');
            return self::FAILURE;
        }

        $data = $response->json();
        $longToken = $data['access_token'] ?? null;
        $expiresIn = (int) ($data['expires_in'] ?? 0);

        if (empty($longToken)) {
            $this->error('No access_token in response.');
            $this->line(json_encode($data, JSON_PRETTY_PRINT));
            return self::FAILURE;
        }

        $days = (int) round($expiresIn / 86400);
        $this->info("Got long-lived token (expires in ~{$days} days).");

        if (!$this->updateEnv('META_PAGE_ACCESS_TOKEN', $longToken)) {
            $this->error('Failed to update .env. Token NOT saved. Re-run the command after fixing file permissions.');
            return self::FAILURE;
        }

        $this->info('Updated .env. Clearing config cache...');
        $this->call('config:clear');

        $this->newLine();
        $this->info("Done. Mark a calendar reminder ~50 days from today to run: php artisan nia:refresh-token");

        return self::SUCCESS;
    }

    /**
     * Replace (or append) a key in .env without disturbing other lines.
     */
    private function updateEnv(string $key, string $value): bool
    {
        $path = base_path('.env');
        if (!is_writable($path)) {
            $this->error(".env is not writable: {$path}");
            return false;
        }

        $content = file_get_contents($path);
        $line = $key . '=' . $value;

        if (preg_match('/^' . preg_quote($key, '/') . '=.*$/m', $content)) {
            $content = preg_replace('/^' . preg_quote($key, '/') . '=.*$/m', $line, $content);
        } else {
            $content = rtrim($content, "\r\n") . PHP_EOL . $line . PHP_EOL;
        }

        return file_put_contents($path, $content) !== false;
    }
}
