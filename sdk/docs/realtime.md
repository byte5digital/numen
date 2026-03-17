# Realtime Guide

The SDK provides three realtime options for receiving live updates from the Numen API.

## RealtimeManager (Recommended)

The highest-level API. Manages SSE connections with automatic fallback to polling.

```ts
import { RealtimeManager } from '@numen/sdk'

const realtime = new RealtimeManager({
  baseUrl: 'https://api.numen.ai',
  token: 'your-token',
})

// Subscribe with pattern matching
const unsubscribe = realtime.subscribe('content.*', (event) => {
  console.log(event.type, event.channel, event.data)
})

// Connection state
realtime.onConnectionStateChange((state) => {
  console.log('Connection:', state) // 'connecting' | 'connected' | 'disconnected' | 'reconnecting'
})

// Clean up
unsubscribe()
realtime.disconnect()
```

### Channel Patterns

- `content.*` — all content events
- `content.created` — only content creation
- `pages.*` — all page events
- `*` — all events

## RealtimeClient (SSE)

Lower-level SSE client with auto-reconnect and exponential backoff.

```ts
import { RealtimeClient } from '@numen/sdk'

const sse = new RealtimeClient({
  baseUrl: 'https://api.numen.ai',
  token: 'your-token',
  maxReconnectAttempts: 10,
  reconnectDelay: 1000,
  maxReconnectDelay: 30000,
})

sse.on('content.updated', (event) => {
  console.log('Updated:', event.data)
})

sse.onConnectionStateChange((state) => {
  console.log('SSE state:', state)
})

sse.onError((error) => {
  console.error('SSE error:', error)
})

sse.connect()

// Later
sse.disconnect()
```

## PollingClient (Fallback)

HTTP polling for environments where SSE isn't available.

```ts
import { PollingClient } from '@numen/sdk'

const poller = new PollingClient({
  baseUrl: 'https://api.numen.ai',
  token: 'your-token',
  interval: 5000, // Poll every 5 seconds
})

poller.on('content.*', (event) => {
  console.log('Polled event:', event)
})

poller.start()

// Later
poller.stop()
```

## Framework Integration

### React

```tsx
import { useRealtime } from '@numen/sdk/react'

function LiveFeed() {
  const { events, connectionState } = useRealtime('content.*')
  // events is an array of RealtimeEvent
  // connectionState is the current connection state
}
```

### Vue

```vue
<script setup>
import { useRealtime } from '@numen/sdk/vue'

const { events, connectionState } = useRealtime('content.*')
</script>
```

### Svelte

```svelte
<script>
  import { createRealtimeStore } from '@numen/sdk/svelte'

  const live = createRealtimeStore('content.*')
</script>
```

## Event Shape

```ts
interface RealtimeEvent {
  type: string       // e.g. 'content.updated'
  channel: string    // e.g. 'content'
  data: unknown      // event payload
  timestamp: string  // ISO 8601
  id?: string        // optional event ID
}
```
