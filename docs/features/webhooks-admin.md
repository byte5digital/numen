# Webhooks Admin UI

## Overview

The Webhooks Admin UI is the control center for managing webhook endpoints and event subscriptions in Numen. Accessible at `/admin/webhooks`, it provides a full CRUD interface for creating, editing, deleting webhook endpoints, managing event subscriptions, viewing delivery logs, and rotating signing secrets.

## Access & Location

- **URL:** `/admin/webhooks`
- **Navigation:** Admin Panel → Settings → Webhooks
- **Required Role:** Admin (or equivalent with webhook management permissions)

## Features

### Create Webhook Endpoints

Create a new webhook endpoint by specifying:
- **URL:** The HTTPS endpoint where webhook payloads will be delivered
- **Event Subscriptions:** Select which event types trigger deliveries to this endpoint (e.g., `content.created`, `content.published`, etc.)
- **Description:** Optional notes about the endpoint's purpose

When you create an endpoint, Numen generates a **signing secret**. This secret is displayed only once — **copy it immediately and store it securely**. You cannot retrieve it later. Use this secret to verify webhook request signatures on your endpoint.

### Manage Event Subscriptions

For each endpoint, select which event types should trigger webhook deliveries. Common events include:
- `content.created`
- `content.published`
- `content.updated`
- `content.deleted`
- `space.created`
- And more

You can add, remove, or modify subscriptions at any time without recreating the endpoint.

### View Delivery Logs

Monitor webhook delivery history per endpoint:
- **Delivery Status:** Success (2xx) or failure with HTTP status code and error message
- **Timestamp:** When the delivery was attempted
- **Payload Size:** Request body size for debugging
- **Response Time:** Latency in milliseconds

Logs are retained for audit and troubleshooting.

### Rotate Signing Secrets

For security, rotate the endpoint's signing secret at any time:

1. Click **Rotate Secret** on the endpoint detail view
2. A new secret is generated and displayed **once only**
3. **Copy the new secret immediately** — the old secret becomes invalid within seconds
4. Update your webhook consumer to use the new secret

This is useful if:
- A secret is suspected to be compromised
- You're changing your webhook consumer implementation
- Regular security rotation is required by your compliance framework

### Manual Redeliver

If a webhook delivery failed or needs to be manually triggered, use the **Redeliver** action:

- Select the delivery log entry you want to redeliver
- Click **Redeliver**
- The same payload is delivered again to the endpoint

**Rate Limit:** Manual redeliveries are rate-limited to **10 per minute per user** to prevent accidental spam or abuse.

## Signing Webhook Requests

Numen signs all webhook payloads using HMAC-SHA256. Each request includes an `X-Numen-Signature` header with the format:

```
X-Numen-Signature: v1=<signature>
```

To verify a webhook:

```php
$secret = 'your_endpoint_secret';
$payload = file_get_contents('php://input');
$signature = hash_hmac('sha256', $payload, $secret);
$expectedHeader = 'v1=' . $signature;

if ($signature !== $_SERVER['HTTP_X_NUMEN_SIGNATURE'] ?? '') {
    http_response_code(403);
    die('Signature mismatch');
}

// Process webhook
```

## API Reference

The webhooks admin UI is backed by REST endpoints under `/api/admin/webhooks`:

- `GET /api/admin/webhooks` — List all endpoints
- `POST /api/admin/webhooks` — Create endpoint
- `GET /api/admin/webhooks/{id}` — Retrieve endpoint details
- `PATCH /api/admin/webhooks/{id}` — Update endpoint and subscriptions
- `DELETE /api/admin/webhooks/{id}` — Delete endpoint
- `POST /api/admin/webhooks/{id}/rotate-secret` — Rotate signing secret
- `GET /api/admin/webhooks/{id}/deliveries` — Paginated delivery log
- `POST /api/admin/webhooks/{id}/deliveries/{deliveryId}/redeliver` — Manual redeliver (rate-limited)

## Best Practices

1. **Store Secrets Securely:** Always store your webhook signing secret in environment variables or a secure vault. Never commit it to version control.

2. **Verify Signatures:** Always validate the `X-Numen-Signature` header on incoming webhooks to ensure requests came from Numen.

3. **Handle Failures Gracefully:** Implement exponential backoff and retry logic on your webhook consumer in case of temporary network issues.

4. **Use HTTPS Only:** Webhooks are delivered only to HTTPS endpoints for security.

5. **Copy Secrets Immediately:** When you create or rotate a secret, copy it right away. You cannot retrieve it later.

6. **Rotate Regularly:** Rotate secrets periodically (e.g., quarterly) as part of security best practices.

7. **Monitor Delivery Logs:** Regularly check the delivery log for failed deliveries and investigate the cause.

## Troubleshooting

### "Signature mismatch" errors

Ensure you're using the correct secret for the endpoint and that you're hashing the raw request body (not JSON-decoded data).

### Webhook not delivering

1. Verify the endpoint URL is correct and HTTPS
2. Check that event subscriptions include the events you're listening for
3. Review the delivery log for specific error messages
4. Ensure your endpoint is responding with a 2xx status code within the timeout window (typically 10 seconds)

### Secret is lost

If you lose a secret before updating your webhook consumer, rotate it immediately. The old secret will become invalid, forcing you to re-configure. This is by design for security.

