# AI Content Quality Scoring

**Version:** 1.0.0  
**Added:** v0.10.0 (2026-03-16)  
**Status:** Stable

Numen's AI-powered quality scoring system provides automated, multi-dimensional content analysis before publishing. Every piece of content gets scored across five dimensions, with configurable thresholds, quality gates in publishing pipelines, and real-time dashboard visualization.

---

## Overview

Traditional content workflows rely on human review to catch quality issues. Numen automates this with an intelligent scoring engine that:

- **Analyzes on-demand** — Manually trigger scoring for any content item via REST API
- **Analyzes on-publish** — Auto-score content when it's published (configurable per space)
- **Gates pipelines** — Stop pipeline execution if quality falls below a threshold
- **Trends & insights** — Dashboard shows historical trends, leaderboards, and score distributions
- **Configurable** — Customize dimension weights, thresholds, and which analyzers to enable

---

## Scoring Dimensions

Numen scores content across **five analyzers**, each producing a 0–100 score:

### 1. Readability

Measures how easy the content is to understand using Flesch-Kincaid metrics, sentence length, word complexity.

### 2. SEO

Evaluates search engine optimization: keyword density, heading structure, meta tags, internal linking.

### 3. Brand Consistency

LLM-powered analysis of tone, voice, and alignment with brand guidelines.

### 4. Factual Accuracy

LLM-powered fact-checking of claims against knowledge base and reference materials.

### 5. Engagement Prediction

AI-predicted engagement score based on content structure, length, CTAs, and topic patterns.

---

## Score Scale

All dimensions use a **0–100 scale**. The **Overall Score** is a weighted average (configurable per space).

| Range | Label | Meaning | Action |
|-------|-------|---------|--------|
| 90–100 | Excellent | Ready to publish | ✅ Publish |
| 75–89 | Good | Minor improvements possible | ✅ Publish or revise |
| 60–74 | Fair | Consider improvements | ⚠️ Request revisions |
| 40–59 | Poor | Significant improvements needed | 🚫 Pause pipeline |
| 0–39 | Critical | Major revision required | 🚫 Reject |

---

## Configuration

Each space has a `ContentQualityConfig` controlling weights, thresholds, and enabled dimensions.

**Configuration UI:** Admin Panel → Settings → Quality Scoring

**Key Settings:**
- **Dimension Weights:** Adjust which dimensions matter most (default: Readability 25%, SEO 25%, Brand 20%, Factual 15%, Engagement 15%)
- **Thresholds:** Define custom tier cutoffs
- **Enabled Dimensions:** Toggle analyzers on/off
- **Auto-score on Publish:** Enable/disable automatic scoring when content publishes
- **Pipeline Gating:** Enable quality gate in pipelines with minimum score threshold

---

## Dashboard & Analytics

Access at **Admin Panel → Quality Scoring**:

**Dashboard Features:**
- **Trend Chart:** Daily overall score and dimension breakdowns with date range picker
- **Space Leaderboard:** Top 10 highest-scoring content items
- **Score Distribution:** Histogram showing percentage in each quality tier
- **Quick Stats:** Average score, trend direction, total items scored

**Editor Integration:**
When editing content, a **Quality Score Ring** appears in the sidebar showing:
- Visual ring with overall score (color-coded by tier)
- Dimension breakdown with individual scores
- "Score Now" button for on-demand scoring
- Dimension-specific insights and suggestions

---

## Pipeline Integration: Quality Gate

Add a `quality_gate` stage to enforce quality before publishing.

**Pipeline Definition Example:**

```json
{
  "stages": [
    { "type": "ai_generate", "model": "claude-sonnet-4-6" },
    { "type": "quality_gate", "min_score": 75 },
    { "type": "auto_publish" }
  ]
}
```

**Behavior:**
- When content reaches the quality gate, it's scored
- If score ≥ min_score: pipeline continues
- If score < min_score: pipeline pauses with status `paused_for_review`
- Editorial team gets notification and can view scores in editor

---

## REST API Reference

All endpoints require Sanctum authentication.

### List Quality Scores

```
GET /api/v1/quality/scores?space_id={spaceId}&content_id={contentId}&per_page=20
```

Returns paginated list of quality scores with dimension items.

### Get Single Score

```
GET /api/v1/quality/scores/{scoreId}
```

Returns single quality score with full analysis and dimension items.

