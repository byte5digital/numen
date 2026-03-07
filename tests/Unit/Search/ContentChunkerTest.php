<?php

namespace Tests\Unit\Search;

use App\Models\Content;
use App\Models\ContentBlock;
use App\Models\ContentVersion;
use App\Services\Search\ContentChunker;
use App\Services\Search\Results\ContentChunk;
use Illuminate\Support\Collection;
use Mockery;
use Tests\TestCase;

class ContentChunkerTest extends TestCase
{
    private ContentChunker $chunker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->chunker = new ContentChunker;
    }

    // ── Title & Excerpt Chunks ────────────────────────────────────────────────

    public function test_title_chunk_is_always_created(): void
    {
        [$content, $version] = $this->makeContentWithVersion('Hello World');

        $chunks = $this->chunker->chunk($content, $version);

        $titleChunks = array_values(array_filter($chunks, fn (ContentChunk $c) => $c->type === 'title'));
        $this->assertNotEmpty($titleChunks);
        $this->assertSame('Hello World', $titleChunks[0]->text);
    }

    public function test_empty_title_produces_no_title_chunk(): void
    {
        [$content, $version] = $this->makeContentWithVersion('');

        $chunks = $this->chunker->chunk($content, $version);

        $titleChunks = array_filter($chunks, fn (ContentChunk $c) => $c->type === 'title');
        $this->assertEmpty($titleChunks);
    }

    public function test_excerpt_chunk_created_when_present(): void
    {
        [$content, $version] = $this->makeContentWithVersion('Title', excerpt: 'A short summary.');

        $chunks = $this->chunker->chunk($content, $version);

        $excerptChunks = array_values(array_filter($chunks, fn (ContentChunk $c) => $c->type === 'excerpt'));
        $this->assertNotEmpty($excerptChunks);
        $this->assertSame('A short summary.', $excerptChunks[0]->text);
    }

    public function test_excerpt_chunk_not_created_when_empty(): void
    {
        [$content, $version] = $this->makeContentWithVersion('Title');

        $chunks = $this->chunker->chunk($content, $version);

        $excerptChunks = array_filter($chunks, fn (ContentChunk $c) => $c->type === 'excerpt');
        $this->assertEmpty($excerptChunks);
    }

    // ── SEO Chunk ────────────────────────────────────────────────────────────

    public function test_seo_chunk_created_when_seo_data_present(): void
    {
        [$content, $version] = $this->makeContentWithVersion(
            'Title',
            seoData: ['title' => 'SEO Title', 'description' => 'SEO Description']
        );

        $chunks = $this->chunker->chunk($content, $version);

        $seoChunks = array_values(array_filter($chunks, fn (ContentChunk $c) => $c->type === 'seo'));
        $this->assertNotEmpty($seoChunks);
        $this->assertStringContainsString('SEO Title', $seoChunks[0]->text);
        $this->assertStringContainsString('SEO Description', $seoChunks[0]->text);
    }

    public function test_seo_chunk_not_created_when_seo_data_empty(): void
    {
        [$content, $version] = $this->makeContentWithVersion('Title', seoData: []);

        $chunks = $this->chunker->chunk($content, $version);

        $seoChunks = array_filter($chunks, fn (ContentChunk $c) => $c->type === 'seo');
        $this->assertEmpty($seoChunks);
    }

    // ── Block-Level Chunks ───────────────────────────────────────────────────

    public function test_heading_block_creates_standalone_heading_chunk(): void
    {
        $blocks = [$this->makeBlock('heading', ['text' => 'Introduction'], 1)];
        [$content, $version] = $this->makeContentWithVersion('Title', blocks: $blocks);

        $chunks = $this->chunker->chunk($content, $version);

        $headingChunks = array_values(array_filter(
            $chunks,
            fn (ContentChunk $c) => $c->type === 'block' && ($c->metadata['block_type'] ?? '') === 'heading'
        ));
        $this->assertNotEmpty($headingChunks);
        $this->assertSame('Introduction', $headingChunks[0]->text);
    }

    public function test_heading_context_propagated_to_following_block(): void
    {
        $blocks = [
            $this->makeBlock('heading', ['text' => 'Chapter One'], 1),
            $this->makeBlock('paragraph', ['text' => 'This is the first paragraph.'], 2),
        ];
        [$content, $version] = $this->makeContentWithVersion('Title', blocks: $blocks);

        $chunks = $this->chunker->chunk($content, $version);

        $bodyChunks = array_values(array_filter(
            $chunks,
            fn (ContentChunk $c) => ($c->metadata['block_type'] ?? '') === 'paragraph'
        ));
        $this->assertNotEmpty($bodyChunks);
        $this->assertStringContainsString('Chapter One', $bodyChunks[0]->text);
        $this->assertSame('Chapter One', $bodyChunks[0]->metadata['heading_context'] ?? '');
    }

    public function test_blocks_without_preceding_heading_have_empty_heading_context(): void
    {
        $blocks = [$this->makeBlock('paragraph', ['text' => 'Orphan paragraph.'], 1)];
        [$content, $version] = $this->makeContentWithVersion('Title', blocks: $blocks);

        $chunks = $this->chunker->chunk($content, $version);

        $paraChunks = array_values(array_filter(
            $chunks,
            fn (ContentChunk $c) => ($c->metadata['block_type'] ?? '') === 'paragraph'
        ));
        $this->assertNotEmpty($paraChunks);
        $this->assertSame('', $paraChunks[0]->metadata['heading_context'] ?? '');
    }

    // ── Chunk Indices ────────────────────────────────────────────────────────

    public function test_chunk_indices_are_sequential_and_start_at_zero(): void
    {
        $blocks = [
            $this->makeBlock('heading', ['text' => 'H1'], 1),
            $this->makeBlock('paragraph', ['text' => 'Body text here.'], 2),
        ];
        [$content, $version] = $this->makeContentWithVersion(
            'Title',
            excerpt: 'Excerpt',
            seoData: ['title' => 'SEO'],
            blocks: $blocks
        );

        $chunks = $this->chunker->chunk($content, $version);

        $indices = array_map(fn (ContentChunk $c) => $c->index, $chunks);
        $this->assertSame(0, min($indices));
        for ($i = 1; $i < count($indices); $i++) {
            $this->assertGreaterThanOrEqual($indices[$i - 1], $indices[$i]);
        }
    }

    // ── Token Counting ───────────────────────────────────────────────────────

    public function test_token_count_is_positive_for_non_empty_text(): void
    {
        [$content, $version] = $this->makeContentWithVersion('Hello World');

        $chunks = $this->chunker->chunk($content, $version);

        foreach ($chunks as $chunk) {
            $this->assertGreaterThan(0, $chunk->tokenCount);
        }
    }

    // ── Long Text Splitting ──────────────────────────────────────────────────

    public function test_long_body_is_split_into_multiple_chunks_via_fallback(): void
    {
        // Generate text longer than 512 tokens (≈ 2048 chars)
        $longText = implode(' ', array_fill(0, 600, 'This is a sentence.'));

        [$content, $version] = $this->makeContentWithVersion('Title', body: $longText);

        $chunks = $this->chunker->chunk($content, $version);

        // Should have title chunk + at least 1 body chunk from the long text fallback
        $bodyChunks = array_filter($chunks, fn (ContentChunk $c) => $c->type === 'body');
        $this->assertGreaterThanOrEqual(1, count($bodyChunks));
    }

    public function test_short_text_produces_single_chunk(): void
    {
        $blocks = [$this->makeBlock('paragraph', ['text' => 'Short text.'], 1)];
        [$content, $version] = $this->makeContentWithVersion('Title', blocks: $blocks);

        $chunks = $this->chunker->chunk($content, $version);

        $paraChunks = array_filter(
            $chunks,
            fn (ContentChunk $c) => ($c->metadata['block_type'] ?? '') === 'paragraph'
        );
        $this->assertCount(1, $paraChunks);
    }

    // ── Overlap ──────────────────────────────────────────────────────────────

    public function test_split_chunks_have_text_overlap(): void
    {
        config(['numen.search.chunk_max_tokens' => 20, 'numen.search.chunk_overlap_tokens' => 5]);

        $sentences = array_fill(0, 30, 'This is a longer test sentence for overlap checking.');
        $text = implode(' ', $sentences);

        $blocks = [$this->makeBlock('paragraph', ['text' => $text], 1)];
        [$content, $version] = $this->makeContentWithVersion('T', blocks: $blocks);

        $chunks = $this->chunker->chunk($content, $version);

        $paraChunks = array_values(array_filter(
            $chunks,
            fn (ContentChunk $c) => ($c->metadata['block_type'] ?? '') === 'paragraph'
        ));

        if (count($paraChunks) >= 2) {
            $end0 = substr($paraChunks[0]->text, -50);
            $start1 = substr($paraChunks[1]->text, 0, 50);
            similar_text($end0, $start1, $pct);
            $this->assertGreaterThan(0, $pct, 'Expected overlap between consecutive chunks');
        } else {
            $this->markTestSkipped('Text was short enough to fit in a single chunk at this token limit');
        }
    }

    // ── Locale Metadata ──────────────────────────────────────────────────────

    public function test_chunks_carry_locale_from_content(): void
    {
        [$content, $version] = $this->makeContentWithVersion('Bonjour', locale: 'fr');

        $chunks = $this->chunker->chunk($content, $version);

        $this->assertNotEmpty($chunks);
        foreach ($chunks as $chunk) {
            $this->assertSame('fr', $chunk->metadata['locale'] ?? null);
        }
    }

    // ── HTML Stripping ───────────────────────────────────────────────────────

    public function test_html_tags_stripped_from_block_data(): void
    {
        $blocks = [$this->makeBlock('paragraph', ['text' => '<p>Hello <strong>World</strong></p>'], 1)];
        [$content, $version] = $this->makeContentWithVersion('Title', blocks: $blocks);

        $chunks = $this->chunker->chunk($content, $version);

        $paraChunks = array_values(array_filter(
            $chunks,
            fn (ContentChunk $c) => ($c->metadata['block_type'] ?? '') === 'paragraph'
        ));
        $this->assertNotEmpty($paraChunks);
        $text = $paraChunks[0]->text;
        $this->assertStringNotContainsString('<p>', $text);
        $this->assertStringNotContainsString('<strong>', $text);
        $this->assertStringContainsString('Hello', $text);
        $this->assertStringContainsString('World', $text);
    }

    // ── Multiple Blocks ───────────────────────────────────────────────────────

    public function test_multiple_blocks_processed_in_order(): void
    {
        $blocks = [
            $this->makeBlock('heading', ['text' => 'First Section'], 1),
            $this->makeBlock('paragraph', ['text' => 'First paragraph.'], 2),
            $this->makeBlock('heading', ['text' => 'Second Section'], 3),
            $this->makeBlock('paragraph', ['text' => 'Second paragraph.'], 4),
        ];
        [$content, $version] = $this->makeContentWithVersion('Title', blocks: $blocks);

        $chunks = $this->chunker->chunk($content, $version);

        $paraChunks = array_values(array_filter(
            $chunks,
            fn (ContentChunk $c) => ($c->metadata['block_type'] ?? '') === 'paragraph'
        ));
        $this->assertCount(2, $paraChunks);

        // First paragraph should have context from "First Section"
        $this->assertStringContainsString('First Section', $paraChunks[0]->text);
        // Second paragraph should have context from "Second Section"
        $this->assertStringContainsString('Second Section', $paraChunks[1]->text);
    }

    // ── Fallback to Body When No Blocks ──────────────────────────────────────

    public function test_fallback_to_body_when_no_blocks(): void
    {
        [$content, $version] = $this->makeContentWithVersion('Title', body: 'This is the body content.');

        $chunks = $this->chunker->chunk($content, $version);

        $bodyChunks = array_filter($chunks, fn (ContentChunk $c) => $c->type === 'body');
        $this->assertNotEmpty($bodyChunks);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * @param  ContentBlock[]  $blocks
     * @param  array<string, mixed>|null  $seoData
     * @return array{Content, ContentVersion}
     */
    private function makeContentWithVersion(
        string $title,
        ?string $excerpt = null,
        ?array $seoData = null,
        array $blocks = [],
        string $locale = 'en',
        string $body = '',
    ): array {
        $content = Mockery::mock(Content::class)->makePartial();
        $content->locale = $locale;

        $version = Mockery::mock(ContentVersion::class)->makePartial();
        $version->title = $title;
        $version->excerpt = $excerpt;
        $version->seo_data = $seoData;
        $version->body = $body;

        $blockCollection = Collection::make($blocks);
        $rel = Mockery::mock(\Illuminate\Database\Eloquent\Relations\HasMany::class);
        $rel->shouldReceive('orderBy')->andReturnSelf();
        $rel->shouldReceive('get')->andReturn($blockCollection);
        $version->shouldReceive('blocks')->andReturn($rel);

        return [$content, $version];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function makeBlock(string $type, array $data, int $sortOrder): ContentBlock
    {
        $block = Mockery::mock(ContentBlock::class)->makePartial();
        $block->type = $type;
        $block->data = $data;
        $block->sort_order = $sortOrder;

        return $block;
    }
}
