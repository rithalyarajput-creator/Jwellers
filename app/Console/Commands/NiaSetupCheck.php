<?php

namespace App\Console\Commands;

use App\Models\Setting;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pre-flight check for the Nia Instagram bot. Audits .env, Anthropic, Meta
 * token validity, queue health, and the chatbot settings table.
 *
 * Reveals nothing sensitive — only PASS/FAIL plus value lengths and last 4
 * chars of tokens (for distinguishing one rotation from another).
 *
 * Run before letting Meta verify the webhook to catch missing config early.
 */
class NiaSetupCheck extends Command
{
    protected $signature = 'nia:setup-check {--ping : Ping Meta and Anthropic to validate live tokens}';

    protected $description = 'Audit Nia configuration and report what is missing or broken.';

    private int $failures = 0;

    public function handle(): int
    {
        $this->info('=== Nia setup check ===');
        $this->newLine();

        $this->checkEnv();
        $this->checkChatbotSettings();
        $this->checkQueue();

        if ($this->option('ping')) {
            $this->newLine();
            $this->info('--- Live API checks ---');
            $this->pingAnthropic();
            $this->pingMetaToken();
        }

        $this->newLine();
        if ($this->failures > 0) {
            $this->error("{$this->failures} check(s) failed.");
            return self::FAILURE;
        }

        $this->info('All checks passed.');
        return self::SUCCESS;
    }

    private function checkEnv(): void
    {
        $this->info('--- Environment (.env) ---');

        $checks = [
            'ANTHROPIC_API_KEY'             => ['min' => 30, 'prefix' => 'sk-ant-'],
            'META_APP_SECRET'               => ['min' => 16],
            'META_PAGE_ACCESS_TOKEN'        => ['min' => 50, 'prefix' => 'IGAA'],
            'META_VERIFY_TOKEN'             => ['min' => 8],
            'META_PAGE_ID'                  => ['min' => 5, 'numeric' => true],
            'META_WHATSAPP_PHONE_NUMBER_ID' => ['min' => 0, 'optional' => true],
        ];

        $configMap = [
            'ANTHROPIC_API_KEY'             => 'services.anthropic.key',
            'META_APP_SECRET'               => 'services.meta.app_secret',
            'META_PAGE_ACCESS_TOKEN'        => 'services.meta.page_access_token',
            'META_VERIFY_TOKEN'             => 'services.meta.verify_token',
            'META_PAGE_ID'                  => null,
            'META_WHATSAPP_PHONE_NUMBER_ID' => 'services.meta.whatsapp_phone_number_id',
        ];

        foreach ($checks as $key => $rule) {
            $value = $configMap[$key] !== null ? config($configMap[$key]) : env($key);
            $value = (string) ($value ?? '');
            $optional = $rule['optional'] ?? false;

            if ($value === '') {
                $msg = "  [{$this->token($optional ? 'SKIP' : 'FAIL')}] {$key} — empty";
                $this->line($msg);
                if (!$optional) {
                    $this->failures++;
                }
                continue;
            }

            $len = strlen($value);
            if ($len < ($rule['min'] ?? 0)) {
                $this->line("  [{$this->token('FAIL')}] {$key} — len={$len}, expected >= {$rule['min']}");
                $this->failures++;
                continue;
            }

            if (!empty($rule['prefix']) && !str_starts_with($value, $rule['prefix'])) {
                $this->line("  [{$this->token('WARN')}] {$key} — does not start with '{$rule['prefix']}' (len={$len})");
                continue;
            }

            if (!empty($rule['numeric']) && !ctype_digit($value)) {
                $this->line("  [{$this->token('FAIL')}] {$key} — expected numeric, got non-digits");
                $this->failures++;
                continue;
            }

            $tail = $len > 4 ? substr($value, -4) : '****';
            $this->line("  [{$this->token('OK')}  ] {$key} — len={$len}, ...{$tail}");
        }
    }

