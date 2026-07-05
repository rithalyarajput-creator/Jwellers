<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * Subscribe the connected Instagram business account to the Meta DM webhook
 * fields Nia needs. Idempotent — Meta accepts repeat calls.
 *
 * Run after rotating the page access token, or once during initial setup.
 */
class SubscribeInstagramWebhook extends Command
{
    protected $signature = 'nia:subscribe-ig
                            {--page-id= : Override the IG/FB page id (defaults to META_PAGE_ID env)}';

    protected $description = 'Subscribe the IG business account to Nia webhook fields (messages, postbacks, reactions, referrals).';

    private const FIELDS = [
        'messages',
        'messaging_postbacks',
        'message_reactions',
        'messaging_referrals',
    ];

    public function handle(): int
    {
        $token = config('services.meta.page_access_token');
        if (empty($token)) {
            $this->error('META_PAGE_ACCESS_TOKEN is not set in .env');
            return self::FAILURE;
        }

        $pageId = $this->option('page-id') ?: env('META_PAGE_ID');
        if (empty($pageId)) {
            $this->error('Provide --page-id or set META_PAGE_ID in .env');
            return self::FAILURE;
        }

        $this->info("Subscribing page {$pageId} to fields: " . implode(', ', self::FIELDS));

        $response = Http::withOptions(['verify' => !app()->environment('local')])->asForm()->post(
            "https://graph.facebook.com/v21.0/{$pageId}/subscribed_apps",
            [
                'subscribed_fields' => implode(',', self::FIELDS),
                'access_token'      => $token,
            ],
        );

        if ($response->failed()) {
            $this->error('Subscribe failed: ' . $response->status());
            $this->line($response->body());
            return self::FAILURE;
        }

        $this->info('Subscribed. Response: ' . $response->body());
        return self::SUCCESS;
    }
}
