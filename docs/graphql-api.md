# GraphQL API — Numen

> **Endpoint:** `POST /graphql`
> **Explorer (dev):** `GET /graphiql`

Numen ships a full GraphQL API powered by [Lighthouse PHP](https://lighthouse-php.com/). It covers all content, space, media, pipeline, and subscription operations.

## Quick Start

### 1. Get an API Token

```bash
POST /v1/auth/login
{ "email": "...", "password": "..." }
# Response: { "token": "1|abc..." }
```

### 2. Set the Authorization Header

```
Authorization: Bearer <token>
Content-Type: application/json
```

### 3. Send Your First Query

```bash
curl -X POST https://your-numen.app/graphql \
  -H "Authorization: Bearer 1|abc..." \
  -H "Content-Type: application/json" \
  -d '{"query": "{ me { id name email } }"}'
```

## Authentication

Numen uses **Laravel Sanctum** tokens. Pass your token in the `Authorization` header on every request:

```
Authorization: Bearer 1|your-token-here
```

Tokens created via the REST `/v1/auth/login` endpoint work here too.

## Example Queries

### Current User

```graphql
query Me {
  me {
    id
    name
    email
    roles {
      name
      spaceId
    }
  }
}
```

### Content by Slug

```graphql
query ContentBySlug($slug: String!, $spaceId: ID!) {
  contentBySlug(slug: $slug, spaceId: $spaceId) {
    id
    title
    slug
    body
    status
    seoTitle
    seoDescription
    publishedAt
    space {
      id
      name
    }
    tags {
      name
    }
    mediaAssets {
      id
      url
      altText
    }
  }
}
```

### Content List with Cursor Pagination

```graphql
query Contents($spaceId: ID!, $after: String, $first: Int) {
  contents(
    spaceId: $spaceId
    first: $first
    after: $after
    orderBy: [{ column: PUBLISHED_AT, order: DESC }]
  ) {
    edges {
      cursor
      node {
        id
        title
        slug
        status
        publishedAt
      }
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}
```

### Spaces

```graphql
query Spaces {
  spaces {
    id
    name
    slug
    description
    contentsCount
  }
}
```

## Example Mutations

### Create a Brief (triggers pipeline)

```graphql
mutation CreateBrief($input: CreateBriefInput!) {
  createBrief(input: $input) {
    id
    topic
    status
    content {
      id
      title
      status
    }
  }
}
```

Variables:
```json
{
  "input": {
    "spaceId": "01HX...",
    "topic": "How to use GraphQL in Laravel",
    "targetAudience": "PHP developers",
    "tone": "professional",
    "wordCount": 1200,
    "keywords": ["graphql", "laravel"],
    "autoPublish": false
  }
}
```

### Create Content Directly

```graphql
mutation CreateContent($input: CreateContentInput!) {
  createContent(input: $input) {
    id
    title
    slug
    status
    createdAt
  }
}
```

### Publish Content

```graphql
mutation PublishContent($id: ID!) {
  publishContent(id: $id) {
    id
    status
    publishedAt
  }
}
```

### Trigger Pipeline Run

```graphql
mutation TriggerPipeline($contentId: ID!, $pipelineId: ID) {
  triggerPipeline(contentId: $contentId, pipelineId: $pipelineId) {
    id
    status
    startedAt
    stages {
      name
      status
    }
  }
}
```

## Subscriptions

Numen supports real-time updates via GraphQL subscriptions (WebSocket).

### Content Updated

```graphql
subscription OnContentUpdated($spaceId: ID!) {
  contentUpdated(spaceId: $spaceId) {
    id
    title
    status
    updatedAt
  }
}
```

### Pipeline Stage Completed

```graphql
subscription OnPipelineProgress($contentId: ID!) {
  pipelineStageCompleted(contentId: $contentId) {
    pipelineRunId
    stageName
    status
    output
    completedAt
  }
}
```

### JavaScript Setup

```javascript
import { createClient } from 'graphql-ws';

const client = createClient({
  url: 'wss://your-numen.app/graphql',
  connectionParams: {
    Authorization: `Bearer ${token}`,
  },
});

client.subscribe(
  { query: `subscription { contentPublished(spaceId: "01HX...") { id title slug publishedAt } }` },
  { next: (data) => console.log('Published:', data), error: console.error }
);
```

## Cursor Pagination

All list fields use cursor-based pagination (Relay-spec):

```graphql
query {
  contents(spaceId: "01HX...", first: 20, after: "eyJpZCI6MTAwfQ") {
    edges {
      cursor
      node { id title }
    }
    pageInfo {
      hasNextPage
      endCursor
    }
  }
}
```

Pass `endCursor` as `after` in the next request to get the next page.

## Complexity and Depth Limits

| Limit | Default | Env var |
|-------|---------|---------|
| Max complexity score | 500 | `GRAPHQL_MAX_COMPLEXITY` |
| Max query depth | 10 | `GRAPHQL_MAX_DEPTH` |

Queries exceeding these limits receive a `422` response.

## Caching

Frequently-read queries (published content by slug) are cached via the `@cache` directive. Default TTL is 300 seconds. Cache is automatically invalidated on content update/publish.

## Persisted Queries (APQ)

Numen supports Automatic Persisted Queries to reduce bandwidth. Compatible with Apollo Client out of the box.

## GraphiQL Explorer

In local development, an interactive GraphQL IDE is available at:

```
http://localhost:8000/graphiql
```

Set your auth token via the **Headers** tab:
```json
{ "Authorization": "Bearer 1|your-token" }
```

## Error Handling

```json
{
  "errors": [{
    "message": "Unauthenticated.",
    "extensions": { "category": "authentication" }
  }]
}
```

| Category | HTTP Status | Meaning |
|----------|-------------|---------|
| `authentication` | 401 | Missing or invalid token |
| `authorization` | 403 | Insufficient permissions |
| `validation` | 422 | Invalid input |
| `not_found` | 404 | Resource not found |

## Apollo Client Integration

```javascript
import { ApolloClient, InMemoryCache, createHttpLink } from '@apollo/client';
import { setContext } from '@apollo/client/link/context';

const authLink = setContext((_, { headers }) => ({
  headers: { ...headers, authorization: `Bearer ${localStorage.getItem('numen_token')}` },
}));

const client = new ApolloClient({
  link: authLink.concat(createHttpLink({ uri: 'https://your-numen.app/graphql' })),
  cache: new InMemoryCache(),
});
```

*Last updated: 2026-03-15 — GraphQL API Layer v0.9.0*
