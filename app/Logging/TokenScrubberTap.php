<?php

namespace App\Logging;

use Illuminate\Log\Logger;

/**
 * Tap class registered against log channels via config/logging.php.
 * Pushes the TokenScrubber processor onto every Monolog handler in the channel.
 */
class TokenScrubberTap
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            if (method_exists($handler, 'pushProcessor')) {
                $handler->pushProcessor(new TokenScrubber());
            }
        }
    }
}
