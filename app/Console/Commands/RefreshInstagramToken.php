<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Refresh the long-lived Instagram Graph API token (extends another 60 days).
 * Run monthly — once the token is at least 24h old and not yet expired.
 *
 * Pairs with nia:exchange-token for first-time setup.
 * Schedule via app/Console/Kernel.php for hands-off rotation.
 */
class RefreshInstagramToken extends Command
{
    protected $signature = 'nia:refresh-token';

    protected $description = 'Refresh the long-lived IGAA token (extends ~60 days) and update .env.';

    public function handle(): int
    {
        $current = config('services.meta.page_access_token');
        if (empty($current)) {
            $this->error('META_PAGE_ACCESS_TOKEN is not set. Run nia:exchange-token first.');
            return self::FAILURE;
        }

        $this->info('Calling graph.instagram.com to refresh...');

        try {
            $response = Http::timeout(15)->get('https://graph.instagram.com/refresh_access_token', [
                'grant_type'   => 'ig_refresh_token',
                'access_token' => $current,
            ]);
        } catch (\Throwable $e) {
            $this->error('Network error: ' . $e->getMessage());
            return self::FAILURE;
        }

        if ($response->failed()) {
            $this->error('Refresh failed (HTTP ' . $response->status() . ').');
            $this->line($response->body());
            $this->newLine();
            $this->warn('Common causes:');
            $this->line(' - Token is less than 24h old (Meta requires age >= 24h before refresh).');
            $this->line(' - Token already expired (>60 days). Re-run nia:exchange-token with a fresh short-lived token.');
            return self::FAILURE;
        }

        $data = $response->json();
        $newToken = $data['access_token'] ?? null;
        $expiresIn = (int) ($data['expires_in'] ?? 0);

        if (empty($newToken)) {
            $this->error('No access_token in response.');
            $this->line(json_encode($data, JSON_PRETTY_PRINT));
            return self::FAILURE;
        }

        $days = (int) round($expiresIn / 86400);
        $this->info("Refreshed (now expires in ~{$days} days).");

        if (!$this->updateEnv('META_PAGE_ACCESS_TOKEN', $newToken)) {
            $this->error('Failed to update .env. Token NOT saved.');
            return self::FAILURE;
        }

        $this->info('Updated .env. Clearing config cache...');
        $this->call('config:clear');

        return self::SUCCESS;
    }

    private function updateEnv(string $key, string $value): bool
    {
        $path = base_path('.env');
        if (!is_writable($path)) {
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
