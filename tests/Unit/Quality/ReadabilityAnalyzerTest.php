<?php

namespace Tests\Unit\Quality;

use App\Models\Content;
use App\Models\ContentVersion;
use App\Services\Quality\ReadabilityAnalyzer;
use PHPUnit\Framework\TestCase;

class ReadabilityAnalyzerTest extends TestCase
{
    private ReadabilityAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new ReadabilityAnalyzer;
    }

    private function makeContent(string $body): Content
    {
        $version = new class($body) extends ContentVersion
        {
            /** @phpstan-ignore-next-line */
            public function __construct(public string $body) {}
        };

        return new class($version) extends Content
        {
            public ?ContentVersion $currentVersion;

            /** @phpstan-ignore-next-line */
            public ?ContentVersion $draftVersion = null;

            /** @phpstan-ignore-next-line */
            public function __construct(ContentVersion $v)
            {
                $this->currentVersion = $v;
            }
        };
    }

    private function makeNoVersion(): Content
    {
        return new class extends Content
        {
            public ?ContentVersion $currentVersion = null;

            public ?ContentVersion $draftVersion = null;

            /** @phpstan-ignore-next-line */
            public function __construct() {}
        };
    }

    public function test_returns_error_when_no_version(): void
    {
        $r = $this->analyzer->analyze($this->makeNoVersion());
        $this->assertSame(0.0, $r->getScore());
        $this->assertSame(1, $r->countByType('error'));
    }

    public function test_returns_error_for_empty_body(): void
    {
        $r = $this->analyzer->analyze($this->makeContent(''));
        $this->assertSame(0.0, $r->getScore());
        $this->assertSame(1, $r->countByType('error'));
    }

    public function test_dimension_and_weight(): void
    {
        $this->assertSame('readability', $this->analyzer->getDimension());
        $this->assertEqualsWithDelta(0.20, $this->analyzer->getWeight(), 0.001);
    }

    public function test_simple_text_scores_high(): void
    {
        $r = $this->analyzer->analyze($this->makeContent('The cat sat. It was fat. The cat ate a rat.'));
        $this->assertGreaterThan(60.0, $r->getScore());
        $this->assertArrayHasKey('flesch_score', $r->getMetadata());
    }

    public function test_metadata_keys(): void
    {
        $r = $this->analyzer->analyze($this->makeContent('This is a test. It has two.'));

        foreach (['word_count', 'sentence_count', 'paragraph_count', 'flesch_score'] as $key) {
            $this->assertArrayHasKey($key, $r->getMetadata());
        }
    }

    public function test_passive_voice_triggers_warning(): void
    {
        $s = str_repeat('The report was reviewed by the team. ', 5).str_repeat('We write active sentences. ', 5);
        $r = $this->analyzer->analyze($this->makeContent($s));
        $this->assertContains('warning', array_column($r->getItems(), 'type'));
    }

    public function test_html_is_stripped(): void
    {
        $r = $this->analyzer->analyze($this->makeContent('<p>Quick brown fox.</p><p>Lazy dog jumps.</p>'));
        $this->assertGreaterThan(0.0, $r->getScore());
    }

    public function test_score_in_range(): void
    {
        $r = $this->analyzer->analyze($this->makeContent('Short text.'));
        $this->assertGreaterThanOrEqual(0.0, $r->getScore());
        $this->assertLessThanOrEqual(100.0, $r->getScore());
    }
}
