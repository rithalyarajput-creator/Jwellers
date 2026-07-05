<?php

namespace App\DTOs\Messaging;

/**
 * Normalised outbound reply for Meta Send API.
 *
 * Carries either plain text or a product carousel (generic template).
 * MessagingService converts this into the wire-format payload.
 */
final class OutgoingReplyDTO
{
    public function __construct(
        public readonly string $platform,
        public readonly string $recipientPlatformId,
        public readonly string $text,
        /** @var ProductMatchDTO[] */
        public readonly array $products = [],
        public readonly ?string $messageTag = null,
    ) {
    }

    public function hasCarousel(): bool
    {
        return !empty($this->products);
    }
}
