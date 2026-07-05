<?php

namespace App\DTOs\Messaging;

/**
 * Normalised inbound DM from Meta (Instagram, Facebook Messenger, WhatsApp).
 *
 * Pure data carrier. No DB, no HTTP. Constructed by MetaWebhookMapper.
 */
final class IncomingMessageDTO
{
    public function __construct(
        public readonly string $platform,
        public readonly string $senderPlatformId,
        public readonly string $text,
        public readonly ?string $messageId = null,
        public readonly ?string $senderName = null,
        public readonly ?int $timestampMs = null,
        public readonly array $attachments = [],
    ) {
    }

    public function isText(): bool
    {
        return trim($this->text) !== '';
    }

    public function toArray(): array
    {
        return [
            'platform'           => $this->platform,
            'sender_platform_id' => $this->senderPlatformId,
            'text'               => $this->text,
            'message_id'         => $this->messageId,
            'sender_name'        => $this->senderName,
            'timestamp_ms'       => $this->timestampMs,
            'attachments'        => $this->attachments,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            platform:         $data['platform'],
            senderPlatformId: $data['sender_platform_id'],
            text:             $data['text'],
            messageId:        $data['message_id'] ?? null,
            senderName:       $data['sender_name'] ?? null,
            timestampMs:      $data['timestamp_ms'] ?? null,
            attachments:      $data['attachments'] ?? [],
        );
    }
}
