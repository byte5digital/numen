# Changelog

All notable changes to `@numen/sdk` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] — 2026-03-17

### Added

- **Core Client** (`NumenClient`) — typed HTTP client with auth middleware and SWR caching
- **16 Resource Modules**
  - `ContentResource` — CRUD for content items with status filtering
  - `PagesResource` — page management with reordering and tree queries
  - `MediaResource` — asset management with upload support
  - `SearchResource` — full-text search, suggestions, and AI-powered ask
  - `VersionsResource` — content version history and diff
  - `TaxonomiesResource` — taxonomy and term management
  - `BriefsResource` — content brief generation and management
  - `PipelineResource` — AI pipeline run management
  - `WebhooksResource` — webhook endpoint CRUD and delivery logs
  - `GraphResource` — knowledge graph queries
  - `ChatResource` — conversational AI interface
  - `AdminResource` — admin operations (settings, users, roles)
  - `CompetitorResource` — competitor analysis
  - `QualityResource` — content quality scoring
  - `RepurposeResource` — content repurposing workflows
  - `TranslationsResource` — translation management
- **React Bindings** (`@numen/sdk/react`)
  - `NumenProvider` context provider
  - Hooks: `useContent`, `useContentList`, `useSearch`, `useMedia`, `usePipeline`, `useRealtime`, `usePage`, `usePageList`
  - Built on `useNumenQuery` with SWR caching
- **Vue 3 Bindings** (`@numen/sdk/vue`)
  - `NumenPlugin` for `app.use()` installation
  - Composables: `useContent`, `useContentList`, `useSearch`, `useMedia`, `usePipeline`, `useRealtime`, `usePage`, `usePageList`
  - Reactive refs with automatic cleanup
- **Svelte Bindings** (`@numen/sdk/svelte`)
  - `setNumenClient` / `getNumenClient` context
  - Store factories: `createContentStore`, `createContentListStore`, `createSearchStore`, `createMediaStore`, `createPipelineStore`, `createPageStore`, `createPageListStore`, `createRealtimeStore`
- **Realtime**
  - `RealtimeClient` — SSE connection with auto-reconnect and exponential backoff
  - `PollingClient` — HTTP polling fallback
  - `RealtimeManager` — unified interface with pattern-based channel subscriptions
- **Error Handling** — typed error classes: `NumenError`, `NumenRateLimitError`, `NumenValidationError`, `NumenAuthError`, `NumenNotFoundError`, `NumenNetworkError`
- **SWR Cache** — stale-while-revalidate caching with TTL and listeners
- **Auth Middleware** — pluggable authentication (API key, Bearer token)
- **TypeScript** — full type coverage with exported types for all resources
- **355 Tests** — comprehensive test suite covering core, resources, framework bindings, and realtime

[0.1.0]: https://github.com/byte5digital/numen/releases/tag/sdk-v0.1.0
