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
