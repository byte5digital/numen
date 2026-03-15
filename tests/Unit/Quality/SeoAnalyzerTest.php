<?php

namespace Tests\Unit\Quality;

use App\Models\Content;
use App\Models\ContentVersion;
use App\Services\Quality\SeoAnalyzer;
use PHPUnit\Framework\TestCase;

class SeoAnalyzerTest extends TestCase
{
    private SeoAnalyzer $analyzer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->analyzer = new SeoAnalyzer;
    }

    /** @param array<string, mixed> $sd */
    private function makeContent(string $title, string $meta, string $body, array $sd = []): Content
    {
        $version = new class($title, $meta, $body, $sd) extends ContentVersion
        {
            /** @param array<string, mixed> $seoData */
            /** @phpstan-ignore-next-line */
            public function __construct(
                public string $title,
                public string $meta_description,
                public string $body,
                public array $seo_data = [],
            ) {}
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

    public function test_dimension_and_weight(): void
    {
        $this->assertSame('seo', $this->analyzer->getDimension());
        $this->assertEqualsWithDelta(0.25, $this->analyzer->getWeight(), 0.001);
    }

    public function test_returns_error_when_no_version(): void
    {
        $result = $this->analyzer->analyze($this->makeNoVersion());
        $this->assertSame(0.0, $result->getScore());
        $this->assertSame(1, $result->countByType('error'));
    }

    public function test_good_content_scores_reasonably(): void
    {
        $title = 'How To Build a Modern CMS With Laravel Easily';
        $meta = 'This is an optimal meta description with over one hundred chars here for the test purpose.';
        $body = '<h1>Main heading</h1><h2>Sub</h2><p>Content <a href="/about">internal</a>.</p>';
        $result = $this->analyzer->analyze($this->makeContent($title, $meta, $body));
        $this->assertGreaterThan(30.0, $result->getScore());
    }

    public function test_missing_title_gives_error(): void
    {
        $result = $this->analyzer->analyze($this->makeContent('', '', '<p>body</p>'));
        $this->assertContains('error', array_column($result->getItems(), 'type'));
    }

    public function test_missing_h1_gives_error(): void
    {
        $body = '<h2>Sub heading only</h2><p>No H1 here.</p>';
        $result = $this->analyzer->analyze($this->makeContent('Title here', '', $body));
        $this->assertContains('error', array_column($result->getItems(), 'type'));
    }

    public function test_multiple_h1_gives_warning(): void
    {
        $body = '<h1>First H1</h1><h1>Second H1</h1><p>Content here.</p>';
        $result = $this->analyzer->analyze($this->makeContent('Title with chars', '', $body));
        $this->assertContains('warning', array_column($result->getItems(), 'type'));
    }

    public function test_images_without_alt_flagged(): void
    {
        $body = '<h1>Heading</h1><img src="a.jpg"><img src="b.jpg"><img src="c.jpg">';
        $result = $this->analyzer->analyze($this->makeContent('Title here', '', $body));
        $types = array_column($result->getItems(), 'type');
        $this->assertTrue(in_array('warning', $types) || in_array('error', $types));
    }

    public function test_images_with_alt_not_flagged_for_alt(): void
    {
        $body = '<h1>Title</h1><img src="a.jpg" alt="desc"><img src="b.jpg" alt="desc2">';
        $result = $this->analyzer->analyze($this->makeContent('A good title here', '', $body));
        $altItems = array_filter($result->getItems(), fn ($i) => ($i['type'] ?? '') === 'image_alt_missing');
        $this->assertCount(0, $altItems);
    }

    public function test_score_in_range(): void
    {
        $result = $this->analyzer->analyze($this->makeContent('', '', ''));
        $this->assertGreaterThanOrEqual(0.0, $result->getScore());
        $this->assertLessThanOrEqual(100.0, $result->getScore());
    }

    public function test_metadata_has_expected_keys(): void
    {
        $result = $this->analyzer->analyze($this->makeContent('Title here', '', '<h1>H1</h1>'));
        $meta = $result->getMetadata();
        $this->assertArrayHasKey('title_length', $meta);
        $this->assertArrayHasKey('meta_desc_length', $meta);
    }
}
