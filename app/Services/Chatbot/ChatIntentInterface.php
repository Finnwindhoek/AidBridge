<?php

namespace App\Services\Chatbot;

use App\Models\User;

/**
 * STRATEGY PATTERN — the help assistant.
 *
 * One question can be answered by exactly one intent. Each intent scores its own
 * confidence for a question and, if chosen, composes the reply. Adding a new
 * topic means writing one class and registering it; no existing intent and not
 * the service itself is edited.
 *
 * This is the same shape as the eligibility strategies in
 * App\Services\Eligibility, deliberately — the two problems are the same one.
 */
interface ChatIntentInterface
{
    /** Stable identifier, used in tests and in the JSON response. */
    public function name(): string;

    /**
     * Confidence that this intent answers the question, from 0.0 to 1.0.
     * The service picks the highest scorer above its threshold.
     */
    public function scoreFor(string $question): float;

    /**
     * Builds the answer. $user is the authenticated asker, so an intent can
     * personalise from their own records — and only ever theirs.
     */
    public function respond(string $question, User $user): ChatReply;
}
