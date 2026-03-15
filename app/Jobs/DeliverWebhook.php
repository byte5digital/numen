<?php

namespace App\Jobs;

use App\Models\WebhookDelivery;
use App\Rules\ExternalUrl;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;

class DeliverWebhook implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    /** Maximum delivery attempts before abandoning. */
    public int $tries = 3;

    /** Seconds between retry attempts (exponential handled by Laravel). */
    public int $backoff = 60;

    public function __construct(
        public readonly WebhookDelivery $delivery,
        public readonly array $payload,
    ) {}

    public function handle(): void
    {
        $webhook = $this->delivery->webhook;

        // Re-validate the URL at delivery time to protect against DNS rebinding attacks.
        // A URL may pass validation at registration time but resolve to an internal address
        // later if the DNS record changes.
        $externalUrlRule = new ExternalUrl;
        if (! $externalUrlRule->resolveAndValidate($webhook->url)) {
            $this->delivery->update([
                'status' => WebhookDelivery::STATUS_ABANDONED,
                'error_message' => 'Delivery aborted: webhook URL resolved to a blocked (internal/private) address at delivery time.',
            ]);

            return;
        }

        $body = json_encode($this->payload, JSON_UNESCAPED_UNICODE);
        $hmac = hash_hmac('sha256', $body, $webhook->secret);
        $signature = 'sha256='.$hmac;

        $headers = array_merge(
            $webhook->headers ?? [],
            [
                'Content-Type' => 'application/json',
                'X-Numen-Event' => $this->delivery->event_type,
                'X-Numen-Delivery' => $this->delivery->id,
                'X-Numen-Signature' => $signature,
            ],
        );

        try {
            $response = Http::withHeaders($headers)
                ->timeout(10)
                ->post($webhook->url, $this->payload);

            if ($response->successful()) {
                $this->delivery->update([
                    'status' => WebhookDelivery::STATUS_DELIVERED,
                    'http_status' => $response->status(),
                    'response_body' => substr((string) $response->body(), 0, 4096),
                    'delivered_at' => now(),
                ]);

                return;
            }

            $this->handleFailure(
                $response->status(),
                $response->body(),
                "HTTP {$response->status()} response from webhook endpoint",
            );
        } catch (\Throwable $e) {
            $this->handleFailure(null, null, $e->getMessage());
            throw $e; // Re-throw so Laravel can retry the job
        }
    }

    public function failed(\Throwable $e): void
    {
        // Called after all retries are exhausted
        $this->delivery->update([
            'status' => WebhookDelivery::STATUS_ABANDONED,
            'error_message' => 'Abandoned after '.$this->tries.' attempts: '.$e->getMessage(),
        ]);
    }

    private function handleFailure(?int $httpStatus, ?string $responseBody, string $errorMessage): void
    {
        $isAbandoned = $this->attempts() >= $this->tries;

        $this->delivery->update([
            'status' => $isAbandoned
                ? WebhookDelivery::STATUS_ABANDONED
                : WebhookDelivery::STATUS_FAILED,
            'http_status' => $httpStatus,
            'response_body' => $responseBody ? substr($responseBody, 0, 4096) : null,
            'error_message' => $errorMessage,
            'attempt_number' => $this->attempts(),
        ]);
    }
}
