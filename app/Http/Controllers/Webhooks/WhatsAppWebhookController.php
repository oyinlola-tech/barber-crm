<?php

namespace App\Http\Controllers\Webhooks;

use App\Configs\WhatsAppConfig;
use App\Http\Controllers\Controller;
use App\Services\Webhooks\Commands\ReceiveWhatsAppWebhookCommand;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request, WhatsAppConfig $configuration): Response
    {
        $query = $request->query->all();
        $mode = $query['hub.mode'] ?? $query['hub_mode'] ?? null;
        $token = $query['hub.verify_token'] ?? $query['hub_verify_token'] ?? null;
        $challenge = $query['hub.challenge'] ?? $query['hub_challenge'] ?? null;
        $expected = $configuration->verifyToken();

        if ($mode === 'subscribe' && is_string($expected) && $expected !== '' && is_string($token) && hash_equals($expected, $token) && is_string($challenge)) {
            return response($challenge, 200)->header('Content-Type', 'text/plain');
        }

        return response()->json(['message' => 'Webhook verification failed.'], 403);
    }

    public function receive(Request $request, ReceiveWhatsAppWebhookCommand $command): JsonResponse
    {
        $result = $command->execute(
            $request->getContent(),
            $request->header('X-Hub-Signature-256'),
        );

        return response()->json($result->body, $result->status);
    }
}
