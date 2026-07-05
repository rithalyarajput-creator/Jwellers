<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Set META_APP_SECRET in .env without the value passing through chat or shell
 * history. Validates against Meta `/me?access_token=app_id|secret` BEFORE
 * writing — so a wrong value is rejected up front instead of silently breaking
 * webhook signature verification and Send API calls.
 */
class SetAppSecret extends Command
{
    protected $signature = 'nia:set-app-secret';

    protected $description = 'Securely set META_APP_SECRET in .env, validate against Meta, clear config.';

    public function handle(): int
    {
        $appId = env('META_APP_ID', '1275953988014074');
        if (empty($appId)) {
            $this->error('META_APP_ID is not set in .env');
            return self::FAILURE;
        }

        $this->info("Validating new app secret against Meta for app_id={$appId}...");
        $secret = $this->secret('Paste the CURRENT app secret from Meta dashboard (input is hidden)');
        if (empty($secret)) {
            $this->error('No value provided.');
            return self::FAILURE;
        }
        $secret = trim($secret);

        $appAccess = $appId . '|' . $secret;

        $resp = Http::withOptions(['verify' => !app()->environment('local')])
            ->timeout(15)
            ->get('https://graph.facebook.com/v21.0/' . $appId, [
                'access_token' => $appAccess,
                'fields'       => 'id,name',
            ]);

        if ($resp->failed()) {
            $err = $resp->json('error.message', $resp->body());
            $this->error('Meta rejected the secret: ' . substr($err, 0, 200));
            $this->newLine();
            $this->warn('No change made to .env. Get the CURRENT secret from:');
            $this->line('  https://developers.facebook.com/apps/' . $appId . '/settings/basic/');
            $this->line('  → App Secret → Show (will prompt your Facebook password)');
            return self::FAILURE;
        }

        $appName = $resp->json('name', '?');
        $this->info("Validated. App name on Meta side: {$appName}");

        if (!$this->updateEnv('META_APP_SECRET', $secret)) {
            $this->error('Failed to write .env. Check permissions.');
            return self::FAILURE;
        }

        $this->info('Updated .env. Clearing config cache...');
        $this->call('config:clear');

        $this->newLine();
        $this->info('Done. Test the change:');
        $this->line('  php artisan nia:debug-token');
        $this->line('  (debug_token should now succeed with valid app-token combo)');

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
