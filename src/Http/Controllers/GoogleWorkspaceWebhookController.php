<?php

namespace Bu\Gws\Http\Controllers;

use Bu\Gws\Jobs\HandleGoogleWorkspaceWebhook;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class GoogleWorkspaceWebhookController extends Controller
{
    public function handle(Request $request)
    {
        try {
            Log::info('GWS webhook received', [
                'headers' => $request->headers->all(),
                'body' => $request->all()
            ]);

            // Verify webhook signature (implement based on your GWS webhook setup)
            if (!$this->verifyWebhookSignature($request)) {
                Log::warning('GWS webhook signature verification failed');
                return response('Unauthorized', 401);
            }

            $eventType = $request->input('eventType');
            $userData = $request->input('userData', []);
            $userEmail = $request->input('userEmail');
            $domain = $request->input('domain', 'bu.glue-si.com');

            // Validate required fields
            if (!$eventType) {
                Log::warning('GWS webhook missing eventType');
                return response('Bad Request: Missing eventType', 400);
            }

            // Dispatch job to handle the webhook
            HandleGoogleWorkspaceWebhook::dispatch($eventType, $userData, $userEmail, $domain);

            Log::info('GWS webhook dispatched successfully', [
                'event_type' => $eventType,
                'user_email' => $userEmail
            ]);

            return response('OK', 200);
        } catch (\Exception $e) {
            Log::error('GWS webhook processing failed', [
                'error' => $e->getMessage(),
                'request_data' => $request->all(),
                'trace' => $e->getTraceAsString()
            ]);

            return response('Internal Server Error', 500);
        }
    }

    protected function verifyWebhookSignature(Request $request): bool
    {
        // TODO: Implement webhook signature verification
        // This depends on how Google Workspace sends webhooks
        // For now, we'll accept all requests for testing

        $signature = $request->header('X-Google-Signature');
        $secret = config('services.google.webhook_secret');

        if (!$signature || !$secret) {
            // If no signature verification is configured, accept the request
            return true;
        }

        // Implement HMAC signature verification here
        // $expectedSignature = hash_hmac('sha256', $request->getContent(), $secret);
        // return hash_equals($signature, $expectedSignature);

        return true; // Placeholder for now
    }
}
