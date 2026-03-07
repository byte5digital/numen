<template>
  <div class="p-6">
    <div class="mb-6 flex items-center justify-between">
      <h1 class="text-2xl font-bold text-gray-900">Search Analytics</h1>
      <div class="flex gap-2">
        <button
          v-for="p in ['7d', '30d', '90d']"
          :key="p"
          class="rounded-lg border px-3 py-1.5 text-sm"
          :class="period === p ? 'border-indigo-600 bg-indigo-600 text-white' : 'border-gray-300 text-gray-700 hover:bg-gray-50'"
          @click="setPeriod(p)"
        >{{ p }}</button>
      </div>
    </div>

    <!-- Metric cards -->
    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
      <div class="rounded-xl bg-white p-4 shadow-sm">
        <p class="text-xs text-gray-500">Total Searches</p>
        <p class="mt-1 text-2xl font-bold">{{ analytics.total_searches ?? 0 }}</p>
      </div>
      <div class="rounded-xl bg-white p-4 shadow-sm">
        <p class="text-xs text-gray-500">Click-Through Rate</p>
        <p class="mt-1 text-2xl font-bold">{{ analytics.click_through_rate ?? 0 }}%</p>
      </div>
      <div class="rounded-xl bg-white p-4 shadow-sm">
        <p class="text-xs text-gray-500">Avg Response Time</p>
        <p class="mt-1 text-2xl font-bold">{{ analytics.avg_response_time_ms ?? 0 }}ms</p>
      </div>
      <div class="rounded-xl bg-white p-4 shadow-sm">
        <p class="text-xs text-gray-500">Zero-Result Queries</p>
        <p class="mt-1 text-2xl font-bold text-red-600">{{ analytics.zero_result_queries?.length ?? 0 }}</p>
      </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
      <!-- Top Queries -->
      <div class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-gray-700">Top Queries</h2>
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b text-xs text-gray-500">
              <th class="pb-2 text-left">Query</th>
              <th class="pb-2 text-right">Searches</th>
              <th class="pb-2 text-right">Avg Results</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="q in analytics.top_queries" :key="q.query_normalized" class="border-b last:border-0">
              <td class="py-2 font-medium">{{ q.query_normalized }}</td>
              <td class="py-2 text-right text-gray-600">{{ q.count }}</td>
              <td class="py-2 text-right text-gray-600">{{ Math.round(q.avg_results) }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Zero Result Queries (content gaps) -->
      <div class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-gray-700">
          Content Gaps
          <span class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700">No results</span>
        </h2>
        <table class="w-full text-sm">
          <thead>
            <tr class="border-b text-xs text-gray-500">
              <th class="pb-2 text-left">Query</th>
              <th class="pb-2 text-right">Searches</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="q in analytics.zero_result_queries" :key="q.query_normalized" class="border-b last:border-0">
              <td class="py-2 font-medium text-red-600">{{ q.query_normalized }}</td>
              <td class="py-2 text-right text-gray-600">{{ q.count }}</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Tier Usage -->
      <div class="rounded-xl bg-white p-4 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold text-gray-700">Tier Usage</h2>
        <div class="space-y-2">
          <div v-for="(count, tier) in analytics.tier_usage" :key="tier" class="flex items-center gap-3">
            <span class="w-20 text-xs text-gray-500 capitalize">{{ tier }}</span>
            <div class="flex-1 rounded-full bg-gray-100">
              <div
                class="h-2 rounded-full bg-indigo-500"
                :style="{ width: tierPercent(count) + '%' }"
              />
            </div>
            <span class="w-12 text-right text-xs text-gray-600">{{ count }}</span>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue'

const period = ref('7d')
const analytics = ref<Record<string, any>>({})

async function load() {
  try {
    const res = await fetch(`/api/v1/admin/search/analytics?period=${period.value}`)
    const data = await res.json()
    analytics.value = data.data ?? {}
  } catch (e) {
    console.error(e)
  }
}

function setPeriod(p: string) {
  period.value = p
  load()
}

function tierPercent(count: number): number {
  const total = Object.values(analytics.value.tier_usage ?? {}).reduce((a: number, b: unknown) => a + (b as number), 0)
  return total > 0 ? Math.round((count / (total as number)) * 100) : 0
}

onMounted(load)
</script>
