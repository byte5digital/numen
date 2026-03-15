<script setup>
import { ref, onMounted, onBeforeUnmount, watch, computed } from 'vue';
import * as d3 from 'd3';
import GraphNodeTooltip from './GraphNodeTooltip.vue';

const props = defineProps({
    spaceId: { type: [String, Number], required: true },
    contentId: { type: [String, Number], default: null },
    filters: { type: Object, default: () => ({ edgeTypes: [], minWeight: 0 }) },
});

const emit = defineEmits(['node-click', 'loaded']);

const svgRef = ref(null);
const containerRef = ref(null);
const nodes = ref([]);
const edges = ref([]);
const loading = ref(false);
const error = ref(null);
const tooltip = ref({ visible: false, x: 0, y: 0, node: null });

let simulation = null;
let svg = null;
let g = null;

const clusterColors = d3.scaleOrdinal([
    '#6366f1','#22c55e','#f97316','#a855f7',
    '#ec4899','#14b8a6','#eab308','#3b82f6',
    '#f43f5e','#84cc16','#06b6d4','#8b5cf6',
]);

const edgeColor = (type) => ({
    SIMILAR_TO: '#3b82f6',
    SHARES_TOPIC: '#22c55e',
    CITES: '#f97316',
    CO_MENTIONS: '#a855f7',
}[type] ?? '#6b7280');

async function fetchGraph() {
    loading.value = true;
    error.value = null;
    try {
        const url = props.contentId
            ? `/api/v1/graph/node/${props.contentId}`
            : `/api/v1/graph/space/${props.spaceId}`;
        const res = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
        });
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        const data = await res.json();
        const payload = data.data ?? data;
        nodes.value = (payload.nodes || []).map(n => ({
            id: n.id,
            title: n.title ?? n.content?.title ?? 'Untitled',
            content_type: n.content_type ?? n.content?.content_type,
            entity_labels: Array.isArray(n.entity_labels)
                ? n.entity_labels
                : (n.entity_labels ? Object.values(n.entity_labels) : []),
            cluster_id: n.cluster_id ?? 0,
            cluster_label: n.cluster_label ?? null,
            edge_count: n.edge_count ?? 0,
        }));
        edges.value = (payload.edges || []).map(e => ({
            source: e.source_id ?? e.source,
            target: e.target_id ?? e.target,
            weight: e.weight ?? 1,
            type: e.edge_type ?? e.type ?? 'SIMILAR_TO',
        }));
        emit('loaded', { nodeCount: nodes.value.length, edgeCount: edges.value.length });
        buildGraph();
    } catch (err) {
        error.value = err.message;
    } finally {
        loading.value = false;
    }
}

const filteredEdges = computed(() => edges.value.filter(e => {
    const typeOk = !props.filters.edgeTypes?.length || props.filters.edgeTypes.includes(e.type);
    const weightOk = e.weight >= (props.filters.minWeight ?? 0);
    return typeOk && weightOk;
}));

function nodeRadius(d) {
    return Math.max(6, Math.min(22, 6 + (d.edge_count || 0) * 1.2));
}

function truncate(str, len) {
    return str && str.length > len ? str.slice(0, len) + '\u2026' : (str || '');
}

function drag(sim) {
    return d3.drag()
        .on('start', (ev, d) => { if (!ev.active) sim.alphaTarget(0.3).restart(); d.fx = d.x; d.fy = d.y; })
        .on('drag', (ev, d) => { d.fx = ev.x; d.fy = ev.y; })
        .on('end', (ev, d) => { if (!ev.active) sim.alphaTarget(0); d.fx = null; d.fy = null; });
}

