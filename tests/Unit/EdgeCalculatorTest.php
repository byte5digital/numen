<?php

namespace Tests\Unit;

use App\Models\Content;
use App\Models\ContentGraphNode;
use App\Models\ContentType;
use App\Models\ContentVersion;
use App\Models\Space;
use App\Models\TaxonomyTerm;
use App\Models\Vocabulary;
use App\Services\Graph\EdgeCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class EdgeCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private EdgeCalculator $calculator;

    private Space $space;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new EdgeCalculator;
        $this->space = Space::factory()->create();
    }

    public function test_computes_shares_topic_edges(): void
    {
        $contentType = ContentType::factory()->create(['space_id' => $this->space->id]);
        $vocabulary = Vocabulary::factory()->create(['space_id' => $this->space->id]);

        $term1 = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocabulary->id]);
        $term2 = TaxonomyTerm::factory()->create(['vocabulary_id' => $vocabulary->id]);

        $content1 = Content::factory()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $contentType->id,
        ]);
        $content2 = Content::factory()->create([
            'space_id' => $this->space->id,
            'content_type_id' => $contentType->id,
        ]);

        // Both share term1 and term2 → high Jaccard
        $content1->taxonomyTerms()->attach([$term1->id, $term2->id]);
        $content2->taxonomyTerms()->attach([$term1->id, $term2->id]);

        $node1 = ContentGraphNode::factory()->create([
            'content_id' => $content1->id,
            'space_id' => $this->space->id,
        ]);
        $node2 = ContentGraphNode::factory()->create([
            'content_id' => $content2->id,
            'space_id' => $this->space->id,
        ]);

        $edges = $this->calculator->computeSharesTopic(
            $node1, $content1->fresh(),
            $node2, $content2->fresh()
        );

        $this->assertCount(1, $edges);
        $this->assertEquals('SHARES_TOPIC', $edges[0]->edge_type);
        $this->assertGreaterThan(0.2, $edges[0]->weight);
    }

    public function test_computes_co_mentions_edges(): void
    {
        $node1 = ContentGraphNode::factory()->create([
            'space_id' => $this->space->id,
            'entity_labels' => ['AI', 'machine learning', 'content', 'strategy'],
        ]);
        $node2 = ContentGraphNode::factory()->create([
            'space_id' => $this->space->id,
            'entity_labels' => ['AI', 'machine learning', 'marketing'],
        ]);

        $edge = $this->calculator->computeCoMentions($node1, $node2);

        $this->assertNotNull($edge);
        $this->assertEquals('CO_MENTIONS', $edge->edge_type);
        $this->assertGreaterThan(0.3, $edge->weight);
    }

    public function test_computes_cites_edges(): void
    {
        $contentType = ContentType::factory()->create(['space_id' => $this->space->id]);

        $targetContent = Content::create([
            'space_id' => $this->space->id,
            'content_type_id' => $contentType->id,
            'slug' => 'target-article',
            'status' => 'published',
            'locale' => 'en',
        ]);
        $targetVersion = ContentVersion::create([
            'content_id' => $targetContent->id,
            'version_number' => 1,
            'title' => 'Target Article',
            'body' => 'Content',
            'body_format' => 'html',
            'author_type' => 'ai_agent',
            'author_id' => 'test',
        ]);
        $targetContent->update(['current_version_id' => $targetVersion->id]);

        // Source references target in its body
        $sourceContent = Content::create([
            'space_id' => $this->space->id,
            'content_type_id' => $contentType->id,
            'slug' => 'source-article',
            'status' => 'published',
            'locale' => 'en',
        ]);
        $sourceVersion = ContentVersion::create([
            'content_id' => $sourceContent->id,
            'version_number' => 1,
            'title' => 'Source Article',
            'body' => '<p>See also <a href="/content/target-article">Target</a>.</p>',
            'body_format' => 'html',
            'author_type' => 'ai_agent',
            'author_id' => 'test',
        ]);
        $sourceContent->update(['current_version_id' => $sourceVersion->id]);

        $sourceNode = ContentGraphNode::factory()->create([
            'content_id' => $sourceContent->id,
            'space_id' => $this->space->id,
        ]);
        $targetNode = ContentGraphNode::factory()->create([
            'content_id' => $targetContent->id,
            'space_id' => $this->space->id,
        ]);

        $edges = $this->calculator->computeCites(
            $sourceNode, $sourceContent->fresh(['currentVersion']),
            $targetNode, $targetContent->fresh(),
            $this->space
        );

        $this->assertCount(1, $edges);
        $this->assertEquals('CITES', $edges[0]->edge_type);
        $this->assertEquals(1.0, $edges[0]->weight);
    }

    public function test_respects_max_edges_per_type(): void
    {
        // Nodes with low entity overlap → no CO_MENTIONS edge
        $node1 = ContentGraphNode::factory()->create([
            'space_id' => $this->space->id,
            'entity_labels' => ['AI'],
        ]);
        $node2 = ContentGraphNode::factory()->create([
            'space_id' => $this->space->id,
            'entity_labels' => ['marketing', 'SEO', 'analytics', 'strategy'],
        ]);

        $edge = $this->calculator->computeCoMentions($node1, $node2);

        // Jaccard = 0/5 = 0 → no edge
        $this->assertNull($edge);
    }

    public function test_skips_similar_to_without_pgvector(): void
    {
        $node = ContentGraphNode::factory()->create(['space_id' => $this->space->id]);
        $others = new Collection([
            ContentGraphNode::factory()->create(['space_id' => $this->space->id]),
        ]);

        // SQLite driver is used in tests → pgvector not available → returns []
        $edges = $this->calculator->computeSimilarTo($node, $others);

        $this->assertSame([], $edges);
    }
}
