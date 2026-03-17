# Security Guide

This guide documents important security considerations when using @numen/sdk.

## Overview

The SDK has been audited for common vulnerabilities. This document outlines findings and best practices to keep your application secure.

## 1. SSE Token in URL

### Issue
When using SSE (Server-Sent Events) for realtime subscriptions, the SDK passes authentication tokens as URL query parameters:

```ts
const realtime = new RealtimeManager({
  baseUrl: 'https://api.numen.ai',
  token: 'your-secret-token', // ⚠️ Passed in URL
})
```

This can expose tokens in:
- Browser history
- Server access logs (if proxied)
- Browser DevTools Network tab
- Referrer headers

### Mitigation

#### Option 1: Use `Authorization` Header (Recommended)
Configure your API server to accept tokens in the `Authorization` header instead:

```ts
// Client-side: use apiKey (stored securely)
const realtime = new RealtimeManager({
  baseUrl: 'https://api.numen.ai',
  apiKey: 'your-api-key', // Alternative auth method
})

// Or rely on cookies for auth
const realtime = new RealtimeManager({
  baseUrl: 'https://api.numen.ai',
  // Token from httpOnly cookie (sent automatically)
})
```

#### Option 2: Use httpOnly Cookies
If your Numen API is served from the same domain:

```ts
// Cookie is sent automatically by the browser
const realtime = new RealtimeManager({
  baseUrl: 'https://api.numen.ai',
  // No token passed — uses httpOnly cookie instead
})
```

**Server-side (Numen config):**
```php
// In your Numen API, accept auth from cookies or headers
// instead of URL query parameters
```

#### Option 3: Token Refresh Strategy
Rotate SSE tokens frequently:

```ts
const realtime = new RealtimeManager({
  baseUrl: 'https://api.numen.ai',
  token: await getShortLivedToken(), // Fetch fresh token (5-15 min TTL)
})

// Refresh token periodically
setInterval(async () => {
  const freshToken = await getShortLivedToken()
  realtime.setToken(freshToken)
}, 10 * 60_000) // Every 10 minutes
```

**Why this works:**
- Even if a token is intercepted, it expires quickly
- Reduces exposure window
- Useful in hostile network environments (public WiFi)

#### Option 4: Use a Reverse Proxy
Add an authentication layer in front of the SSE endpoint:

```
Client → Your Proxy (with session cookie) → Numen API
```

The proxy injects the token server-side, keeping it out of URLs entirely.

## 2. Channel Name Encoding

### Issue
Channel names in realtime subscriptions may contain special characters that aren't properly URL-encoded:

```ts
const channelName = 'content.article:My&Article'
realtime.subscribe(channelName, (event) => {
  // URL: https://api.numen.ai/v1/realtime/content.article:My&Article
  // ⚠️ Unencoded '&' can break URL parsing
})
```

Special characters like `&`, `#`, `?`, `/`, and spaces need safe encoding.

### Solution

Always URL-encode channel names if they contain user input:

```ts
import { encodeURIComponent } from 'js'

const userId = 'user@example.com'
const channelName = `user.${encodeURIComponent(userId)}`

realtime.subscribe(channelName, (event) => {
  console.log(event)
})

// Safe channel names (no encoding needed):
realtime.subscribe('content.*', handler)
realtime.subscribe('pipeline.pending', handler)
realtime.subscribe('space.production', handler)

// Unsafe channel names (encode first):
const unsafe = `content.${userInput}` // ⚠️ If userInput has special chars
const safe = `content.${encodeURIComponent(userInput)}` // ✅
```

### Safe Channel Patterns

These patterns are safe without additional encoding:

- Alphanumerics: `a-z`, `A-Z`, `0-9`
- Underscores: `_`
- Dots: `.`
- Hyphens: `-`

**Avoid in channel names:**
- `&`, `#`, `?`, `/` — URL special chars
- Spaces — breaks URL structure
- Control characters — can cause injection
- Non-ASCII Unicode — may cause encoding issues

## 3. FormData Upload Security

### Issue
When uploading files with `.media.upload()`, the code manually sets the `Content-Type` header to `multipart/form-data`:

```ts
const formData = new FormData()
formData.append('file', file)

// ⚠️ BROKEN: Manually setting Content-Type breaks multipart encoding
headers: {
  'Content-Type': 'multipart/form-data',
}
```

This breaks the multipart boundary encoding, which can:
- Prevent file upload entirely
- Expose boundary markers in the upload
- Cause parsing errors on the server

### Solution

**Let the browser set the Content-Type header automatically:**

