<?php

/**
 * AidBridge — Welfare Aid & Cash Assistance Distribution Management System
 *
 * Module 2 — Application & Document Management
 * Author: Lee Kar How
 */

namespace App\Http\Controllers;

use App\Services\Chatbot\ChatbotService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON endpoint behind the help assistant widget.
 *
 * Questions are answered from the authenticated user's own records and are
 * deliberately NOT persisted: free text typed by an applicant may contain
 * personal details, and storing it would put PII somewhere the audit trail's
 * redaction rules do not reach.
 */
class AssistantController extends Controller
{
    public function __construct(private readonly ChatbotService $chatbot) {}

    public function ask(Request $request): JsonResponse
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:'.ChatbotService::MAX_QUESTION_LENGTH],
        ]);

        $reply = $this->chatbot->ask($data['question'], $request->user());

        return response()->json($reply->toArray());
    }
}
