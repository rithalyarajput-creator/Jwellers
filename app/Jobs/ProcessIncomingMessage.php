<?php

namespace App\Jobs;

use App\DTOs\Messaging\IncomingMessageDTO;
use App\Services\MessagingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Async processing of one inbound DM. Webhook controller dispatches this and
 * returns 200 immediately so Meta does not retry on slow Claude calls.
 *
 * Configured with conservative retries because the message is already stored
 * once and external Claude/Meta failures are usually transient.
 */
class ProcessIncomingMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;
    public int $timeout = 60;
    public int $backoff = 10;

    public function __construct(
        private readonly array $dtoData,
    ) {
        $this->onQueue('nia');
    }

    public function handle(MessagingService $messaging): void
    {
        $dto = IncomingMessageDTO::fromArray($this->dtoData);
        $messaging->processIncoming($dto);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Nia: ProcessIncomingMessage permanently failed', [
            'platform'   => $this->dtoData['platform'] ?? null,
            'message_id' => $this->dtoData['message_id'] ?? null,
            'error'      => $e->getMessage(),
        ]);
    }
}