### Trigger Quality Scoring

```
POST /api/v1/quality/score
```

**Request:**
```json
{ "content_id": "01J..." }
```

**Response (202):** Job queued. Check `/api/v1/quality/scores` to poll for results.

### Get Trends & Analytics

```
GET /api/v1/quality/trends?space_id={spaceId}&from=2026-02-15&to=2026-03-16
```

Returns daily trends, leaderboard, and score distribution for date range.

### Get Quality Config

```
GET /api/v1/quality/config?space_id={spaceId}
```

Returns current configuration. Creates default if none exists.

### Update Quality Config

```
PUT /api/v1/quality/config
```

**Requires:** `settings.manage` permission

**Request:**
```json
{
  "space_id": "01J...",
  "auto_score_on_publish": true,
  "pipeline_gate_enabled": true,
  "pipeline_gate_min_score": 75,
  "enabled_dimensions": ["readability", "seo", "brand_consistency"],
  "dimension_weights": {
    "readability": 0.4,
    "seo": 0.4,
    "brand_consistency": 0.2
  }
}
```

---

## Webhooks

### `quality.scored`

Fired when content is successfully scored.

**Payload:**
```json
{
  "event": "quality.scored",
  "timestamp": "2026-03-16T10:00:00Z",
  "data": {
    "score_id": "01J...",
    "content_id": "01J...",
    "space_id": "01J...",
    "overall_score": 82.5,
    "readability_score": 88.0,
    "seo_score": 79.0,
    "brand_consistency_score": 85.0,
    "factual_accuracy_score": 91.0,
    "engagement_prediction_score": 70.0,
    "scored_at": "2026-03-16T10:00:00Z"
  }
}
```

**Use case:** Slack notifications, analytics sync, downstream integrations.

---

## Security & Rate Limiting

### Authentication
All endpoints require Sanctum bearer token. Space membership is validated.

### Rate Limiting
- Scoring requests: 100 per space per minute
- API reads: Standard Numen limits (200 req/min per user)

### Prompt Fencing
LLM-based analyzers use strict prompt fencing to prevent injection:
- User content is never concatenated into prompts
- All input is structured, not strings
- Outputs are sanitized before storage

### Cost Control
- Each LLM call logged to `AIGenerationLog`
- Admins can set per-space daily budgets
- Scoring respects budget limits

---

## Performance

- **Typical scoring time:** 2–4 seconds
- **Execution:** Async background jobs; API returns 202 immediately
- **LLM Model:** Claude Haiku by default (configurable)
- **Cost estimate:** ~2–3¢ per score (LLM-based analyzers only; Readability/SEO free)

---

## Troubleshooting

### "Scoring timed out"
- Disable expensive analyzers (Brand, Factual) temporarily
- Increase timeout: `QUALITY_ANALYZER_TIMEOUT=60`
- Check LLM API status

### "Low score for good content"
- Adjust dimension weights in Settings → Quality Scoring
- Review dimension feedback in editor sidebar
- Disable irrelevant analyzers

### "API returns 429 (Too Many Requests)"
- Implement exponential backoff in client
- Keep requests under 100/min per space
- Batch operations coming v0.11.0

### "Dashboard shows no data"
- Enable `auto_score_on_publish` and publish items, OR
- Manually trigger: `POST /api/v1/quality/score` for existing items
- Wait 5–10 minutes for trend aggregation

---

## FAQ

**Q: Can I disable quality scoring?**
A: Yes. Don't enable auto-scoring and remove quality gates from pipelines. You can still score on-demand.

**Q: Does scoring affect publication latency?**
A: Auto-scoring is async (no impact). Pipeline quality gates are synchronous (adds 2–4 seconds).

**Q: What LLM do analyzers use?**
A: Claude Haiku by default (configurable via `QUALITY_SCORER_MODEL`).

**Q: How much does it cost?**
A: Only LLM analyzers cost (~2–3¢ per score). Readability and SEO are free. See `AIGenerationLog` for exact costs.

**Q: Can I train a custom model?**
A: Not yet. Custom models on roadmap for v0.12.0+.

---

## References

- [Quality Scoring API Reference](api/quality-api.md)
- [Blog Post: Introducing Quality Scoring](blog/quality-scoring-launch.md)
- [OpenAPI Spec](../openapi.yaml) — Search for `Quality Scoring` tag

