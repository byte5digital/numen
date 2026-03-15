<?php

namespace App\Listeners;

use App\Models\ContentGraphEdge;
use App\Models\ContentGraphNode;

/**
 * Synchronously removes a content node (and its cascading edges) from the
 * knowledge graph when content is unpublished or deleted.
 *
 * Intentionally synchronous: the operation is small and must be consistent.
 */
class RemoveFromKnowledgeGraphListener
{
    /**
     * Handle ContentUnpublished and ContentDeleted events.
     *
     * @param  object{content: \App\Models\Content}  $event
     */
    public function handle(object $event): void
    {
        $contentId = $event->content->id;

        // Remove edges first (source or target) to avoid FK violations
        $nodeIds = ContentGraphNode::where('content_id', $contentId)
            ->pluck('id');

        if ($nodeIds->isNotEmpty()) {
            ContentGraphEdge::whereIn('source_id', $nodeIds)
                ->orWhereIn('target_id', $nodeIds)
                ->delete();

            ContentGraphNode::where('content_id', $contentId)->delete();
        }
    }
}
