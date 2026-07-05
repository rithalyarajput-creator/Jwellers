<?php

namespace App\DTOs\Messaging;

/**
 * Customer intent extracted from a single inbound message.
 *
 * Produced by IntentExtractor (Haiku call). Consumed by ProductLookupService
 * and ClaudeService tool-use loop.
 */
final class IntentDTO
{
    public const INTENT_BROWSE       = 'browse';
    public const INTENT_SIZING       = 'sizing';
    public const INTENT_PRICE        = 'price';
    public const INTENT_ORDER_STATUS = 'order_status';
    public const INTENT_RETURNS      = 'returns';
    public const INTENT_HUMAN        = 'human';
    public const INTENT_SMALL_TALK   = 'small_talk';
    public const INTENT_OTHER        = 'other';

    public function __construct(
        public readonly string $intent,
        public readonly array $filters = [],
        public readonly bool $askForMore = false,
        public readonly float $confidence = 0.0,
    ) {
    }

    public function needsHumanHandoff(): bool
    {
        return in_array($this->intent, [
            self::INTENT_HUMAN,
            self::INTENT_ORDER_STATUS,
            self::INTENT_RETURNS,
        ], true);
    }

    public function needsProductLookup(): bool
    {
        return in_array($this->intent, [
            self::INTENT_BROWSE,
            self::INTENT_SIZING,
            self::INTENT_PRICE,
        ], true);
    }

    public function toArray(): array
    {
        return [
            'intent'       => $this->intent,
            'filters'      => $this->filters,
            'ask_for_more' => $this->askForMore,
            'confidence'   => $this->confidence,
        ];
    }
}
