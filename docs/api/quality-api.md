# Content Quality Scoring API Reference

**Version:** 1.0.0  
**Base URL:** `/api/v1/quality`  
**Added:** 2026-03-16

All endpoints require Sanctum authentication. Permission `content.view` is required
for read operations; `settings.manage` is required for config updates.

---

## Endpoints

### 1. GET /api/v1/quality/scores

List quality scores for a space, optionally filtered by content item.

**Query parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `space_id` | string (ULID) | ✅ | Space to filter by |
| `content_id` | string (ULID) | ❌ | Filter to specific content item |
| `per_page` | integer (1–100) | ❌ | Scores per page (default: 20) |

**Response:**
```json
{
  "data": [
    {
      "id": "01J...",
      "space_id": "01J...",
      "content_id": "01J...",
      "content_version_id": "01J...",
      "overall_score": 82.5,
      "dimensions": {
        "readability": 88.0,
        "seo": 79.0,
        "brand": 85.0,
        "factual": 91.0,
        "engagement": 70.0
      },
      "scoring_model": "content-quality-v1",
      "scoring_duration_ms": 1240,
      "scored_at": "2026-03-16T10:00:00Z",
      "items": []
    }
  ],
  "links": {...},
  "meta": {...}
}
```

---

### 2. GET /api/v1/quality/scores/{score}

Get a single quality score with its dimension items.

**Response:** Single `ContentQualityScoreResource` with `items` array.

---

### 3. POST /api/v1/quality/score

Trigger an async quality scoring job for a content item.

**Request body:**
```json
{ "content_id": "01J..." }
```

**Response (202):**
```json
{ "message": "Quality scoring job queued.", "content_id": "01J..." }
```

---

### 4. GET /api/v1/quality/trends

Aggregate daily trend data, leaderboard, and dimension distributions for a space.

**Query parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `space_id` | string (ULID) | ✅ | Space to query |
| `from` | date (YYYY-MM-DD) | ❌ | Start date (default: 30 days ago) |
| `to` | date (YYYY-MM-DD) | ❌ | End date (default: today) |

**Response:**
```json
{
  "data": {
    "trends": {
      "2026-03-15": {
        "overall": 81.3,
        "readability": 85.2,
        "seo": 78.4,
        "brand": 82.0,
        "factual": 90.1,
        "engagement": 71.5,
        "total": 12
      }
    },
    "leaderboard": [
      {
        "score_id": "01J...",
        "content_id": "01J...",
        "title": "10 Ways to Write Better Content",
        "overall_score": 94.5,
        "scored_at": "2026-03-16T09:00:00Z"
      }
    ],
    "distribution": {
      "overall": { "0-10": 0, "10-20": 1, "20-30": 2, ..., "90-100": 8 }
    },
    "period": { "from": "2026-02-14", "to": "2026-03-16" }
  }
}
```

---

### 5. GET /api/v1/quality/config

Get quality configuration for a space. Creates a default config if none exists.

**Query parameters:** `space_id` (required)

**Response:**
```json
{
  "data": {
    "id": "01J...",
    "space_id": "01J...",
    "dimension_weights": {
      "readability": 0.25,
      "seo": 0.25,
      "brand_consistency": 0.20,
      "factual_accuracy": 0.15,
      "engagement_prediction": 0.15
    },
    "thresholds": { "poor": 40, "fair": 60, "good": 75, "excellent": 90 },
    "enabled_dimensions": ["readability", "seo", "brand_consistency", "factual_accuracy", "engagement_prediction"],
    "auto_score_on_publish": true,
    "pipeline_gate_enabled": false,
    "pipeline_gate_min_score": 70.0,
    "created_at": "2026-03-16T08:00:00Z",
    "updated_at": "2026-03-16T08:00:00Z"
  }
}
```

---

### 6. PUT /api/v1/quality/config

Update quality configuration for a space. Requires `settings.manage` permission.

**Request body:**
```json
{
  "space_id": "01J...",
  "pipeline_gate_enabled": true,
  "pipeline_gate_min_score": 75,
  "auto_score_on_publish": true,
  "enabled_dimensions": ["readability", "seo"],
  "dimension_weights": { "readability": 0.5, "seo": 0.5 }
}
```

**Response:** Updated `ContentQualityConfigResource`.

---

## Webhook Event

### `quality.scored`

Fired when a content item is successfully scored.

**Payload:**
```json
{
  "id": "01J...",
  "event": "quality.scored",
  "timestamp": "2026-03-16T10:00:00Z",
  "data": {
    "score_id": "01J...",
    "content_id": "01J...",
    "space_id": "01J...",
    "overall_score": 82.5,
    "readability_score": 88.0,
    "seo_score": 79.0,
    "brand_score": 85.0,
    "factual_score": 91.0,
    "engagement_score": 70.0,
    "scored_at": "2026-03-16T10:00:00Z"
  }
}
```

---

## Pipeline Stage: `quality_gate`

Add a `quality_gate` stage to any pipeline to enforce quality thresholds before publishing.

**Pipeline definition example:**
```json
{
  "stages": [
    { "name": "ai_generate", "type": "ai_generate" },
    {
      "name": "quality_check",
      "type": "quality_gate",
      "min_score": 75
    },
    { "name": "publish", "type": "auto_publish" }
  ]
}
```

If `min_score` is omitted, the stage uses the space's `pipeline_gate_min_score` config (default: 70).

If the score is below the threshold, the pipeline is paused with status `paused_for_review`.

---

## Scoring Dimensions

| Dimension | Key | Description |
|-----------|-----|-------------|
| Readability | `readability` | Flesch-Kincaid score, sentence length, word complexity |
| SEO | `seo` | Keyword density, meta tags, heading structure |
| Brand Consistency | `brand_consistency` | Tone, voice, and brand guideline adherence (LLM-based) |
| Factual Accuracy | `factual_accuracy` | Fact-check claims against knowledge base (LLM-based) |
| Engagement Prediction | `engagement_prediction` | Predicted engagement based on content patterns (LLM-based) |

## Score Interpretation

| Range | Label | Meaning |
|-------|-------|---------|
| 90–100 | Excellent | Ready to publish, high-quality |
| 75–89 | Good | Publish-ready, minor improvements possible |
| 60–74 | Fair | Consider improvements before publishing |
| 40–59 | Poor | Significant improvements needed |
| 0–39 | Critical | Major revision required |
