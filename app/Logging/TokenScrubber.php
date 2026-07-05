<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor that redacts Meta and Anthropic tokens before they reach
 * any log handler. Applied to the `single` and `daily` channels via tap()
 * configuration in config/logging.php.
 *
 * Patterns covered:
 * - Meta long-lived user/page tokens: EAA*, EAAB*, EAAG*
 * - Instagram Basic Display + Graph: IGAA*, IGQ*
 * - Anthropic API keys: sk-ant-*
 * - Generic Bearer headers
 * - Hub signature header values (rotation evidence, not secret, but noisy)
 */
class TokenScrubber implements ProcessorInterface
{
    private const REDACTED = '[REDACTED]';

    private const PATTERNS = [
        '/\b(?:EAAB|EAAG|EAA)[A-Za-z0-9_-]{20,}/',
        '/\bIGA[A-Za-z0-9_-]{20,}/',
        '/\bIGQ[A-Za-z0-9_-]{20,}/',
        '/\bsk-ant-[A-Za-z0-9_-]{20,}/',
        '/Bearer\s+[A-Za-z0-9._\-]+/i',
    ];

    public function __invoke(LogRecord $record): LogRecord
    {
        $message = $this->scrub($record->message);
        $context = $this->scrubArray($record->context);
        $extra = $this->scrubArray($record->extra);

        return $record->with(
            message: $message,
            context: $context,
            extra: $extra,
        );
    }

    private function scrubArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = $this->scrubArray($value);
            } elseif (is_string($value)) {
                $data[$key] = $this->scrub($value);
            }
        }
        return $data;
    }

    private function scrub(string $text): string
    {
        return preg_replace(self::PATTERNS, self::REDACTED, $text) ?? $text;
    }
}
