<?php

namespace App\Mappers;

use App\DTOs\Messaging\IncomingMessageDTO;

/**
 * Map raw Meta webhook payloads to IncomingMessageDTO.
 *
 * Pure functions only - no DB, no HTTP, no Laravel facades. Trivially testable.
 *
 * Reference payload shapes:
 * - Instagram + FB Messenger: entry[].messaging[]
 * - WhatsApp Business:        entry[].changes[].value.messages[]
 */
final class MetaWebhookMapper
{
    /**
     * Walk the full webhook payload and return one DTO per processable message.
     * Skips echoes, non-text events, and malformed entries.
     *
     * @return IncomingMessageDTO[]
     */
    public static function fromPayload(array $payload): array
    {
        $object = $payload['object'] ?? '';
        $entries = $payload['entry'] ?? [];
        $out = [];

        foreach ($entries as $entry) {
            // IG + FB Messenger events
            foreach (($entry['messaging'] ?? []) as $event) {
                $dto = self::fromMessagingEvent($event, $object);
                if ($dto) {
                    $out[] = $dto;
                }
            }

            // WhatsApp Business events
            foreach (($entry['changes'] ?? []) as $change) {
                if (($change['field'] ?? '') !== 'messages') {
                    continue;
                }
                $value = $change['value'] ?? [];
                foreach (self::fromWhatsAppValue($value) as $dto) {
                    $out[] = $dto;
                }
            }
        }

        return $out;
    }

    /**
     * IG and FB Messenger share the messaging event shape. The webhook object
     * field disambiguates platform ('instagram' vs 'page').
     */
    public static function fromMessagingEvent(array $event, string $webhookObject): ?IncomingMessageDTO
    {
        // Skip echoes (messages we sent).
        if (!empty($event['message']['is_echo'])) {
            return null;
        }

        $text = $event['message']['text'] ?? '';
        if (trim($text) === '') {
            // Could be sticker, image, attachment-only — out of scope for now.
            return null;
        }

        $senderId = $event['sender']['id'] ?? null;
        if (!$senderId) {
            return null;
        }

        $platform = ($webhookObject === 'instagram') ? 'instagram' : 'facebook';

        return new IncomingMessageDTO(
            platform:         $platform,
            senderPlatformId: (string) $senderId,
            text:             $text,
            messageId:        $event['message']['mid'] ?? null,
            senderName:       null,
            timestampMs:      isset($event['timestamp']) ? (int) $event['timestamp'] : null,
            attachments:      $event['message']['attachments'] ?? [],
        );
    }

    /**
     * WhatsApp value contains both a messages[] array and a contacts[] array.
     * We pair them so we can capture sender display name.
     *
     * @return IncomingMessageDTO[]
     */
    public static function fromWhatsAppValue(array $value): array
    {
        $messages = $value['messages'] ?? [];
        $contactsByWaId = [];
        foreach (($value['contacts'] ?? []) as $contact) {
            if (isset($contact['wa_id'])) {
                $contactsByWaId[$contact['wa_id']] = $contact['profile']['name'] ?? null;
            }
        }

        $out = [];
        foreach ($messages as $msg) {
            $dto = self::fromWhatsAppMessage($msg, $contactsByWaId);
            if ($dto) {
                $out[] = $dto;
            }
        }
        return $out;
    }

    public static function fromWhatsAppMessage(array $msg, array $contactsByWaId = []): ?IncomingMessageDTO
    {
        if (($msg['type'] ?? '') !== 'text') {
            return null;
        }

        $from = $msg['from'] ?? null;
        $text = $msg['text']['body'] ?? '';

        if (!$from || trim($text) === '') {
            return null;
        }

        return new IncomingMessageDTO(
            platform:         'whatsapp',
            senderPlatformId: (string) $from,
            text:             $text,
            messageId:        $msg['id'] ?? null,
            senderName:       $contactsByWaId[$from] ?? null,
            timestampMs:      isset($msg['timestamp']) ? ((int) $msg['timestamp']) * 1000 : null,
            attachments:      [],
        );
    }
}
