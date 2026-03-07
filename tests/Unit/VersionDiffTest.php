<?php

namespace Tests\Unit;

use App\Models\Content;
use App\Models\ContentType;
use App\Models\ContentVersion;
use App\Models\Space;
use App\Models\User;
use App\Services\Versioning\VersionDiff;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VersionDiffTest extends TestCase
{
    use RefreshDatabase;

    private ContentVersion $versionA;

    private ContentVersion $versionB;

    protected function setUp(): void
    {
        parent::setUp();

        $space = Space::create(['name' => 'Space', 'slug' => 'space']);
        $type = ContentType::create(['space_id' => $space->id, 'name' => 'Blog', 'slug' => 'blog', 'schema' => []]);
        $content = Content::create([
            'space_id' => $space->id,
            'content_type_id' => $type->id,
            'slug' => 'test',
            'status' => 'draft',
            'locale' => 'en',
        ]);
        $user = User::factory()->create();

        $this->versionA = $content->versions()->create([
            'version_number' => 1,
            'title' => 'Version A',
            'body' => 'Body A',
            'body_format' => 'markdown',
            'author_type' => 'human',
            'author_id' => $user->id,
            'status' => 'published',
        ]);

        $this->versionB = $content->versions()->create([
            'version_number' => 2,
            'title' => 'Version B',
            'body' => 'Body B',
            'body_format' => 'markdown',
            'author_type' => 'human',
            'author_id' => $user->id,
            'status' => 'draft',
        ]);
    }

    public function test_has_changes_returns_true_when_field_diffs_present(): void
    {
        $diff = new VersionDiff(
            $this->versionA,
            $this->versionB,
            fieldDiffs: ['title' => ['type' => 'changed', 'old' => 'A', 'new' => 'B']],
            blockDiffs: [],
            seoDiffs: [],
        );

        $this->assertTrue($diff->hasChanges());
    }

    public function test_has_changes_returns_true_when_block_diffs_present(): void
    {
        $diff = new VersionDiff(
            $this->versionA,
            $this->versionB,
            fieldDiffs: [],
            blockDiffs: [['type' => 'added', 'position' => 0]],
            seoDiffs: [],
        );

        $this->assertTrue($diff->hasChanges());
    }

    public function test_has_changes_returns_true_when_seo_diffs_present(): void
    {
        $diff = new VersionDiff(
            $this->versionA,
            $this->versionB,
            fieldDiffs: [],
            blockDiffs: [],
            seoDiffs: ['meta_description' => ['old' => 'A', 'new' => 'B']],
        );

        $this->assertTrue($diff->hasChanges());
    }

    public function test_has_changes_returns_false_when_all_empty(): void
    {
        $diff = new VersionDiff($this->versionA, $this->versionB, [], [], []);

        $this->assertFalse($diff->hasChanges());
    }

    public function test_summary_returns_no_changes_when_empty(): void
    {
        $diff = new VersionDiff($this->versionA, $this->versionB, [], [], []);

        $this->assertEquals('No changes', $diff->summary());
    }

    public function test_summary_includes_title_changed(): void
    {
        $diff = new VersionDiff(
            $this->versionA,
            $this->versionB,
            fieldDiffs: ['title' => ['type' => 'changed', 'old' => 'Old', 'new' => 'New']],
            blockDiffs: [],
            seoDiffs: [],
        );

        $this->assertStringContainsString('title changed', $diff->summary());
    }

    public function test_summary_includes_block_counts(): void
    {
        $diff = new VersionDiff(
            $this->versionA,
            $this->versionB,
            fieldDiffs: [],
            blockDiffs: [
                ['type' => 'added', 'position' => 0],
                ['type' => 'added', 'position' => 1],
                ['type' => 'removed', 'position' => 2],
            ],
            seoDiffs: [],
        );

        $summary = $diff->summary();
        $this->assertStringContainsString('2 blocks added', $summary);
        $this->assertStringContainsString('1 blocks removed', $summary);
    }

    public function test_summary_includes_seo_field_count(): void
    {
        $diff = new VersionDiff(
            $this->versionA,
            $this->versionB,
            fieldDiffs: [],
            blockDiffs: [],
            seoDiffs: [
                'meta_description' => ['old' => 'A', 'new' => 'B'],
                'og_title' => ['old' => null, 'new' => 'New OG'],
            ],
        );

        $this->assertStringContainsString('2 SEO fields changed', $diff->summary());
    }

    public function test_json_serialize_includes_expected_keys(): void
    {
        $diff = new VersionDiff($this->versionA, $this->versionB, [], [], []);

        $json = $diff->jsonSerialize();

        $this->assertArrayHasKey('version_a', $json);
        $this->assertArrayHasKey('version_b', $json);
        $this->assertArrayHasKey('has_changes', $json);
        $this->assertArrayHasKey('summary', $json);
        $this->assertArrayHasKey('fields', $json);
        $this->assertArrayHasKey('blocks', $json);
        $this->assertArrayHasKey('seo', $json);
    }

    public function test_json_serialize_includes_version_numbers(): void
    {
        $diff = new VersionDiff($this->versionA, $this->versionB, [], [], []);

        $json = $diff->jsonSerialize();

        $this->assertEquals(1, $json['version_a']['version_number']);
        $this->assertEquals(2, $json['version_b']['version_number']);
    }
}
