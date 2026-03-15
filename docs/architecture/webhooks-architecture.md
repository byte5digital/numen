# Webhooks & Event System Architecture

**Feature**: Webhooks & Event System (GitHub Discussion #9)  
**Status**: Architecture Design  
**Last Updated**: 2026-03-07  
**Author**: Blueprint 🏗️

---

## 1. Overview & Motivation

Modern headless CMS platforms need to notify external systems when things happen — triggering builds, syncing with CDNs, notifying Slack, updating search indexes. Numen currently has no outbound notification mechanism.

**This architecture design proposes:**
- A **comprehensive event catalog** for all system events
- A **webhook management system** with CRUD operations and delivery logs
- **Reliable delivery** with exponential backoff retry logic (3 retries)
- **Cryptographic signing** using HMAC-SHA256 for webhook verification
- **Event filtering** to allow subscribers to react to specific events
- **Bulk/batch mode** for high-volume scenarios
- **Special support for Numen-specific events** (pipeline stages, AI quality scores)

---

## 2. Event Catalog

### 2.1 Event Types

All events follow the pattern `domain.action` (lowercase with dots).

#### **Content Events**
- `content.created` — When a new content item is created
- `content.updated` — When content is edited (field changes, metadata)
- `content.published` — When content is published to public state
- `content.unpublished` — When published content is reverted to draft
- `content.scheduled` — When content is scheduled for future publication
- `content.rolled_back` — When a content version is rolled back

#### **Pipeline Events** (Numen-specific)
- `pipeline.started` — Pipeline execution begins
- `pipeline.stage_completed` — Individual pipeline stage finishes (including AI quality scores)
- `pipeline.completed` — Pipeline execution succeeded
- `pipeline.failed` — Pipeline execution failed with error details
- `pipeline.cancelled` — Pipeline was manually cancelled

#### **Media Events**
- `media.uploaded` — New media asset is uploaded
- `media.deleted` — Media asset is removed
- `media.variant_generated` — Image variant (thumbnail, webp, etc.) is ready

#### **Brief Events**
- `brief.submitted` — A content brief is submitted

#### **Future Events** (reserved for expansion)
- `user.created`, `user.updated`, `user.deleted`
- `space.created`, `space.updated`
- `content.expired`, `content.republished`

### 2.2 Event Structure

Each event carries:
```php
class WebhookPayload {
    string $id;                    // Unique event ID (ULID)
    string $event;                 // e.g., "content.published"
    int $timestamp;                // Unix timestamp
    string $space_id;              // Workspace/Space context
    array $data;                   // Event-specific payload
    array $metadata;               // Optional: request_id, user_id, ip, etc.
}
```

### 2.3 Payload Examples

**Content Published**
```json
{
  "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "event": "content.published",
  "timestamp": 1678876800,
  "space_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "data": {
    "content_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
    "content_type": "article",
    "title": "Hello World",
    "slug": "hello-world",
    "locale": "en",
    "published_at": "2023-03-15T12:00:00Z",
    "version_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
    "canonical_id": null
  },
  "metadata": {
    "user_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
    "request_id": "req-123456"
  }
}
```

**Pipeline Stage Completed** (Numen-specific)
```json
{
  "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "event": "pipeline.stage_completed",
  "timestamp": 1678876800,
  "space_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "data": {
    "pipeline_run_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
    "stage": "enhancement",
    "duration_ms": 2450,
    "status": "completed",
    "ai_quality_score": 0.87,
    "ai_quality_metrics": {
      "coherence": 0.92,
      "relevance": 0.84,
      "tone_match": 0.79
    },
    "output_tokens": 1250,
    "cost_usd": 0.025
  },
  "metadata": {
    "content_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV"
  }
}
```

---

## 3. Database Schema

### 3.1 Webhooks Table

```sql
CREATE TABLE webhooks (
    id CHAR(26) PRIMARY KEY,          -- ULID
    space_id CHAR(26) NOT NULL,       -- Multi-tenancy
    url VARCHAR(2048) NOT NULL,       -- Endpoint URL
    secret VARCHAR(128) NOT NULL,     -- HMAC secret (random, 32+ bytes)
    
    -- Subscription filtering
    events JSON NOT NULL,             -- ["content.published", "pipeline.*"]
    active BOOLEAN DEFAULT TRUE,      -- Can disable without deleting
    
    -- Configuration
    retry_policy JSON DEFAULT NULL,   -- See 3.3 Retry Config
    headers JSON DEFAULT NULL,        -- Custom headers: {"Authorization": "Bearer token"}
    batch_mode BOOLEAN DEFAULT FALSE, -- Batch events instead of single delivery
    batch_timeout INT DEFAULT 5000,   -- Max wait in ms before sending batch
    
    -- Audit
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL,
    deleted_at TIMESTAMP NULL,        -- Soft-delete
    
    FOREIGN KEY (space_id) REFERENCES spaces(id) ON DELETE CASCADE,
    INDEX idx_space_id_active (space_id, active),
    UNIQUE uk_space_url (space_id, url)  -- No duplicate endpoints per space
);
```

### 3.2 Webhook Delivery Logs Table

```sql
CREATE TABLE webhook_deliveries (
    id CHAR(26) PRIMARY KEY,          -- ULID
    webhook_id CHAR(26) NOT NULL,     -- FK to webhooks
    event_id CHAR(26) NOT NULL,       -- The event that triggered this delivery
    event_type VARCHAR(64) NOT NULL,  -- e.g., "content.published" (denormalized for queries)
    
    -- Payload metadata
    payload_hash VARCHAR(64) NULL,    -- SHA256(payload) for dedup in batch mode
    
    -- Delivery attempt
    attempt_number TINYINT DEFAULT 1,
    status VARCHAR(32) NOT NULL,      -- pending, delivered, failed, abandoned
    http_status INT NULL,             -- Response status code
    response_body LONGTEXT NULL,      -- First 5KB of response
    error_message TEXT NULL,          -- Exception message if failed
    
    -- Timing
    scheduled_at TIMESTAMP NULL,      -- When delivery should be retried
    delivered_at TIMESTAMP NULL,      -- When successfully delivered
    created_at TIMESTAMP NOT NULL,
    
    FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE,
    INDEX idx_webhook_id_status (webhook_id, status),
    INDEX idx_event_id (event_id),
    INDEX idx_scheduled_at (scheduled_at),
    INDEX idx_created_at (created_at)
);
```

### 3.3 Retry Configuration

Stored as JSON in `webhooks.retry_policy`:

```json
{
  "max_retries": 3,
  "initial_delay_ms": 5000,
  "backoff_multiplier": 2.0,
  "max_delay_ms": 300000,
  "timeout_ms": 30000,
  "retry_status_codes": [408, 429, 500, 502, 503, 504]
}
```

**Exponential Backoff Schedule** (defaults):
- **Attempt 1**: Immediate
- **Attempt 2**: 5s delay (5000ms)
- **Attempt 3**: 10s delay (5000 × 2)
- **Attempt 4**: 20s delay (10000 × 2)
- **Attempt 5 (if retries=4)**: 40s delay (cap at 300s max)

---

## 4. Event System Architecture

### 4.1 Event Flow Diagram

```
┌─────────────────┐
│  Domain Action  │
│ (e.g., Publish) │
└────────┬────────┘
         │
         ▼
┌─────────────────────────────┐
│  Dispatch Laravel Event     │
│  (e.g., ContentPublished)   │
└────────┬────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│  WebhookEventListener               │
│  - Maps Laravel Event → Webhook     │
│  - Creates WebhookPayload           │
│  - Publishes to event dispatcher    │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│  DispatchWebhookEvent               │
│  (Laravel event)                    │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│  WebhookEventDispatcher             │
│  - Finds matching webhooks          │
│  - Creates delivery records         │
│  - Dispatches to queue              │
└────────┬────────────────────────────┘
         │
         ▼
┌─────────────────────────────────────┐
│  DeliverWebhook Job (Queued)       │
│  - Signs payload                    │
│  - Makes HTTP POST request          │
│  - Handles retries via scheduler    │
└─────────────────────────────────────┘
```

### 4.2 Laravel Events (Domain Events)

These already exist in Numen. Webhooks subscribes to them:

```php
// app/Events/Content/ContentPublished.php
namespace App\Events\Content;
use App\Models\Content;
use Illuminate\Foundation\Events\Dispatchable;

class ContentPublished {
    use Dispatchable;
    public function __construct(public Content $content) {}
}
```

### 4.3 Webhook Event System

#### A. WebhookPayload Value Object

```php
// app/Services/Webhooks/WebhookPayload.php
namespace App\Services\Webhooks;

class WebhookPayload {
    public string $id;              // ULID
    public string $event;           // e.g., "content.published"
    public int $timestamp;
    public string $space_id;
    public array $data;
    public array $metadata;
    
    public function toArray(): array { /* ... */ }
    public function toJson(): string { /* ... */ }
}
```

#### B. Event Mapper Service

Maps Laravel events → Webhook events:

```php
// app/Services/Webhooks/EventMapper.php
namespace App\Services\Webhooks;

class EventMapper {
    public static function map(object $laravelEvent): WebhookPayload {
        return match($laravelEvent::class) {
            \App\Events\Content\ContentPublished::class 
                => self::mapContentPublished($laravelEvent),
            \App\Events\Pipeline\PipelineStageCompleted::class
                => self::mapPipelineStageCompleted($laravelEvent),
            // ... more mappings
            default => throw new UnmappableEventException(),
        };
    }
    
    private static function mapContentPublished($event): WebhookPayload {
        return new WebhookPayload(
            event: 'content.published',
            data: [
                'content_id' => $event->content->id,
                'content_type' => $event->content->content_type->slug,
                'title' => $event->content->current_version?->title,
                // ...
            ],
        );
    }
}
```

#### C. DispatchWebhookEvent (Laravel Event)

```php
// app/Events/Webhooks/DispatchWebhookEvent.php
namespace App\Events\Webhooks;

use App\Services\Webhooks\WebhookPayload;
use Illuminate\Foundation\Events\Dispatchable;

class DispatchWebhookEvent {
    use Dispatchable;
    
    public function __construct(public WebhookPayload $payload) {}
}
```

#### D. WebhookEventListener (Maps domain → webhook events)

```php
// app/Listeners/Webhooks/WebhookEventListener.php
namespace App\Listeners\Webhooks;

use App\Events\Webhooks\DispatchWebhookEvent;
use App\Services\Webhooks\EventMapper;

class WebhookEventListener {
    public function handle(object $event): void {
        try {
            $payload = EventMapper::map($event);
            DispatchWebhookEvent::dispatch($payload);
        } catch (UnmappableEventException) {
            // Silently skip unmapped events
        }
    }
}
```

#### E. Register Listeners (ServiceProvider)

```php
// app/Providers/EventServiceProvider.php
protected $listen = [
    \App\Events\Content\ContentPublished::class => [
        \App\Listeners\Webhooks\WebhookEventListener::class,
    ],
    \App\Events\Content\ContentUnpublished::class => [
        \App\Listeners\Webhooks\WebhookEventListener::class,
    ],
    // ... all events that trigger webhooks
];
```

---

## 5. Webhook Delivery System

### 5.1 WebhookEventDispatcher (Queuer)

Responsible for finding matching webhooks and queueing delivery jobs:

```php
// app/Services/Webhooks/WebhookEventDispatcher.php
namespace App\Services\Webhooks;

use App\Models\Webhook;
use App\Jobs\Webhooks\DeliverWebhook;

class WebhookEventDispatcher {
    public function dispatch(WebhookPayload $payload): void {
        $webhooks = Webhook::query()
            ->where('space_id', $payload->space_id)
            ->where('active', true)
            ->get();
        
        foreach ($webhooks as $webhook) {
            if ($this->matches($webhook, $payload)) {
                if ($webhook->batch_mode) {
                    // Queue in batch buffer instead of immediate job
                    $this->queueBatch($webhook, $payload);
                } else {
                    // Immediate delivery
                    DeliverWebhook::dispatch($webhook, $payload)
                        ->onQueue('webhooks');
                }
            }
        }
    }
    
    private function matches(Webhook $webhook, WebhookPayload $payload): bool {
        foreach ($webhook->events as $pattern) {
            if ($this->patternMatches($pattern, $payload->event)) {
                return true;
            }
        }
        return false;
    }
    
    private function patternMatches(string $pattern, string $event): bool {
        $pattern = str_replace('*', '.*', $pattern);
        return preg_match("/^{$pattern}$/", $event) === 1;
    }
}
```

### 5.2 DeliverWebhook Job (Queued Worker)

Handles HTTP delivery, signing, and retries:

```php
// app/Jobs/Webhooks/DeliverWebhook.php
namespace App\Jobs\Webhooks;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use App\Services\Webhooks\WebhookPayload;
use App\Services\Webhooks\WebhookSigner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class DeliverWebhook implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    
    public int $tries = 4;  // 1 initial + 3 retries
    
    public function __construct(
        public Webhook $webhook,
        public WebhookPayload $payload,
    ) {}
    
    public function handle(): void {
        $delivery = WebhookDelivery::create([
            'webhook_id' => $this->webhook->id,
            'event_id' => $this->payload->id,
            'event_type' => $this->payload->event,
            'attempt_number' => 1,
            'status' => 'pending',
        ]);
        
        $signature = WebhookSigner::sign($this->payload, $this->webhook->secret);
        
        try {
            $response = $this->sendRequest($signature);
            
            $delivery->update([
                'status' => 'delivered',
                'http_status' => $response->status(),
                'response_body' => $response->body(),
                'delivered_at' => now(),
            ]);
        } catch (\Exception $e) {
            $this->handleFailure($delivery, $e);
        }
    }
    
    private function sendRequest(string $signature): \Illuminate\Http\Client\Response {
        return Http::timeout(30)
            ->withHeaders([
                'X-Webhook-Signature' => $signature,
                'X-Webhook-Event' => $this->payload->event,
                'X-Webhook-ID' => $this->payload->id,
                'Content-Type' => 'application/json',
                ...$this->webhook->headers ?? [],
            ])
            ->post($this->webhook->url, $this->payload->toArray());
    }
    
    private function handleFailure(WebhookDelivery $delivery, \Exception $e): void {
        $delivery->update([
            'status' => 'failed',
            'error_message' => $e->getMessage(),
        ]);
        
        if ($this->attempts() < $this->tries) {
            $this->retry($this->calculateBackoff());
        } else {
            $delivery->update(['status' => 'abandoned']);
        }
    }
    
    private function calculateBackoff(): int {
        $config = $this->webhook->retry_policy ?? [
            'initial_delay_ms' => 5000,
            'backoff_multiplier' => 2.0,
            'max_delay_ms' => 300000,
        ];
        
        $delay = $config['initial_delay_ms'] 
            * pow($config['backoff_multiplier'], $this->attempts() - 1);
        
        return (int) min($delay, $config['max_delay_ms']) / 1000;
    }
}
```

### 5.3 Batch Mode (ProcessWebhookBatch Job)

For high-volume scenarios, batch events:

```php
// app/Jobs/Webhooks/ProcessWebhookBatch.php
namespace App\Jobs\Webhooks;

use App\Models\Webhook;
use App\Models\WebhookDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessWebhookBatch implements ShouldQueue {
    public function handle(): void {
        $pendingBatches = Cache::tags('webhook-batch')
            ->keys('webhook-batch:*');
        
        foreach ($pendingBatches as $batchKey) {
            $webhook_id = explode(':', $batchKey)[1];
            $webhook = Webhook::find($webhook_id);
            
            $events = Cache::tags('webhook-batch')
                ->get($batchKey, []);
            
            if (empty($events)) continue;
            
            $payload = [
                'id' => \Illuminate\Support\Str::ulid(),
                'event' => 'webhook.batch',
                'timestamp' => now()->unix(),
                'events' => $events,
            ];
            
            DeliverWebhookBatch::dispatch($webhook, $payload);
            Cache::tags('webhook-batch')->forget($batchKey);
        }
    }
}
```

---

## 6. Secret Signing & Verification

### 6.1 WebhookSigner Service

HMAC-SHA256 implementation:

```php
// app/Services/Webhooks/WebhookSigner.php
namespace App\Services\Webhooks;

use Illuminate\Support\Facades\Hash;

class WebhookSigner {
    public static function sign(WebhookPayload $payload, string $secret): string {
        $body = json_encode($payload->toArray(), JSON_UNESCAPED_SLASHES);
        
        return 'sha256=' . hash_hmac('sha256', $body, $secret);
    }
    
    public static function verify(
        string $signature,
        string $body,
        string $secret
    ): bool {
        $expected = 'sha256=' . hash_hmac('sha256', $body, $secret);
        
        // Constant-time comparison to prevent timing attacks
        return hash_equals($expected, $signature);
    }
}
```

### 6.2 Receiver Verification (Example)

Subscribers verify the signature:

```php
// On the subscriber's end (pseudo-code)
$signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'];
$body = file_get_contents('php://input');
$secret = 'webhook_secret_from_numen_dashboard';

if (!hash_equals(
    'sha256=' . hash_hmac('sha256', $body, $secret),
    $signature
)) {
    http_response_code(401);
    exit('Unauthorized');
}

$payload = json_decode($body, true);
// Process webhook...
```

---

## 7. API Endpoints

All endpoints are RESTful, follow JSON:API conventions where applicable, and require webhook-scoped API key authentication.

### 7.1 Webhook Management

#### Create Webhook
```
POST /api/v1/webhooks
Authorization: Bearer {api_key}
Content-Type: application/json

{
  "url": "https://example.com/webhooks/numen",
  "events": ["content.published", "pipeline.*"],
  "active": true,
  "retry_policy": {
    "max_retries": 3,
    "initial_delay_ms": 5000
  },
  "headers": {
    "Authorization": "Bearer secret-token"
  },
  "batch_mode": false
}

Response: 201 Created
{
  "id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "url": "https://example.com/webhooks/numen",
  "secret": "whsec_1a2b3c4d5e6f...",  // Only shown on creation!
  "events": [...],
  "active": true,
  "created_at": "2023-03-15T12:00:00Z"
}
```

#### List Webhooks
```
GET /api/v1/webhooks?page=1&limit=20
Authorization: Bearer {api_key}

Response: 200 OK
{
  "data": [
    { "id": "...", "url": "...", "events": [...], ... }
  ],
  "meta": {
    "total": 5,
    "page": 1,
    "per_page": 20
  }
}
```

#### Get Webhook
```
GET /api/v1/webhooks/{id}
Authorization: Bearer {api_key}

Response: 200 OK
{ "id": "...", "url": "...", ... }
```

#### Update Webhook
```
PATCH /api/v1/webhooks/{id}
Authorization: Bearer {api_key}
Content-Type: application/json

{
  "events": ["content.published", "content.updated"],
  "active": false
}

Response: 200 OK
{ "id": "...", "url": "...", ... }
```

#### Delete Webhook
```
DELETE /api/v1/webhooks/{id}
Authorization: Bearer {api_key}

Response: 204 No Content
```

#### Rotate Secret
```
POST /api/v1/webhooks/{id}/rotate-secret
Authorization: Bearer {api_key}

Response: 200 OK
{
  "id": "...",
  "secret": "whsec_new_secret_here"  // New secret
}
```

### 7.2 Delivery Logs

#### List Deliveries for Webhook
```
GET /api/v1/webhooks/{id}/deliveries?status=failed&page=1
Authorization: Bearer {api_key}

Response: 200 OK
{
  "data": [
    {
      "id": "...",
      "webhook_id": "...",
      "event_type": "content.published",
      "status": "failed",
      "http_status": 500,
      "error_message": "Connection timeout",
      "attempt_number": 3,
      "created_at": "2023-03-15T12:05:00Z",
      "delivered_at": null
    }
  ],
  "meta": { "total": 10, "page": 1 }
}
```

#### Get Single Delivery
```
GET /api/v1/webhooks/{webhook_id}/deliveries/{delivery_id}
Authorization: Bearer {api_key}

Response: 200 OK
{
  "id": "...",
  "webhook_id": "...",
  "event_type": "content.published",
  "payload_hash": "sha256abc123...",
  "status": "failed",
  "http_status": 500,
  "response_body": "{ \"error\": \"Internal Server Error\" }",
  "error_message": "HTTP 500: Internal Server Error",
  "attempt_number": 2,
  "scheduled_at": "2023-03-15T12:10:00Z",
  "delivered_at": null,
  "created_at": "2023-03-15T12:05:00Z"
}
```

#### Retry Failed Delivery
```
POST /api/v1/webhooks/{webhook_id}/deliveries/{delivery_id}/retry
Authorization: Bearer {api_key}

Response: 202 Accepted
{
  "id": "...",
  "status": "pending",
  "attempt_number": 4,
  "scheduled_at": "2023-03-15T12:35:00Z"
}
```

#### Get Delivery Statistics
```
GET /api/v1/webhooks/{id}/stats
Authorization: Bearer {api_key}

Response: 200 OK
{
  "total_deliveries": 1250,
  "successful": 1200,
  "failed": 30,
  "pending": 20,
  "success_rate": 0.96,
  "average_response_time_ms": 245,
  "last_30_days": {
    "deliveries": 450,
    "successful": 440,
    "failed": 10
  }
}
```

---

## 8. Admin UI Components

### 8.1 Webhooks List (Vue/Inertia Component)

**Path**: `resources/js/Pages/Webhooks/Index.vue`

**Features**:
- Table with columns: URL, Events (preview), Status (active/inactive), Last Delivery, Actions
- Create button (link to creation form)
- Filter by status (active/inactive)
- Pagination
- Bulk actions: activate/deactivate

**Design**: 
```
┌─ Webhooks ─────────────────────────────────────┐
│ [+ Create Webhook]                              │
├─────────────────────────────────────────────────┤
│ URL              │ Events          │ Status │  … │
├─────────────────────────────────────────────────┤
│ example.com/w… │ content.*, p... │ ✓      │ … │
│ hooks.io/api  │ media.uploaded │ ✓      │ … │
│ example.com/…  │ pipeline.*     │ ✗      │ … │
└─────────────────────────────────────────────────┘
```

### 8.2 Create/Edit Webhook (Form Component)

**Path**: `resources/js/Pages/Webhooks/CreateEdit.vue`

**Fields**:
- **Endpoint URL** (text input, validated)
- **Events** (multi-select checkbox group with categories):
  - Content: created, updated, published, unpublished, scheduled, rolled_back
  - Pipeline: started, stage_completed, completed, failed, cancelled
  - Media: uploaded, deleted, variant_generated
  - Brief: submitted
  - Select All / Clear All buttons
  - Pattern matching hint: "Use `*` for wildcards (e.g., `pipeline.*`)"
- **Status** (toggle: active/inactive)
- **Retry Policy** (collapsible):
  - Max retries (dropdown: 1-5, default 3)
  - Initial delay (select: 1s, 5s, 10s, default 5s)
  - Backoff multiplier (select: 1.5, 2.0, 3.0)
  - Max delay (input, default 300s)
- **Custom Headers** (key-value table, optional):
  - Add/remove rows
  - Examples provided (Bearer auth)
- **Batch Mode** (toggle + timeout input):
  - When enabled: show "Wait up to X ms before sending events together"
  - Timeout input (ms, default 5000)
- **Preview Secret** (read-only, copyable):
  - "Secret will be shown only at creation time. Save it securely!"
- **Actions**: [Save] [Cancel] [Test Webhook]

### 8.3 Delivery Logs Viewer (DataTable Component)

**Path**: `resources/js/Pages/Webhooks/DeliveryLogs.vue`

**Features**:
- Sortable table: Event Type, Status, Response Code, Timestamp, Actions
- Filters (sidebar or top bar):
  - Status: All / Pending / Delivered / Failed / Abandoned
  - Event Type: Dropdown (content.*, pipeline.*, media.*, brief.*)
  - Date range: From/To date pickers
  - Response code range (optional)
- Pagination (20/50/100 per page)
- Detail modal on row click:
  - Payload JSON (syntax-highlighted, copyable)
  - Response body (JSON viewer)
  - Error message (if failed)
  - Retry button (if failed or pending)
  - Timestamps for created/scheduled/delivered

**Design**:
```
┌─ Delivery Logs: {webhook_url} ─────────────────────┐
│ Filters:                                             │
│ [Status: All ▼] [Event: All ▼] [From: ___] [To: ___]│
├────────────────────────────────────────────────────┤
│ Event Type       │ Status    │ Code │ Time      │   │
├────────────────────────────────────────────────────┤
│ content.pub…     │ ✓ Delivered│ 200  │ 2m ago   │ ↗ │
│ pipeline.stage…  │ ✗ Failed   │ 500  │ 1m ago   │ ↗ │
│ content.upd…     │ ⏳ Pending  │ —    │ 30s ago  │ ↗ │
└────────────────────────────────────────────────────┘
```

### 8.4 Test Webhook Modal

**Path**: `resources/js/Components/TestWebhookModal.vue`

**Features**:
- Dropdown to select an event type
- Send test payload with sample data
- Display response: status, headers, body
- Show actual signature that was sent
- Copy button for all content
- Success/error feedback

---

## 9. Configuration & Queuing

### 9.1 Config File

**Path**: `config/numen.php` (new section)

```php
'webhooks' => [
    // Default retry policy
    'default_retry_policy' => [
        'max_retries' => 3,
        'initial_delay_ms' => 5000,
        'backoff_multiplier' => 2.0,
        'max_delay_ms' => 300000,
        'timeout_ms' => 30000,
        'retry_status_codes' => [408, 429, 500, 502, 503, 504],
    ],
    
    // Queue configuration
    'queue' => 'webhooks',
    
    // Batch processing
    'batch_processing_enabled' => true,
    'batch_max_size' => 100,
    
    // Delivery log retention
    'log_retention_days' => 90,
    
    // Rate limiting (optional, future)
    'rate_limit_per_minute' => 1000,
],
```

### 9.2 Queue & Scheduling

#### Horizon Configuration

A dedicated `webhooks` queue on Redis (separate from default):

```php
// config/horizon.php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => 'webhooks',
            'balance' => 'simple',
            'processes' => 4,
            'tries' => 4,
            'timeout' => 30,
            'nice' => null,
        ],
    ],
],
```

#### Scheduler for Batch Processing

```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule) {
    $schedule->job(new ProcessWebhookBatch)
        ->everyMinute()
        ->withoutOverlapping();
    
    // Clean up old delivery logs
    $schedule->call(function () {
        WebhookDelivery::where(
            'created_at',
            '<',
            now()->subDays(config('numen.webhooks.log_retention_days'))
        )->delete();
    })->daily();
}
```

---

## 10. Key Design Decisions

### Decision 1: Laravel Events as Source of Truth

**Choice**: Use existing Laravel events as the source for webhook events.

**Rationale**:
- ✅ Events already exist for all major domain actions
- ✅ Decouples webhook system from domain logic
- ✅ Listeners pattern is well-understood in Laravel
- ✅ Allows other systems to subscribe without webhook overhead

**Trade-off**: EventMapper adds a translation layer, but ensures clean separation.

---

### Decision 2: Separate Webhook Delivery Job + Exponential Backoff

**Choice**: Queue-based delivery with exponential backoff retry logic in the job itself.

**Rationale**:
- ✅ Automatic retries without cron overhead
- ✅ Exponential backoff avoids hammering failed endpoints
- ✅ Jobs marked as failed after max retries → manual retry via API
- ✅ Failed deliveries are logged for debugging

**Alternative Considered**: Cron-based scheduler that processes retry table entries.
- ❌ More complex state machine
- ❌ Requires separate scheduler logic

---

### Decision 3: HMAC-SHA256 Signing (Not Timestamps + Nonce)

**Choice**: Sign request body with shared secret using HMAC-SHA256.

**Rationale**:
- ✅ Industry standard (GitHub, Stripe, Shopify use this)
- ✅ Verifies both authenticity and integrity
- ✅ No timestamp/nonce complexity
- ✅ Constant-time comparison prevents timing attacks

**Header Format**: `X-Webhook-Signature: sha256=<hex>`

---

### Decision 4: Event Filtering with Glob Patterns

**Choice**: Subscribe to events using simple glob patterns (e.g., `pipeline.*`, `content.published`).

**Rationale**:
- ✅ Simple to implement with regex
- ✅ Flexible for future event proliferation
- ✅ No regex injection risk (we validate on save)
- ✅ Readable: `*` is universally understood

**Alternative Considered**: Full regex patterns
- ❌ Overkill complexity
- ❌ Security risk (untrusted regex)

---

### Decision 5: Batch Mode via Cache (Not Database)

**Choice**: Batch events in Redis cache with a scheduler job.

**Rationale**:
- ✅ Fast, atomic operations
- ✅ Automatic expiry (TTL)
- ✅ No additional database writes
- ✅ Separate from delivery logs

**Implementation**: `Cache::tags('webhook-batch')->put(...)`

---

### Decision 6: Soft-Delete Webhooks (Not Hard Delete)

**Choice**: Use `softDeletes()` on webhooks table.

**Rationale**:
- ✅ Audit trail: can see what webhooks existed
- ✅ Safe: accidentally deleted webhook can be restored
- ✅ Prevent FK conflicts with delivery logs
- ✅ GDPR compliance: deletion requests still possible via query

---

### Decision 7: AI Quality Scores in Pipeline Events

**Choice**: Include `ai_quality_score` and `ai_quality_metrics` in `pipeline.stage_completed` payload.

**Rationale**:
- ✅ Numen-specific value: pipeline stages include AI evaluation
- ✅ Subscribers can route/filter by quality score
- ✅ Enables AI-driven webhook routing (future)
- ✅ Cost tracking per stage for billing

---

### Decision 8: No Webhook Routing/Transformations in v1

**Choice**: Ship v1 with simple subscription only. Transformations deferred to v2+.

**Rationale**:
- ✅ Reduces scope for 0.1 release
- ✅ Subscribers can implement their own transforms
- ✅ Future: add JMESPath transformations if needed
- ✅ Keeps payload schema immutable

---

## 11. Data Flow Examples

### Example 1: Content Published → Webhook Delivery

```
1. User publishes content in admin UI
   └─> PublishContent job executes
   
2. Content record updated: status = "published"
   └─> Dispatch ContentPublished event
   
3. WebhookEventListener catches event
   └─> EventMapper converts to WebhookPayload
   └─> Dispatch DispatchWebhookEvent
   
4. WebhookEventDispatcher receives DispatchWebhookEvent
   └─> Query webhooks for space_id, filter by events
   └─> For each matching webhook:
       └─> Dispatch DeliverWebhook job to "webhooks" queue
   
5. DeliverWebhook job executes:
   └─> Create WebhookDelivery record (status=pending)
   └─> Sign payload with HMAC-SHA256
   └─> POST to webhook URL with signature header
   └─> If success (2xx): update delivery.status='delivered'
   └─> If failure: retry with exponential backoff
   └─> After max retries: delivery.status='abandoned'
   
6. Admin views Delivery Logs
   └─> Sees delivery record with response code, payload, etc.
   └─> Can click "Retry" to resend
```

### Example 2: Pipeline Stage Completed (with AI Quality)

```
1. Pipeline execution completes stage "enhancement"
   └─> PipelineRunService calculates quality scores
   └─> Dispatch PipelineStageCompleted event
   
2. WebhookEventListener catches event
   └─> EventMapper extracts:
       - stage name
       - duration_ms
       - ai_quality_score (0-1 range)
       - ai_quality_metrics { coherence, relevance, tone_match }
       - output_tokens, cost_usd
   └─> Creates WebhookPayload with event='pipeline.stage_completed'
   └─> Dispatch DispatchWebhookEvent
   
3. Subscriber receives webhook:
   - Signature verified
   - Extracts quality score
   - Conditional logic:
     - If quality < 0.7: notify team
     - If quality >= 0.85: auto-publish
     - Log metrics to analytics system
```

### Example 3: Batch Mode (High-Volume Scenario)

```
1. Multiple events fire in quick succession
   Content.created → Event 1
   Content.created → Event 2
   Content.created → Event 3
   
2. WebhookEventDispatcher for batch-enabled webhook:
   └─> Instead of DeliverWebhook job
   └─> Queue in Redis cache: webhook-batch:{webhook_id}
   └─> Set TTL = batch_timeout (e.g., 5000ms)
   
3. After 5 seconds (or when batch reaches max_size=100):
   └─> ProcessWebhookBatch scheduler job runs
   └─> Collect all cached events for this webhook
   └─> Create single payload with events array
   └─> Dispatch DeliverWebhookBatch job
   
4. Subscriber receives:
   {
     "event": "webhook.batch",
     "events": [
       { "event": "content.created", "data": {...} },
       { "event": "content.created", "data": {...} },
       { "event": "content.created", "data": {...} }
     ]
   }
```

---

## 12. Migration Strategy

### New Migrations (Never Modify Existing)

All migrations follow the pattern:
- File format: `database/migrations/YYYY_MM_DD_HHMMSS_create_webhooks_table.php`
- Use ULID for primary keys
- Include indexes on foreign keys, status, created_at
- Include soft deletes where applicable

**List of migrations to create:**

1. `create_webhooks_table.php` — Main webhook endpoints
2. `create_webhook_deliveries_table.php` — Delivery logs and retry tracking
3. `create_webhook_events_table.php` — Event catalog (optional, for reference)

---

## 13. Testing Strategy

### Unit Tests
- `WebhookSignerTest` — HMAC signing and verification
- `EventMapperTest` — Laravel event → WebhookPayload mapping
- `WebhookEventDispatcherTest` — Pattern matching and filtering
- `WebhookPayloadTest` — Serialization and schema validation

### Integration Tests
- End-to-end: Event fired → Webhook delivered → Delivery logged
- Retry logic: Failed delivery → Exponential backoff → Success
- Batch mode: Multiple events → Single batch payload
- API endpoints: CRUD, filtering, retry

### Performance Tests
- Bulk event firing (1000+ events) → delivery within X seconds
- Large payload handling (>10KB JSON)
- Database query efficiency (indexes validated)

---

## 14. Deployment Checklist

- [ ] Run migrations (`php artisan migrate`)
- [ ] Publish config (`php artisan vendor:publish --tag=numen-config`)
- [ ] Create API keys for webhook management
- [ ] Configure queue (Horizon with "webhooks" queue)
- [ ] Set up scheduler (ProcessWebhookBatch cron)
- [ ] Test webhook delivery with test endpoint
- [ ] Monitor delivery logs in admin UI
- [ ] Document event catalog for external subscribers
- [ ] Update API docs with new endpoints

---

## 15. Future Enhancements

### v1.1+
- **Event Filtering by User**: Subscribe only to events created by specific users
- **Webhook Signing Algorithms**: Support RS256 (RSA) in addition to HMAC
- **Event Transformation**: JMESPath expressions to transform payload before delivery
- **Webhook Groups**: Subscribe multiple endpoints to same events atomically

### v2.0+
- **AI-Driven Routing**: Route webhooks based on AI quality scores
- **Conditional Logic**: IF quality > 0.8 THEN POST to A ELSE POST to B
- **Dead Letter Queue**: Persistent storage for permanently failed deliveries
- **Idempotency Keys**: De-duplicate identical events within time window
- **Webhook Middleware**: Authentication, rate limiting, IP whitelist per webhook

---

## 16. Security Considerations

### Secret Management
- Secrets are generated as random 32-byte hex strings
- Stored in database (encrypted at rest via Laravel encryption if enabled)
- Only shown at creation time (not retrievable later)
- Can be rotated via API endpoint

### Payload Integrity
- HMAC-SHA256 signature prevents tampering
- Signature verified by receiver using shared secret
- Include nonce in future iterations if idempotency needed

### Rate Limiting (v1.1+)
- Per-webhook rate limits (configurable)
- Backpressure: if receiver slow, queue doesn't back up
- Configurable timeout per request

### Audit Trail
- All deliveries logged with request/response details
- Soft-deleted webhooks remain in audit logs
- Supports compliance audits (GDPR, SOC2)

---

## 17. Monitoring & Observability

### Metrics to Track
- Webhooks created/updated/deleted per day
- Total deliveries per day
- Success rate (successful / total)
- Average response time
- Failed deliveries (retry exhausted)
- Queue size (pending deliveries)

### Logs
- Delivery success/failure with full response
- Event dispatch timing
- Retry attempts and backoff delays
- Signature verification (debug mode)

### Alerts (v1.1+)
- Webhook success rate < 95% for 1 hour
- Delivery queue depth > 10,000 items
- Response time > 5 seconds (average)

---

## 18. Conclusion

This architecture provides a **scalable, reliable webhook system** for Numen that:

✅ Integrates seamlessly with existing Laravel events  
✅ Includes industry-standard HMAC-SHA256 signing  
✅ Handles retries with exponential backoff  
✅ Supports batch processing for high-volume scenarios  
✅ Provides comprehensive admin UI and API  
✅ Includes audit logging and delivery history  
✅ Follows Laravel best practices (jobs, queues, models)  
✅ Designed for multi-tenancy (space scoping)  
✅ Extensible for future enhancements (transformations, AI routing, etc.)

The system is **scope M** — event dispatcher, webhook model, delivery system, and admin UI — and depends on the Redis queue system already in place.