    private function checkChatbotSettings(): void
    {
        $this->newLine();
        $this->info('--- Chatbot settings (db) ---');

        try {
            $enabled = Setting::get('nia_enabled', null);
            $model = Setting::get('nia_model', null);
            $promptOverride = Setting::get('nia_system_prompt', '');

            $this->line('  nia_enabled       : ' . ($enabled === null ? '[FAIL] missing' : ($enabled ? 'true' : 'false')));
            $this->line('  nia_model         : ' . ($model ?: '[FAIL] missing'));
            $this->line('  nia_system_prompt : ' . (empty($promptOverride) ? 'using default' : 'custom (' . strlen($promptOverride) . ' chars)'));

            if ($enabled === null || empty($model)) {
                $this->failures++;
                $this->warn('  Run: php artisan db:seed --class=ChatbotSettingsSeeder');
            }
        } catch (\Throwable $e) {
            $this->line('  [FAIL] could not read settings: ' . $e->getMessage());
            $this->failures++;
        }
    }

    private function checkQueue(): void
    {
        $this->newLine();
        $this->info('--- Queue health ---');

        try {
            $jobs = DB::table('jobs')->where('queue', 'nia')->count();
            $failed = DB::table('failed_jobs')->count();
            $this->line("  jobs[queue=nia]   : {$jobs}");
            $this->line("  failed_jobs total : {$failed}");
            if ($failed > 0) {
                $this->warn('  Inspect: php artisan queue:failed');
            }
        } catch (\Throwable $e) {
            $this->line('  [FAIL] could not read queue tables: ' . $e->getMessage());
            $this->failures++;
        }
    }

    private function pingAnthropic(): void
    {
        $key = config('services.anthropic.key');
        if (empty($key)) {
            $this->line('  Anthropic         : [SKIP] no key');
            return;
        }

        try {
            $resp = Http::withOptions(['verify' => !app()->environment('local')])->timeout(10)->withHeaders([
                'x-api-key'         => $key,
                'anthropic-version' => '2023-06-01',
                'content-type'      => 'application/json',
            ])->post('https://api.anthropic.com/v1/messages', [
                'model'      => 'claude-haiku-4-5-20251001',
                'max_tokens' => 16,
                'messages'   => [['role' => 'user', 'content' => 'ping']],
            ]);

            $status = $resp->status();
            if ($resp->successful()) {
                $this->line("  Anthropic         : [{$this->token('OK')}  ] HTTP {$status}");
            } else {
                $this->line("  Anthropic         : [{$this->token('FAIL')}] HTTP {$status} — " . substr($resp->body(), 0, 120));
                $this->failures++;
            }
        } catch (\Throwable $e) {
            $this->line('  Anthropic         : [FAIL] ' . $e->getMessage());
            $this->failures++;
        }
    }

    private function pingMetaToken(): void
    {
        $token = config('services.meta.page_access_token');
        if (empty($token)) {
            $this->line('  Meta token        : [SKIP] no token');
            return;
        }

        try {
            // Lightweight identity call - works for both IG (graph.instagram.com)
            // and FB page (graph.facebook.com) tokens. We try IG first.
            $resp = Http::withOptions(['verify' => !app()->environment('local')])->timeout(10)->get('https://graph.instagram.com/me', [
                'fields'       => 'id,username',
                'access_token' => $token,
            ]);

            if ($resp->successful()) {
                $data = $resp->json();
                $username = $data['username'] ?? '?';
                $id = $data['id'] ?? '?';
                $this->line("  Meta token        : [{$this->token('OK')}  ] IG account @{$username} (id={$id})");
                return;
            }

            // Fall back to FB graph
            $resp2 = Http::withOptions(['verify' => !app()->environment('local')])->timeout(10)->get('https://graph.facebook.com/v21.0/me', [
                'access_token' => $token,
            ]);

            if ($resp2->successful()) {
                $data = $resp2->json();
                $name = $data['name'] ?? '?';
                $this->line("  Meta token        : [{$this->token('OK')}  ] FB page '{$name}'");
                return;
            }

            $this->line('  Meta token        : [FAIL] both graph.instagram.com and graph.facebook.com rejected');
            $this->line('    IG response: ' . substr($resp->body(), 0, 200));
            $this->failures++;
        } catch (\Throwable $e) {
            $this->line('  Meta token        : [FAIL] ' . $e->getMessage());
            $this->failures++;
        }
    }

    private function token(string $label): string
    {
        return match ($label) {
            'OK'   => '<fg=green>OK</>',
            'FAIL' => '<fg=red>FAIL</>',
            'WARN' => '<fg=yellow>WARN</>',
            'SKIP' => '<fg=gray>SKIP</>',
            default => $label,
        };
    }
}
