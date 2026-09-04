<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 2 — Application & Document Management
 * Author: Lee Kar How
 */

namespace App\Services\Chatbot;

/**
 * One answer from the assistant.
 *
 * A value object rather than a bare string so an intent can offer follow-up
 * questions and a deep link alongside its text, and the controller does not have
 * to know how any of it was assembled.
 */
final readonly class ChatReply
{
    /**
     * @param  string  $message      plain text; never HTML, so the client can insert
     *                               it with textContent and XSS is impossible
     * @param  string[]  $suggestions follow-up questions offered as buttons
     */
    public function __construct(
        public string $message,
        public array $suggestions = [],
        public ?string $linkUrl = null,
        public ?string $linkLabel = null,
        public string $intent = 'unknown',
    ) {}

    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'suggestions' => $this->suggestions,
            'link' => $this->linkUrl ? ['url' => $this->linkUrl, 'label' => $this->linkLabel] : null,
            'intent' => $this->intent,
        ];
    }
}
