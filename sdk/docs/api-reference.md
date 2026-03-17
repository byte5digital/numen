# API Reference

## NumenClient

The core client. All resource modules are available as properties.

```ts
import { NumenClient } from '@numen/sdk'

const client = new NumenClient(options: NumenClientOptions)
```

### NumenClientOptions

| Property | Type | Required | Description |
|----------|------|----------|-------------|
| `baseUrl` | `string` | ✅ | API base URL |
| `apiKey` | `string` | — | API key for authentication |
| `token` | `string` | — | Bearer token for authentication |
| `cache` | `CacheOptions` | — | SWR cache config |
| `timeout` | `number` | — | Request timeout (ms) |

---

## Resources

### client.content

| Method | Signature | Description |
|--------|-----------|-------------|
| `list` | `(params?: ContentListParams) → Promise<PaginatedResponse<ContentItem>>` | List content items |
| `get` | `(id: string) → Promise<ContentItem>` | Get content by ID |
| `create` | `(payload: ContentCreatePayload) → Promise<ContentItem>` | Create content |
| `update` | `(id: string, payload: ContentUpdatePayload) → Promise<ContentItem>` | Update content |
| `delete` | `(id: string) → Promise<void>` | Delete content |

### client.pages

| Method | Signature | Description |
|--------|-----------|-------------|
| `list` | `(params?: PageListParams) → Promise<PaginatedResponse<Page>>` | List pages |
| `get` | `(id: string) → Promise<Page>` | Get page by ID |
| `create` | `(payload: PageCreatePayload) → Promise<Page>` | Create page |
| `update` | `(id: string, payload: PageUpdatePayload) → Promise<Page>` | Update page |
| `delete` | `(id: string) → Promise<void>` | Delete page |
| `reorder` | `(payload: PageReorderPayload) → Promise<void>` | Reorder pages |

### client.media

| Method | Signature | Description |
|--------|-----------|-------------|
| `list` | `(params?: MediaListParams) → Promise<PaginatedResponse<MediaAsset>>` | List media |
| `get` | `(id: string) → Promise<MediaAsset>` | Get media by ID |
| `upload` | `(file: File, meta?: MediaUpdatePayload) → Promise<MediaAsset>` | Upload media |
| `update` | `(id: string, payload: MediaUpdatePayload) → Promise<MediaAsset>` | Update metadata |
| `delete` | `(id: string) → Promise<void>` | Delete media |

### client.search

| Method | Signature | Description |
|--------|-----------|-------------|
| `search` | `(params: SearchParams) → Promise<SearchResponse>` | Full-text search |
| `suggest` | `(query: string) → Promise<SuggestResponse>` | Autocomplete suggestions |
| `ask` | `(payload: AskPayload) → Promise<AskResponse>` | AI-powered Q&A |

### client.versions

| Method | Signature | Description |
|--------|-----------|-------------|
| `list` | `(contentId: string, params?: VersionListParams) → Promise<PaginatedResponse<ContentVersion>>` | List versions |
| `get` | `(contentId: string, versionId: string) → Promise<ContentVersion>` | Get version |
| `diff` | `(contentId: string, fromId: string, toId: string) → Promise<VersionDiff>` | Compare versions |
| `restore` | `(contentId: string, versionId: string) → Promise<ContentItem>` | Restore version |

### client.taxonomies

| Method | Signature | Description |
|--------|-----------|-------------|
| `list` | `() → Promise<Taxonomy[]>` | List taxonomies |
| `get` | `(id: string) → Promise<Taxonomy>` | Get taxonomy |
| `create` | `(payload: TaxonomyCreatePayload) → Promise<Taxonomy>` | Create taxonomy |
| `update` | `(id: string, payload: TaxonomyUpdatePayload) → Promise<Taxonomy>` | Update taxonomy |
| `delete` | `(id: string) → Promise<void>` | Delete taxonomy |
| `listTerms` | `(taxonomyId: string) → Promise<TaxonomyTerm[]>` | List terms |
| `createTerm` | `(taxonomyId: string, payload: TermCreatePayload) → Promise<TaxonomyTerm>` | Create term |
| `updateTerm` | `(taxonomyId: string, termId: string, payload: TermUpdatePayload) → Promise<TaxonomyTerm>` | Update term |
| `deleteTerm` | `(taxonomyId: string, termId: string) → Promise<void>` | Delete term |

### client.briefs

Brief generation and management for content planning.

### client.pipeline

AI content pipeline management — trigger runs, check status, retrieve results.

### client.webhooks

Webhook endpoint CRUD and delivery log inspection.

### client.graph

Knowledge graph queries and traversal.

### client.chat

Conversational AI interface for content-related queries.

### client.admin

Administrative operations: settings, users, roles.

### client.competitor

Competitor analysis resources.

### client.quality

Content quality scoring and auditing.

### client.repurpose

Content repurposing workflow management.

### client.translations

Translation management for multilingual content.

---

## Error Classes

| Class | Status Code | Description |
|-------|-------------|-------------|
| `NumenError` | Any | Base error class |
| `NumenAuthError` | 401 | Authentication failure |
| `NumenNotFoundError` | 404 | Resource not found |
| `NumenValidationError` | 422 | Validation errors |
| `NumenRateLimitError` | 429 | Rate limit exceeded (includes `retryAfter`) |
| `NumenNetworkError` | — | Network/connection failure |

---

## Utilities

### createNumenClient

Factory function (returns `NumenClient` with backward-compat properties):

```ts
import { createNumenClient } from '@numen/sdk'
const client = createNumenClient({ baseUrl: '...', apiKey: '...' })
```

### createAuthMiddleware

```ts
import { createAuthMiddleware } from '@numen/sdk'
const middleware = createAuthMiddleware({ apiKey: 'key' })
```

### SWRCache

```ts
import { SWRCache } from '@numen/sdk'
const cache = new SWRCache({ ttl: 60_000, maxEntries: 100 })
```

### SDK_VERSION

```ts
import { SDK_VERSION } from '@numen/sdk'
// '0.1.0'
```
