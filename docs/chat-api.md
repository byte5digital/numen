# Conversational CMS API Documentation

> **Base URL:** `/api/v1/chat`
> **Auth:** All endpoints require `Authorization: Bearer {token}` (Laravel Sanctum).
> **Rate Limit:** 20 requests/minute (per user).

---

## Endpoints

### 1. List Conversations
```
GET /api/v1/chat/conversations
```
Paginated list of the user's conversations, ordered by most recent activity.

**Response 200:**
```json
{"data": [{"id": "01HX...", "space_id": "01HX...", "title": "Session", "last_active_at": "2026-03-15T12:00:00Z", "pending_action": null}], "meta": {"current_page": 1, "per_page": 20, "total": 1}}
```

### 2. Create Conversation
```
POST /api/v1/chat/conversations
```
**Body:** `{ "space_id": "01HX...", "title": "optional" }`

| Field | Type | Required |
|-------|------|----------|
| `space_id` | string (ULID) | Yes |
| `title` | string | No |

**Response 201:** `{ "data": { "id": "01HX...", ... } }`

---

### 3. Delete Conversation
```
DELETE /api/v1/chat/conversations/{id}
```
**Response 204:** No content.

---

### 4. Get Message History
```
GET /api/v1/chat/conversations/{id}/messages
```
Paginated message history (50/page, oldest first).

**Response 200:**
```json
{
  "data": [
    {"id": "01HX...", "role": "user", "content": "How many published articles?", "intent": null, "cost_usd": null},
    {"id": "01HX...", "role": "assistant", "content": "You have 42 published articles.", "intent": {"action": "content.query", "entity": "content", "params": {"status": "published"}, "confidence": 0.95, "requires_confirmation": false}, "input_tokens": 512, "output_tokens": 128, "cost_usd": 0.00042}
  ]
}
```

---

### 5. Send Message — SSE Stream
```
POST /api/v1/chat/conversations/{id}/messages
Content-Type: application/json
Accept: text/event-stream
```
Sends a user message and streams the assistant's response as Server-Sent Events.

**Body:** `{ "message": "Create a blog post about summer recipes" }`

| Field | Type | Required | Max |
|-------|------|----------|-----|
| `message` | string | Yes | 4000 chars |

**Response headers:**
```
Content-Type: text/event-stream
Cache-Control: no-cache
X-Accel-Buffering: no
X-RateLimit-Remaining: 18
X-RateLimit-Reset: 1710504060
```
Response body: see SSE Streaming Format below.

**Response 429:**
```json
{"error": "Rate limit exceeded.", "quota": {"messages_remaining": 0, "cost_remaining_usd": 0.0, "resets_at": "2026-03-15T13:00:00Z"}}
```

---

### 6. Confirm Pending Action
```
POST /api/v1/chat/conversations/{id}/confirm
```
Executes the action stored in `conversation.pending_action`.

**Response 200:**
```json
{"data": {"confirmed": true, "action": {"action": "content.delete", "entity": "content", "params": {"id": "01HX..."}, "confidence": 0.98, "requires_confirmation": true}}}
```
**Response 422:** `{"error": "No pending action"}`

---

### 7. Cancel Pending Action
```
DELETE /api/v1/chat/conversations/{id}/confirm
```
**Response 200:** `{"data": {"cancelled": true}}`

---

### 8. Get Suggestions
```
GET /api/v1/chat/suggestions?space_id={id}&route={route}
```
Context-aware suggestion chips for the current UI route.

| Param | Required | Description |
|-------|----------|-------------|
| `space_id` | Yes | Current space ULID |
| `route` | No | Current UI route e.g. `content.index` |

**Response 200:**
```json
{"data": ["Show me recent drafts", "Trigger the SEO pipeline", "How many articles this week?", "Create a blog post"]}
```

---

## SSE Streaming Format

The `/messages` endpoint returns [Server-Sent Events](https://developer.mozilla.org/en-US/docs/Web/API/Server-sent_events). Each event is a JSON object prefixed `data: `. The stream ends with `data: [DONE]`.

**Example stream:**
```
data: {"type":"text","content":"You have 42 published articles in this space."}

data: {"type":"intent","intent":{"action":"content.query","entity":"content","params":{"status":"published"},"confidence":0.95,"requires_confirmation":false}}

data: {"type":"done","cost_usd":0.00042}

data: [DONE]
```

### Chunk Types

| Type | Description | Key Fields |
|------|-------------|-----------|
| `text` | Assistant's human-readable reply | `content: string` |
| `intent` | Extracted CMS action | `intent: Intent` |
| `action` | Auto-executed action result | `result: ActionResult` |
| `confirm` | Action requiring user confirmation | `intent: Intent`, `message: string` |
| `error` | Recoverable mid-stream error | `message: string`, `code?: string` |
| `done` | Stream complete, cost summary | `cost_usd: number` |

### Intent Object
```typescript
interface Intent {
  action: string;                    // e.g. "content.create"
  entity: string;                    // e.g. "content"
  params: Record<string, unknown>;
  confidence: number;                // 0.0 – 1.0
  requires_confirmation: boolean;
}
```

### Chunk Examples

**text:** `{"type":"text","content":"I'll create a blog post for you."}`

**intent:**
```json
{"type":"intent","intent":{"action":"content.create","entity":"content","params":{"title":"Summer Recipes","type":"blog_post"},"confidence":0.92,"requires_confirmation":true}}
```

**confirm:**
```json
{"type":"confirm","message":"I'm about to delete 'Summer Recipes'. Are you sure?","intent":{"action":"content.delete","entity":"content","params":{"id":"01HX..."},"confidence":0.98,"requires_confirmation":true}}
```

**action:**
```json
{"type":"action","result":{"success":true,"result":{"id":"01HX...","status":"draft"},"message":"Content item created."}}
```

**error:** `{"type":"error","message":"LLM provider unavailable.","code":"llm_error"}`

**done:** `{"type":"done","cost_usd":0.00038}`

---

## Intent Actions

| Action | Description | Confirm Required |
|--------|-------------|-----------------|
| `content.query` | Search/filter content items | No |
| `content.create` | Create content (triggers pipeline) | Yes (configurable) |
| `content.update` | Update existing content | Yes |
| `content.delete` | Delete content | Yes |
| `content.publish` | Set status to `published` | Yes |
| `content.unpublish` | Set status to `draft` | No |
| `pipeline.trigger` | Trigger an AI pipeline run | Yes |
| `query.generic` | General question — no CMS action | No |

Actions marked **Yes** emit a `confirm` chunk. Call `POST /confirm` to execute or `DELETE /confirm` to cancel.
Override via env: `CHAT_CONFIRMATION_REQUIRED_ACTIONS=content.create,content.delete,...`

---

## Rate Limiting

- **Per-minute:** 20 requests/minute per user (`CHAT_MAX_MESSAGES_PER_MINUTE`)
- **Daily cost:** $1.00/day per user (`CHAT_MAX_DAILY_COST`)

Both limits return HTTP 429 when exceeded.

### Response Headers

| Header | Description |
|--------|-------------|
| `X-RateLimit-Remaining` | Messages remaining this minute |
| `X-RateLimit-Reset` | Unix timestamp of window reset |
| `Retry-After` | Seconds to wait (429 only) |

---

## Error Responses

| Status | Meaning |
|--------|---------|
| `401` | Missing or invalid Bearer token |
| `403` | Not authorized for this resource |
| `404` | Conversation not found or belongs to another user |
| `422` | Validation error (field-level details in `errors`) |
| `429` | Rate limit or daily cost budget exceeded |
| `500` | Internal server error |
