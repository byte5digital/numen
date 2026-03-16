# Introducing AI Content Quality Scoring

_Published: 2026-03-16_

We're excited to announce **AI Content Quality Scoring** for Numen — a powerful new feature
that automatically evaluates every piece of content across five key dimensions, giving your
team actionable insights to ship higher-quality content, faster.

## What Is Content Quality Scoring?

Every content creator faces the same challenge: how do you know if your content is truly
ready to publish? Is it readable enough for your audience? Does it hit the right SEO signals?
Does it stay on-brand? Is it factually accurate?

Until now, answering these questions required manual review — a time-consuming process that
slows down publishing pipelines. With AI Content Quality Scoring, Numen does this automatically.

## Five Dimensions of Quality

Our scoring engine analyzes content across five dimensions:

1. **Readability** — Uses Flesch-Kincaid metrics to assess how easy your content is to read.
   Complex sentences and difficult vocabulary are flagged with specific suggestions.

2. **SEO** — Evaluates keyword density, heading structure, meta tags, and content length
   against SEO best practices.

3. **Brand Consistency** — Our LLM-powered analyzer checks your content against your brand
   guidelines, ensuring consistent voice, tone, and messaging.

4. **Factual Accuracy** — Cross-references claims against your knowledge base and common
   facts, flagging potential inaccuracies for human review.

5. **Engagement Prediction** — Predicts how engaging your content will be based on structure,
   emotional resonance, and proven engagement patterns.

## The Quality Dashboard

The new `/admin/quality` dashboard gives you a bird's-eye view of content quality across
your space:

- **Trend charts** showing how quality scores evolve over time
- **Space leaderboard** highlighting your top-performing content
- **Dimension breakdowns** showing where to focus improvement efforts
- **Distribution histograms** across all five dimensions

## Pipeline Quality Gates

Want to prevent low-quality content from being published automatically? Add a `quality_gate`
stage to your pipeline:

```json
{
  "name": "quality_check",
  "type": "quality_gate",
  "min_score": 75
}
```

If content doesn't meet the threshold, the pipeline pauses for human review. No more
accidentally publishing content that isn't ready.

## Auto-Score on Publish

Enable **Auto-score on publish** in Quality Settings, and every piece of content will be
automatically scored when it's published. Your quality metrics stay up-to-date without
any manual intervention.

## Webhooks

Integrate quality scores into your workflows with the new `quality.scored` webhook event.
Trigger Slack notifications, update your CMS, or feed scores into your analytics platform
whenever content is scored.

## Getting Started

1. Visit **Settings → Quality Scoring** to configure your dimensions and thresholds
2. Navigate to **Quality Dashboard** to see your space's quality trends
3. Open any content in the editor — the new **Quality Score Panel** in the sidebar shows
   the latest score and lets you trigger a re-score with one click

## What's Next

We're already working on the next iteration of quality scoring, including:
- Custom rubrics defined in natural language
- Cross-locale quality comparison
- Automated improvement suggestions with one-click application

---

_Numen is an open-source AI-powered CMS. [Contribute on GitHub →](https://github.com/byte5digital/numen)_
