<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Authoritative token inspection. Calls Meta /debug_token and /me/permissions
 * and prints validity, expiry (the canonical answer to "does it expire?"),
 * granted scopes, and the IG/FB asset the token is bound to.
 *
 * Reads token + app secret from .env. Never accepts a token as a CLI arg.
 */
class NiaDebugToken extends Command
{
    protected $signature = 'nia:debug-token';

    protected $description = 'Inspect META_PAGE_ACCESS_TOKEN against Meta /debug_token (validity, expiry, scopes).';

    public function handle(): int
    {
        $token = config('services.meta.page_access_token');
        $appSecret = config('services.meta.app_secret');
        $appId = env('META_APP_ID', '1275953988014074');

        if (empty($token)) {
            $this->error('META_PAGE_ACCESS_TOKEN is empty. Add it to .env first.');
            return self::FAILURE;
        }

        $this->info('=== Token validity (graph.facebook.com/me) ===');
        $meOk = $this->callMe($token);

        if (empty($appSecret)) {
            $this->newLine();
            $this->warn('META_APP_SECRET is empty — skipping /debug_token (need app-token to call it).');
            $this->line('Add the rotated app secret to .env to see the canonical expiry value.');
            return $meOk ? self::SUCCESS : self::FAILURE;
        }

        $this->newLine();
        $this->info('=== Token debug (graph.facebook.com/debug_token) ===');
        $debugOk = $this->callDebugToken($token, $appId, $appSecret);

        $this->newLine();
        $this->info('=== Granted scopes (graph.facebook.com/me/permissions) ===');
        $this->callPermissions($token);

        return ($meOk && $debugOk) ? self::SUCCESS : self::FAILURE;
    }

    private function callMe(string $token): bool
    {
        try {
            $resp = Http::withOptions(['verify' => !app()->environment('local')])->timeout(10)->get('https://graph.facebook.com/v21.0/me', [
                'access_token' => $token,
                'fields'       => 'id,name',
            ]);

            if ($resp->failed()) {
                $this->line('  [FAIL] HTTP ' . $resp->status());
                $err = $resp->json('error.message', $resp->body());
                $this->line('  ' . substr((string) $err, 0, 300));
                return false;
            }

            $data = $resp->json();
            $this->line('  [OK]  Bound to: ' . ($data['name'] ?? '?') . ' (id=' . ($data['id'] ?? '?') . ')');
            return true;
        } catch (\Throwable $e) {
            $this->line('  [FAIL] ' . $e->getMessage());
            return false;
        }
    }

    private function callDebugToken(string $token, string $appId, string $appSecret): bool
    {
        try {
            $appAccess = $appId . '|' . $appSecret;
            $resp = Http::withOptions(['verify' => !app()->environment('local')])->timeout(10)->get('https://graph.facebook.com/debug_token', [
                'input_token'  => $token,
                'access_token' => $appAccess,
            ]);

            if ($resp->failed()) {
                $this->line('  [FAIL] HTTP ' . $resp->status());
                $err = $resp->json('error.message', $resp->body());
                $this->line('  ' . substr((string) $err, 0, 300));
                return false;
            }

            $d = $resp->json('data', []);
            $isValid = (bool) ($d['is_valid'] ?? false);
            $type = $d['type'] ?? '?';
            $expiresAt = (int) ($d['expires_at'] ?? -1);
            $dataExp = (int) ($d['data_access_expires_at'] ?? -1);
            $appName = $d['application'] ?? '?';
            $userId = $d['user_id'] ?? null;
            $profileId = $d['profile_id'] ?? null;

            $this->line('  is_valid                : ' . ($isValid ? 'true' : 'false'));
            $this->line('  type                    : ' . $type);
            $this->line('  application             : ' . $appName);
            if ($userId !== null) {
                $this->line('  user_id (System User)   : ' . $userId);
            }
            if ($profileId !== null) {
                $this->line('  profile_id (Page)       : ' . $profileId);
            }
            $this->line('  expires_at              : ' . $this->formatExpiry($expiresAt));
            $this->line('  data_access_expires_at  : ' . $this->formatExpiry($dataExp));

            if ($expiresAt === 0) {
                $this->info('  → Confirmed never-expires token.');
            } elseif ($expiresAt > 0) {
                $days = (int) round(($expiresAt - time()) / 86400);
                $this->warn("  → Token expires in ~{$days} days. NOT a never-expires token.");
            }

            return $isValid;
        } catch (\Throwable $e) {
            $this->line('  [FAIL] ' . $e->getMessage());
            return false;
        }
    }

    private function callPermissions(string $token): void
    {
        try {
            $resp = Http::withOptions(['verify' => !app()->environment('local')])->timeout(10)->get('https://graph.facebook.com/v21.0/me/permissions', [
                'access_token' => $token,
            ]);
            if ($resp->failed()) {
                $this->line('  [SKIP] HTTP ' . $resp->status() . ' — System User tokens may not expose this');
                return;
            }
            $perms = $resp->json('data', []);
            $granted = [];
            $declined = [];
            foreach ($perms as $p) {
                if (($p['status'] ?? '') === 'granted') {
                    $granted[] = $p['permission'];
                } else {
                    $declined[] = $p['permission'];
                }
            }
            sort($granted);
            $this->line('  Granted (' . count($granted) . '): ' . implode(', ', $granted));
            if (!empty($declined)) {
                $this->warn('  Declined (' . count($declined) . '): ' . implode(', ', $declined));
            }

            $required = ['instagram_manage_messages', 'pages_messaging', 'instagram_basic', 'pages_show_list'];
            $missing = array_diff($required, $granted);
            if (!empty($missing)) {
                $this->newLine();
                $this->warn('Missing recommended scopes for Nia DM: ' . implode(', ', $missing));
            }
        } catch (\Throwable $e) {
            $this->line('  [SKIP] ' . $e->getMessage());
        }
    }

    private function formatExpiry(int $ts): string
    {
        if ($ts === 0) {
            return '0 (never expires)';
        }
        if ($ts < 0) {
            return 'unknown';
        }
        return date('Y-m-d H:i:s', $ts) . ' UTC (' . $ts . ')';
    }
}
