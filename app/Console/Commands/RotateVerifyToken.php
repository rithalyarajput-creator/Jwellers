<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Generate a cryptographically random Meta webhook verify token and write it
 * to .env in place. Prints the new value ONCE so the operator can paste the
 * same value into Meta dashboard's Verify Token field. After that, the value
 * lives only in .env (and Meta's webhook config) — never in code or chat.
 *
 * Run on first setup, or any time the verify token is suspected leaked.
 */
class RotateVerifyToken extends Command
{
    protected $signature = 'nia:rotate-verify-token
                            {--length=48 : Length of the generated token (default 48 chars)}
                            {--show : Print the new value to stdout (required for first setup)}';

    protected $description = 'Generate a fresh Meta webhook verify token and write it to .env.';

    public function handle(): int
    {
        $length = max(16, min(128, (int) $this->option('length')));
        $token = Str::random($length);

        if (!$this->updateEnv('META_VERIFY_TOKEN', $token)) {
            $this->error('Could not write to .env. Check file permissions.');
            return self::FAILURE;
        }

        $this->info('Generated and saved a new META_VERIFY_TOKEN.');

        if ($this->option('show')) {
            $this->newLine();
            $this->warn('=== Paste THIS into Meta dashboard "Verify Token" field ===');
            $this->line($token);
            $this->warn('============================================================');
            $this->newLine();
            $this->line('After Meta verifies the webhook, you do not need this value again.');
            $this->line('It will not be shown again unless you re-run with --show.');
        } else {
            $this->newLine();
            $this->line('Re-run with --show if you need to read the value to paste into Meta dashboard.');
        }

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
