/**
 * Resource modules barrel export.
 */

export { ContentResource } from './content.js'
export type { ContentItem, ContentListParams, ContentCreatePayload, ContentUpdatePayload } from './content.js'

export { PagesResource } from './pages.js'
export type { Page, PageListParams, PageCreatePayload, PageUpdatePayload, PageReorderPayload } from './pages.js'

export { MediaResource } from './media.js'
export type { MediaAsset, MediaListParams, MediaUpdatePayload } from './media.js'

export { SearchResource } from './search.js'
export type { SearchParams, SearchResult, SearchResponse, SuggestResponse, AskPayload, AskResponse } from './search.js'

export { VersionsResource } from './versions.js'
export type { ContentVersion, VersionListParams, VersionDiff } from './versions.js'

export { TaxonomiesResource } from './taxonomies.js'
export type {
  Taxonomy,
  TaxonomyTerm,
  TaxonomyCreatePayload,
  TaxonomyUpdatePayload,
  TermCreatePayload,
  TermUpdatePayload,
} from './taxonomies.js'

export { BriefsResource } from './briefs.js'
export type { Brief, BriefListParams, BriefCreatePayload } from './briefs.js'

export { PipelineResource } from './pipeline.js'
export type { PipelineRun, PipelineRunListParams } from './pipeline.js'

export { WebhooksResource } from './webhooks.js'
export type { Webhook, WebhookDelivery, WebhookListParams, WebhookCreatePayload, WebhookUpdatePayload } from './webhooks.js'

export { GraphResource } from './graph.js'
export type { GraphNode, GraphRelationship, GraphCluster } from './graph.js'

export { ChatResource } from './chat.js'
export type { Conversation, ChatMessage, SendMessagePayload, CreateConversationPayload } from './chat.js'

export { RepurposeResource } from './repurpose.js'
export type { FormatTemplate } from './repurpose.js'

export { TranslationsResource } from './translations.js'
export type { Translation } from './translations.js'

export { QualityResource } from './quality.js'
export type { QualityScore, QualityScoreListParams, QualityConfig } from './quality.js'

export { CompetitorResource } from './competitor.js'
export type { CompetitorSource, CompetitorAlert, Differentiation, CompetitorSourceCreatePayload, CompetitorSourceUpdatePayload, CompetitorSourceListParams } from './competitor.js'

export { AdminResource } from './admin.js'
export type { Role, AuditLog, RoleCreatePayload, RoleUpdatePayload } from './admin.js'