```ts
import { NumenClient } from '@numen/sdk'

const client = new NumenClient({
  baseUrl: 'https://api.numen.ai',
  apiKey: 'your-api-key',
})

// ✅ Correct: Browser automatically sets Content-Type with boundary
const asset = await client.media.upload(file, {
  alt: 'My image',
  title: 'Article hero',
  folder_id: 'folder-123',
})

console.log(`Uploaded: ${asset.data.url}`)
```

**If you must customize the request:**

```ts
// Option 1: Use SDK's built-in .upload() — it handles headers correctly
const asset = await client.media.upload(file, metadata)

// Option 2: Make a raw request without overriding Content-Type
const formData = new FormData()
formData.append('file', file)
formData.append('title', 'My Title')

// Do NOT set 'Content-Type' header — let fetch/axios set it
const response = await fetch(`${baseUrl}/v1/media`, {
  method: 'POST',
  body: formData,
  // ✅ No Content-Type header — browser fills in boundary automatically
})
```

## 4. Validation & Error Handling

### Always validate server responses:

```ts
try {
  const article = await client.content.get('id')
  
  // Type-check response
  if (!article.id || typeof article.title !== 'string') {
    throw new Error('Invalid response format')
  }
  
  // Use TypeScript for compile-time safety
  const safe: ContentItem = article // ✅ Type-checked
} catch (error) {
  if (error instanceof NumenValidationError) {
    console.log('Validation failed:', error.details)
  } else if (error instanceof NumenAuthError) {
    // Token expired or invalid — refresh and retry
    const newToken = await refreshToken()
    client.setToken(newToken)
  } else if (error instanceof NumenError) {
    console.log('API error:', error.message)
  }
}
```

## 5. API Key Storage

### ✅ DO:
- Store API keys in environment variables (`.env`)
- Use secrets management (HashiCorp Vault, AWS Secrets Manager, etc.)
- Rotate keys periodically
- Use short-lived tokens from a backend service

### ❌ DON'T:
- Hardcode API keys in client-side code
- Commit keys to version control
- Expose keys in frontend bundles
- Store keys in localStorage or sessionStorage (for SPA)

### Secure Pattern (Backend Gateway):

```ts
// Frontend (browser)
const client = new NumenClient({
  baseUrl: 'https://yourapp.com/api/proxy', // Your backend
  // No API key in browser
})

// Backend (Node.js/Python/PHP/etc)
app.post('/api/proxy/v1/content', async (req, res) => {
  // Verify user session
  const userId = req.session.userId
  if (!userId) return res.status(401).send('Unauthorized')
  
  // Use server-side API key
  const response = await fetch('https://api.numen.ai/v1/content', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${process.env.NUMEN_API_KEY}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(req.body),
  })
  
  return res.json(await response.json())
})
```

This keeps your API key server-side and secure.

## 6. CORS & CSP Headers

If serving the SDK from a different domain than Numen API:

```ts
// Ensure CORS is enabled on Numen API
// Access-Control-Allow-Origin: *
// Access-Control-Allow-Methods: GET, POST, PATCH, DELETE
// Access-Control-Allow-Headers: Authorization, Content-Type
```

For Content Security Policy (CSP):

```html
<meta http-equiv="Content-Security-Policy" 
      content="
        connect-src 'self' https://api.numen.ai;
        script-src 'self';
      " />
```

## 7. Dependency Security

Keep dependencies up-to-date:

```bash
npm audit
npm update
pnpm audit
pnpm update
```

Monitor security advisories:
- GitHub: Watch the numen/sdk repository
- npm: Subscribe to package security alerts

## 8. Rate Limiting & Quotas

The SDK includes a rate limit error class:

```ts
try {
  await client.search.search({ query: 'test' })
} catch (error) {
  if (error instanceof NumenRateLimitError) {
    console.log(`Rate limited. Retry after ${error.retryAfter}s`)
    
    // Exponential backoff
    await new Promise(r => setTimeout(r, error.retryAfter * 1000))
    await client.search.search({ query: 'test' })
  }
}
```

## Summary

| Finding | Severity | Mitigation | Status |
|---------|----------|-----------|--------|
| SSE token in URL | MEDIUM | Use httpOnly cookies or short-lived tokens | Documented |
| Channel name encoding | MEDIUM | URL-encode channel names with special chars | Documented |
| FormData Content-Type | MEDIUM | Let browser set header automatically | Documented |
| API key in frontend | HIGH | Use backend gateway pattern | Design pattern provided |
| Stale dependencies | MEDIUM | Run `npm audit` regularly | Developer responsibility |

---

## Questions?

For security issues, please contact [security@numen.ai](mailto:security@numen.ai) responsibly.
