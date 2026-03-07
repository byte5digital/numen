<?php

namespace App\Services\Versioning;

use App\Events\Content\ContentPublished;
use App\Jobs\PublishScheduledContent;
use App\Models\Content;
use App\Models\ContentDraft;
use App\Models\ContentVersion;
use App\Models\ScheduledPublish;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class VersioningService
{
    public function __construct(private DiffEngine $diffEngine) {}

    /**
     * Create a new draft version for editing.
     * If content has a live version, branch from it.
     */
    public function createDraft(Content $content, ?ContentVersion $branchFrom = null): ContentVersion
    {
        $base = $branchFrom ?? $content->currentVersion;
        $nextNumber = $content->versions()->max('version_number') + 1;

        /** @var string|null $userId */
        $userId = Auth::id();

        $draft = $content->versions()->create([
            'version_number' => $nextNumber,
            'title' => $base !== null ? $base->title : '',
            'excerpt' => $base?->excerpt,
            'body' => $base !== null ? $base->body : '',
            'body_format' => $base !== null ? $base->body_format : 'markdown',
            'structured_fields' => $base?->structured_fields,
            'seo_data' => $base?->seo_data,
            'author_type' => 'human',
            'author_id' => $userId ?? 'system',
            'status' => 'draft',
            'parent_version_id' => $base?->id,
            'change_reason' => $branchFrom ? "Branched from v{$base->version_number}" : null,
        ]);

        // Clone blocks from base version
        if ($base) {
            $base->load('blocks');
            $base->blocks->each(fn ($block) => $draft->blocks()->create(
                $block->only(['type', 'sort_order', 'data', 'wysiwyg_override'])
            ));
        }

        $content->update(['draft_version_id' => $draft->id]);

        return $draft;
    }

    /**
     * Auto-save draft content (debounced, no version creation).
     *
     * @param  array<string, mixed>  $data
     */
    public function autoSave(Content $content, User $user, array $data): ContentDraft
    {
        $draft = ContentDraft::updateOrCreate(
            ['content_id' => $content->id, 'user_id' => $user->id],
            array_merge($data, ['last_saved_at' => now()])
        );

        // Atomically increment save_count after upsert to avoid casting issues with DB::raw
        $draft->increment('save_count');

        return $draft->fresh() ?? $draft;
    }

    /**
     * Promote a draft to a named version (save point).
     */
    public function saveVersion(
        ContentVersion $draft,
        string $label,
        ?string $changeReason = null,
    ): ContentVersion {
        $draft->update([
            'label' => $label,
            'change_reason' => $changeReason ?? $draft->change_reason,
            'content_hash' => $draft->computeHash(),
        ]);

        // Clear auto-save buffer for this content
        ContentDraft::where('content_id', $draft->content_id)->delete();

        return $draft->fresh() ?? $draft;
    }

    /**
     * Publish a specific version (makes it live).
     */
    public function publish(Content $content, ContentVersion $version): void
    {
        // Archive previously published version
        $content->versions()
            ->where('status', 'published')
            ->update(['status' => 'archived']);

        $version->update(['status' => 'published']);

        $content->update([
            'current_version_id' => $version->id,
            'status' => 'published',
            'published_at' => now(),
        ]);

        // If the published version was the draft, clear draft pointer
        if ($content->draft_version_id === $version->id) {
            $content->update(['draft_version_id' => null]);
        }

        event(new ContentPublished($content));
    }

    /**
     * Schedule a version for future publishing.
     */
    public function schedule(
        Content $content,
        ContentVersion $version,
        Carbon $publishAt,
        ?string $notes = null,
    ): ScheduledPublish {
        // Cancel any existing pending schedules for this content
        $content->scheduledPublishes()->where('status', 'pending')->update(['status' => 'cancelled']);

        $version->update([
            'status' => 'scheduled',
            'scheduled_at' => $publishAt,
        ]);

        $content->update([
            'status' => 'scheduled',
            'scheduled_publish_at' => $publishAt,
        ]);

        /** @var string|null $userId */
        $userId = Auth::id();

        $schedule = ScheduledPublish::create([
            'content_id' => $content->id,
            'version_id' => $version->id,
            'scheduled_by' => $userId ?? 'system',
            'publish_at' => $publishAt,
            'status' => 'pending',
            'notes' => $notes,
        ]);

        // Dispatch delayed job
        PublishScheduledContent::dispatch($schedule->id)->delay($publishAt);

        return $schedule;
    }

    /**
     * Rollback: create a new version from a historical one and publish it.
     */
    public function rollback(Content $content, ContentVersion $targetVersion): ContentVersion
    {
        /** @var string|null $userId */
        $userId = Auth::id();

        $nextNumber = $content->versions()->max('version_number') + 1;

        $label = $targetVersion->label ? " ({$targetVersion->label})" : '';

        $newVersion = $content->versions()->create([
            'version_number' => $nextNumber,
            'title' => $targetVersion->title,
            'excerpt' => $targetVersion->excerpt,
            'body' => $targetVersion->body,
            'body_format' => $targetVersion->body_format,
            'structured_fields' => $targetVersion->structured_fields,
            'seo_data' => $targetVersion->seo_data,
            'author_type' => 'human',
            'author_id' => $userId ?? 'system',
            'status' => 'draft',
            'parent_version_id' => $targetVersion->id,
            'change_reason' => "Rollback to v{$targetVersion->version_number}{$label}",
        ]);

        // Clone blocks
        $targetVersion->load('blocks');
        $targetVersion->blocks->each(fn ($block) => $newVersion->blocks()->create(
            $block->only(['type', 'sort_order', 'data', 'wysiwyg_override'])
        ));

        $newVersion->update(['content_hash' => $newVersion->computeHash()]);

        // Auto-publish the rollback
        $this->publish($content, $newVersion);

        return $newVersion;
    }

    /**
     * Compare two versions and return structured diff.
     */
    public function diff(ContentVersion $versionA, ContentVersion $versionB): VersionDiff
    {
        return $this->diffEngine->compare($versionA, $versionB);
    }

    /**
     * Create a branch: new draft from a non-current version.
     * Enables "work on next version while current is live".
     */
    public function branch(Content $content, ContentVersion $fromVersion, ?string $label = null): ContentVersion
    {
        $draft = $this->createDraft($content, $fromVersion);

        if ($label) {
            $draft->update(['label' => $label]);
        }

        return $draft;
    }
}
