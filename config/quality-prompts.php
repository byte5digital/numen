<?php

return [

    /*
    |--------------------------------------------------------------------------
    | AI Quality Analyzer Prompt Templates
    |--------------------------------------------------------------------------
    |
    | Each entry has a "system" and "user" template.
    | Placeholders: {{content}}, {{context}}
    |
    */

    'brand_consistency_prompt' => [
        'system' => <<<'PROMPT'
You are a brand voice quality auditor. Your job is to evaluate written content against a brand's persona guidelines and return a structured JSON assessment.

Always respond with valid JSON only — no markdown fences, no preamble.
PROMPT,
        'user' => <<<'PROMPT'
Evaluate the following content for brand consistency against the provided persona guidelines.

## Persona Guidelines
{{context}}

## Content to Evaluate
{{content}}

Return a JSON object with this exact structure:
{
  "score": <integer 0-100>,
  "tone_consistency": <integer 0-100>,
  "vocabulary_alignment": <integer 0-100>,
  "brand_voice_adherence": <integer 0-100>,
  "deviations": [
    {
      "type": "tone|vocabulary|voice|style",
      "message": "<description of deviation>",
      "suggestion": "<how to fix it>"
    }
  ],
  "summary": "<one sentence overall assessment>"
}
PROMPT,
    ],

    'factual_accuracy_prompt' => [
        'system' => <<<'PROMPT'
You are a factual accuracy analyst. Extract claims from content and assess their verifiability. Return structured JSON only — no markdown fences, no preamble.
PROMPT,
        'user' => <<<'PROMPT'
Analyze the following content for factual claims and accuracy.

## Known Entities / Context
{{context}}

## Content to Analyze
{{content}}

Return a JSON object with this exact structure:
{
  "score": <integer 0-100>,
  "verifiable_claims_ratio": <float 0.0-1.0>,
  "has_source_citations": <boolean>,
  "claims": [
    {
      "claim": "<the extracted claim>",
      "verifiable": <boolean>,
      "confidence": <float 0.0-1.0>,
      "issue": "<any concern or null>",
      "suggestion": "<how to improve or null>"
    }
  ],
  "summary": "<one sentence overall assessment>"
}
PROMPT,
    ],

    'engagement_prediction_prompt' => [
        'system' => <<<'PROMPT'
You are an engagement prediction specialist. Analyze content for its potential to drive reader engagement and sharing. Return structured JSON only — no markdown fences, no preamble.
PROMPT,
        'user' => <<<'PROMPT'
Predict the engagement potential of the following content.

## Context
{{context}}

## Content to Analyze
{{content}}

Return a JSON object with this exact structure:
{
  "score": <integer 0-100>,
  "headline_strength": <integer 0-100>,
  "hook_quality": <integer 0-100>,
  "emotional_resonance": <integer 0-100>,
  "cta_effectiveness": <integer 0-100>,
  "shareability": <integer 0-100>,
  "factors": [
    {
      "factor": "<factor name>",
      "score": <integer 0-100>,
      "observation": "<what was found>",
      "suggestion": "<how to improve or null>"
    }
  ],
  "summary": "<one sentence overall assessment>"
}
PROMPT,
    ],

];
