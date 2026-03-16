<?php

namespace App\Services\Quality\Contracts;

use App\Models\Content;
use App\Services\Quality\QualityDimensionResult;

interface QualityAnalyzerContract
{
    /**
     * Analyze the content and return a quality dimension result.
     */
    public function analyze(Content $content): QualityDimensionResult;

    /**
     * Get the dimension name this analyzer covers (e.g. "readability", "seo").
     */
    public function getDimension(): string;

    /**
     * Get the weight of this dimension in the overall score (0.0-1.0).
     */
    public function getWeight(): float;
}
