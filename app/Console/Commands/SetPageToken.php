<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Set META_PAGE_ACCESS_TOKEN in .env without the value passing through chat
 * or shell history. Validates against Meta `/me?fields=id,name,...` BEFORE
 * writing — so a wrong/expired token is rejected up front instead of silently
 * breaking message send and webhook subscription.
 *
 * Run after regenerating the System User token (e.g. when adding new scopes
 * like pages_messaging or pages_manage_metadata).
 */
class SetPageToken extends Command
{
    protected $signature = 'nia:set-page-token';

    protected $description = 'Securely set META_PAGE_ACCESS_TOKEN in .env, validate against Meta, clear config.';

    public function handle(): int
    {
        $token = $this->secret('Paste the new Meta page access token (System User EAA, input is hidden)');
        if (empty($token)) {
            $this->error('No value provided.');
            return self::FAILURE;
        }
        $token = trim($token);

        if (!preg_match('/^EAA[A-Za-z0-9_-]{20,}$/', $token) && !preg_match('/^IGAA[A-Za-z0-9_-]{20,}$/', $token)) {
            $this->warn('Token does not match expected EAA/IGAA pattern — continuing but check that you pasted the right value.');
        }

        $this->info('Validating token against Meta...');
        $resp = Http::withOptions(['verify' => !app()->environment('local')])
            ->timeout(15)
            ->get('https://graph.facebook.com/v21.0/me', [
                'access_token' => $token,
                'fields'       => 'id,name',
            ]);

        if ($resp->failed()) {
            $err = $resp->json('error.message', $resp->body());
            $this->error('Meta rejected the token: ' . substr((string) $err, 0, 200));
            $this->warn('No change made to .env. Get a fresh token from Business Settings → System Users.');
            return self::FAILURE;
        }

        $bound = $resp->json();
        $this->info(sprintf('Validated. Token is bound to: %s (id=%s)',
            $bound['name'] ?? '?', $bound['id'] ?? '?'));

        $this->info('Listing first 5 IG-linked pages this token can see...');
        $pagesResp = Http::withOptions(['verify' => !app()->environment('local')])
            ->timeout(10)
            ->get('https://graph.facebook.com/v21.0/me/accounts', [
                'access_token' => $token,
                'fields'       => 'id,name,instagram_business_account{username}',
                'limit'        => 5,
            ]);
        if ($pagesResp->ok()) {
            foreach ($pagesResp->json('data', []) as $p) {
                $ig = $p['instagram_business_account']['username'] ?? null;
                $this->line(sprintf('  %s (id=%s)%s',
                    $p['name'] ?? '?',
                    $p['id'] ?? '?',
                    $ig ? " → IG @{$ig}" : ' [no IG]'
                ));
            }
        }

        if (!$this->updateEnv('META_PAGE_ACCESS_TOKEN', $token)) {
            $this->error('Failed to write .env. Check permissions.');
            return self::FAILURE;
        }

        $this->info('Updated .env. Clearing config cache...');
        $this->call('config:clear');

        $this->newLine();
        $this->info('Next steps:');
        $this->line('  1. php artisan nia:debug-token   # confirm scopes include pages_messaging + pages_manage_metadata');
        $this->line('  2. php artisan nia:subscribe-ig --page-id=' . config('services.meta.page_id', '<your-page-id>'));
        $this->line('  3. Send a real DM to @jwellers and watch lead_chats');

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
