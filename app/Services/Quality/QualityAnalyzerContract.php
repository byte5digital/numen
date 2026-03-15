<?php

namespace App\Services\Quality;

use App\Models\Content;

interface QualityAnalyzerContract
{
    public function analyze(Content $content): QualityDimensionResult;

    public function getDimension(): string;

    public function getWeight(): float;
}
