<?php

namespace Tests\Unit\Search;

use App\Services\Search\SearchCapabilities;
use Tests\TestCase;

class SearchCapabilitiesTest extends TestCase
{
    // ── Tier Flags ───────────────────────────────────────────────────────────

    public function test_has_instant_returns_true_when_instant_available_and_enabled(): void
    {
        config(['numen.search.tiers_enabled.instant' => true]);
        $caps = new SearchCapabilities(instant: true, semantic: false, ask: false);

        $this->assertTrue($caps->hasInstant());
    }

    public function test_has_instant_returns_false_when_instant_unavailable(): void
    {
        config(['numen.search.tiers_enabled.instant' => true]);
        $caps = new SearchCapabilities(instant: false, semantic: false, ask: false);

        $this->assertFalse($caps->hasInstant());
    }

    public function test_has_instant_returns_false_when_tier_disabled_in_config(): void
    {
        config(['numen.search.tiers_enabled.instant' => false]);
        $caps = new SearchCapabilities(instant: true, semantic: false, ask: false);

        $this->assertFalse($caps->hasInstant());
    }

    public function test_has_semantic_returns_true_when_semantic_available_and_enabled(): void
    {
        config(['numen.search.tiers_enabled.semantic' => true]);
        $caps = new SearchCapabilities(instant: false, semantic: true, ask: false);

        $this->assertTrue($caps->hasSemantic());
    }

    public function test_has_semantic_returns_false_when_semantic_unavailable(): void
    {
        $caps = new SearchCapabilities(instant: false, semantic: false, ask: false);

        $this->assertFalse($caps->hasSemantic());
    }

    public function test_has_semantic_returns_false_when_tier_disabled_in_config(): void
    {
        config(['numen.search.tiers_enabled.semantic' => false]);
        $caps = new SearchCapabilities(instant: false, semantic: true, ask: false);

        $this->assertFalse($caps->hasSemantic());
    }

    public function test_has_ask_returns_true_when_ask_available_and_enabled(): void
    {
        config(['numen.search.tiers_enabled.ask' => true]);
        $caps = new SearchCapabilities(instant: false, semantic: false, ask: true);

        $this->assertTrue($caps->hasAsk());
    }

    public function test_has_ask_returns_false_when_ask_unavailable(): void
    {
        $caps = new SearchCapabilities(instant: false, semantic: false, ask: false);

        $this->assertFalse($caps->hasAsk());
    }

    public function test_has_ask_returns_false_when_tier_disabled_in_config(): void
    {
        config(['numen.search.tiers_enabled.ask' => false]);
        $caps = new SearchCapabilities(instant: false, semantic: false, ask: true);

        $this->assertFalse($caps->hasAsk());
    }

    // ── toArray ───────────────────────────────────────────────────────────────

    public function test_to_array_reflects_effective_capabilities(): void
    {
        config([
            'numen.search.tiers_enabled.instant' => true,
            'numen.search.tiers_enabled.semantic' => true,
            'numen.search.tiers_enabled.ask' => false,
        ]);
        $caps = new SearchCapabilities(instant: true, semantic: true, ask: true);

        $arr = $caps->toArray();

        $this->assertTrue($arr['instant']);
        $this->assertTrue($arr['semantic']);
        $this->assertFalse($arr['ask']); // disabled in config
    }

    // ── All Unavailable ───────────────────────────────────────────────────────

    public function test_all_tiers_unavailable_caps(): void
    {
        $caps = new SearchCapabilities(instant: false, semantic: false, ask: false);

        $this->assertFalse($caps->hasInstant());
        $this->assertFalse($caps->hasSemantic());
        $this->assertFalse($caps->hasAsk());
    }
}