function buildGraph() {
    if (!svgRef.value || !nodes.value.length) return;
    d3.select(svgRef.value).selectAll('*').remove();
    const width = containerRef.value?.offsetWidth || 900;
    const height = containerRef.value?.offsetHeight || 600;

    svg = d3.select(svgRef.value).attr('width', width).attr('height', height);
    const defs = svg.append('defs');
    ['SIMILAR_TO','SHARES_TOPIC','CITES','CO_MENTIONS'].forEach(type => {
        defs.append('marker').attr('id', `arrow-${type}`)
            .attr('viewBox','0 -4 8 8').attr('refX',16).attr('refY',0)
            .attr('markerWidth',6).attr('markerHeight',6).attr('orient','auto')
            .append('path').attr('d','M0,-4L8,0L0,4')
            .attr('fill', edgeColor(type)).attr('opacity',0.7);
    });

    g = svg.append('g');
    const zoom = d3.zoom().scaleExtent([0.1,8]).on('zoom', ev => g.attr('transform', ev.transform));
    svg.call(zoom);

    const simNodes = nodes.value.map(n => ({ ...n }));
    const nodeById = Object.fromEntries(simNodes.map(n => [n.id, n]));
    const simEdges = filteredEdges.value.filter(e => nodeById[e.source] && nodeById[e.target]).map(e => ({ ...e }));

    simulation = d3.forceSimulation(simNodes)
        .force('link', d3.forceLink(simEdges).id(d => d.id).distance(d => 120 / (d.weight||1) + 60).strength(0.4))
        .force('charge', d3.forceManyBody().strength(-300))
        .force('center', d3.forceCenter(width/2, height/2))
        .force('collision', d3.forceCollide().radius(d => nodeRadius(d) + 4));

    const link = g.append('g').selectAll('line').data(simEdges).join('line')
        .attr('stroke', d => edgeColor(d.type))
        .attr('stroke-width', d => Math.max(0.5, Math.min(4, d.weight||1)))
        .attr('stroke-opacity', 0.6)
        .attr('marker-end', d => `url(#arrow-${d.type})`);

    const node = g.append('g').selectAll('g').data(simNodes).join('g')
        .attr('cursor','pointer')
        .call(drag(simulation))
        .on('mouseenter', (ev, d) => {
            const rect = svgRef.value.getBoundingClientRect();
            tooltip.value = { visible: true, x: ev.clientX - rect.left, y: ev.clientY - rect.top, node: d };
        })
        .on('mousemove', (ev) => {
            const rect = svgRef.value.getBoundingClientRect();
            tooltip.value = { ...tooltip.value, x: ev.clientX - rect.left, y: ev.clientY - rect.top };
        })
        .on('mouseleave', () => { tooltip.value = { ...tooltip.value, visible: false }; })
        .on('click', (ev, d) => { emit('node-click', d); });

    node.append('circle')
        .attr('r', d => nodeRadius(d))
        .attr('fill', d => clusterColors(d.cluster_id ?? 0))
        .attr('fill-opacity', 0.85)
        .attr('stroke', d => String(d.id) === String(props.contentId) ? '#fff' : 'transparent')
        .attr('stroke-width', 2);

    node.append('text')
        .text(d => truncate(d.title, 18))
        .attr('text-anchor','middle')
        .attr('dy', d => nodeRadius(d) + 12)
        .attr('fill','#d1d5db').attr('font-size','10px').attr('pointer-events','none');

    simulation.on('tick', () => {
        link.attr('x1',d=>d.source.x).attr('y1',d=>d.source.y).attr('x2',d=>d.target.x).attr('y2',d=>d.target.y);
        node.attr('transform', d => `translate(${d.x},${d.y})`);
    });

    if (props.contentId) {
        simulation.on('end', () => {
            const n = simNodes.find(n => String(n.id) === String(props.contentId));
            if (n) {
                svg.transition().duration(800).call(zoom.transform,
                    d3.zoomIdentity.translate(width/2,height/2).scale(1.2).translate(-n.x,-n.y));
            }
        });
    }
}

onMounted(() => {
    fetchGraph();
    const ro = new ResizeObserver(() => buildGraph());
    if (containerRef.value) ro.observe(containerRef.value);
    onBeforeUnmount(() => ro.disconnect());
});
onBeforeUnmount(() => { simulation?.stop(); });
watch(() => [props.spaceId, props.contentId], fetchGraph);
watch(() => props.filters, buildGraph, { deep: true });
</script>

<template>
    <div ref="containerRef" class="relative w-full h-full min-h-96">
        <div v-if="loading" class="absolute inset-0 flex items-center justify-center bg-gray-950/60 rounded-xl z-10">
            <div class="flex flex-col items-center gap-3">
                <div class="w-8 h-8 border-2 border-indigo-400 border-t-transparent rounded-full animate-spin"></div>
                <span class="text-sm text-gray-400">Loading graph...</span>
            </div>
        </div>
        <div v-else-if="error" class="absolute inset-0 flex items-center justify-center">
            <div class="text-center">
                <p class="text-red-400 text-sm mb-2">Failed to load graph</p>
                <p class="text-gray-500 text-xs">{{ error }}</p>
                <button @click="fetchGraph" class="mt-3 text-xs text-indigo-400 hover:underline">Retry</button>
            </div>
        </div>
        <div v-else-if="!nodes.length && !loading" class="absolute inset-0 flex items-center justify-center">
            <p class="text-gray-500 text-sm">No graph data yet. Generate content to build the graph.</p>
        </div>
        <svg ref="svgRef" class="w-full h-full bg-gray-950 rounded-xl"></svg>
        <GraphNodeTooltip :node="tooltip.node" :x="tooltip.x" :y="tooltip.y" :visible="tooltip.visible" />
    </div>
</template>
