# Performance Feedback Loop

> **Since:** v0.11.0 · **Issue:** [#43](https://github.com/byte5digital/numen/issues/43)

## Overview

The Performance Feedback Loop closes the gap between content creation and content outcomes. It ingests real-time engagement data (page views, clicks, scroll depth, conversions), aggregates it into daily/weekly/monthly snapshots, correlates content attributes with performance metrics, builds a predictive space-level model, and surfaces actionable insights — including A/B testing and automated content refresh suggestions.

Every brief you create benefits from what previous content taught the system.

## Architecture

```
Ingest -> Aggregate -> Correlate -> Model -> Insights -> Brief Enrichment
```

| Stage | Service | What it does |
|-------|---------|-------------|
| **Ingest** | `PerformanceIngestService` | Accepts events from tracking pixel, SDK, webhooks (GA4, Segment) |
| **Aggregate** | `PerformanceAggregatorService` | Rolls raw events into daily/weekly/monthly snapshots with composite scores |
| **Correlate** | `PerformanceCorrelatorService` | Finds statistical relationships between content attributes and performance metrics |
| **Model** | `SpacePerformanceModelService` | Builds a space-level predictive model (feature weights, recommendations) |
| **Insights** | `PerformanceInsightBuilder` | Generates human-readable insights from snapshots, correlations, and model |
| **Brief Enrichment** | `BriefEnrichmentService` | Injects performance learnings into new briefs automatically |
| **A/B Testing** | `ABTestService` | Runs controlled experiments with traffic splitting and statistical significance |
| **Content Refresh** | `ContentRefreshAdvisorService` | Identifies declining content and generates refresh suggestions |

### Data Flow

1. **Events arrive** via tracking pixel (`pixel.gif`), bulk SDK endpoint, or webhook (GA4/Segment format).
2. **Aggregation** rolls events into `ContentPerformanceSnapshot` rows with computed composite scores.
3. **Correlation analysis** examines content attributes (tone, length, topic, format) against metrics.
4. **Space model** computes feature weights and confidence scores from correlation data.
5. **Insights** are generated combining snapshots, correlations, and model predictions.
6. **Brief enrichment** automatically injects top-performing attributes into new briefs.

### Models

| Model | Table | Purpose |
|-------|-------|---------|
| `ContentPerformanceEvent` | `content_performance_events` | Raw engagement events |
| `ContentPerformanceSnapshot` | `content_performance_snapshots` | Aggregated period metrics |
| `PerformanceCorrelation` | `performance_correlations` | Attribute-to-metric correlations |
| `SpacePerformanceModel` | `space_performance_models` | Space-level predictive model |
| `ContentAbTest` | `content_ab_tests` | A/B test definitions |
| `ContentAbVariant` | `content_ab_variants` | Test variant tracking |
| `ContentRefreshSuggestion` | `content_refresh_suggestions` | Refresh advisor output |
| `ContentAttribute` | `content_attributes` | Extracted content features for correlation |

## API Endpoints

### Tracking (Public — No Auth)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/v1/track` | Legacy single-event intake |
| `GET` | `/api/v1/spaces/{space}/tracking/pixel.gif` | 1x1 tracking pixel |
| `POST` | `/api/v1/spaces/{space}/tracking/events` | Bulk event intake (up to 100) |
| `POST` | `/api/v1/performance/webhook` | External analytics webhook (GA4, Segment) |

### Performance Analytics (Auth Required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/spaces/{space}/performance/overview` | Dashboard overview (top performers, trends, model summary) |
| `GET` | `/api/v1/spaces/{space}/performance/snapshots` | List snapshots (filterable) |
| `GET` | `/api/v1/spaces/{space}/performance/snapshots/{snapshot}` | Single snapshot detail |
| `POST` | `/api/v1/spaces/{space}/performance/aggregate` | Trigger aggregation for content + period |
| `GET` | `/api/v1/spaces/{space}/performance/insights` | Space-wide insights |
| `GET` | `/api/v1/spaces/{space}/performance/insights/{contentId}` | Content-specific insights |
| `GET` | `/api/v1/spaces/{space}/performance/model` | View space performance model |
| `POST` | `/api/v1/spaces/{space}/performance/model/rebuild` | Rebuild performance model |
| `GET` | `/api/v1/spaces/{space}/performance/correlations` | List correlations (filterable) |
| `GET` | `/api/v1/spaces/{space}/performance/correlations/{contentId}` | Correlations for specific content |
| `POST` | `/api/v1/spaces/{space}/performance/correlations/analyze` | Run correlation analysis |

### A/B Testing (Auth Required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/v1/spaces/{space}/ab-tests` | Create a new A/B test |
| `GET` | `/api/v1/spaces/{space}/ab-tests` | List tests for space |
| `GET` | `/api/v1/spaces/{space}/ab-tests/{test}` | Show test with results |
| `POST` | `/api/v1/spaces/{space}/ab-tests/{test}/assign` | Assign visitor to variant |
| `POST` | `/api/v1/spaces/{space}/ab-tests/{test}/convert` | Record a conversion |
| `POST` | `/api/v1/spaces/{space}/ab-tests/{test}/end` | End test and declare winner |

### Content Refresh (Auth Required)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/v1/spaces/{space}/refresh-suggestions` | List suggestions (filterable by priority, status) |
| `GET` | `/api/v1/spaces/{space}/refresh-suggestions/{suggestion}` | Show suggestion detail |
| `POST` | `/api/v1/spaces/{space}/refresh-suggestions/generate` | Generate new suggestions |
| `POST` | `/api/v1/spaces/{space}/refresh-suggestions/{suggestion}/accept` | Accept and auto-generate refresh brief |
| `POST` | `/api/v1/spaces/{space}/refresh-suggestions/{suggestion}/dismiss` | Dismiss suggestion |

## A/B Testing Guide

### Creating a Test

```bash
curl -X POST /api/v1/spaces/{space}/ab-tests \
  -H "Authorization: Bearer {token}" \
  -d '{
    "name": "Headline Length Test",
    "hypothesis": "Shorter headlines increase click-through rate",
    "metric": "conversion_rate",
    "variants": [
      {"content_id": "CONTENT_ULID_1", "label": "Control", "is_control": true},
      {"content_id": "CONTENT_ULID_2", "label": "Short Headline", "is_control": false}
    ]
  }'
```

### Lifecycle

1. **Draft** — Test is created but not yet running.
2. **Running** — Starts automatically on first `assign` call. Visitors are deterministically split between variants.
3. **Completed** — Call `end` to conclude. The system calculates statistical significance and declares a winner (if any).

### Statistical Significance

The system uses a z-test for proportions. Results include z_score, p_value, confidence_level, lift_percentage, and is_significant.

## Content Refresh Advisor Guide

The advisor monitors content performance and proactively suggests updates for declining or underperforming content.

### Generating Suggestions

```bash
curl -X POST /api/v1/spaces/{space}/refresh-suggestions/generate \
  -H "Authorization: Bearer {token}"
```

Each suggestion includes trigger_type, urgency_score (0-100), recommended_changes, and expected_improvement.

### Filtering

```bash
# High priority only (urgency_score >= 50)
GET /api/v1/spaces/{space}/refresh-suggestions?priority=high

# Pending suggestions only
GET /api/v1/spaces/{space}/refresh-suggestions?status=pending
```

### Accepting a Suggestion

Accepting auto-generates a refresh brief for the content pipeline:

```bash
curl -X POST /api/v1/spaces/{space}/refresh-suggestions/{id}/accept
```

## Dashboard Usage Guide

The Performance Dashboard (Numen Studio) provides a visual interface with six components:

1. **PerformanceDashboard** — Main container, tab-based navigation
2. **PerformanceOverview** — Top performers, trend charts, model summary
3. **ContentPerformanceDetail** — Deep-dive into individual content metrics
4. **ABTestManager** — Create, monitor, and end A/B tests
5. **ContentRefreshAdvisor** — Review and act on refresh suggestions
6. **PerformanceCorrelations** — Explore attribute-to-metric relationships

### Key Interactions

- **Score Ring** — Visual composite performance score (0-100)
- **Trend Charts** — 14-day rolling view/score trends
- **Correlation Matrix** — Heatmap of attribute-metric relationships
- **A/B Test Cards** — Live experiment status with significance indicators
- **Refresh Queue** — Prioritized content needing attention

### Filters

All list views support per_page, content_id, period_type, from/to date range, attribute_name/metric_name (correlations), priority and status (refresh suggestions).
