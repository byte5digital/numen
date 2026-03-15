<script setup>
import { Link, usePage, router } from '@inertiajs/vue3'
import ChatSidebar from '../Components/Chat/ChatSidebar.vue';
import { ref, computed } from 'vue';

const page = usePage();
const user = computed(() => page.props.auth?.user);
const sidebarOpen = ref(true);

function logout() {
    router.post('/logout');
}

const navigation = [
    { name: 'Dashboard', href: '/admin', icon: '📊' },
    { name: 'Content', href: '/admin/content', icon: '📝' },
    { name: 'Briefs', href: '/admin/briefs', icon: '📋' },
    { name: 'Pipelines', href: '/admin/pipelines', icon: '⚡' },
    { name: 'Personas', href: '/admin/personas', icon: '🤖' },
    { name: 'Pages', href: '/admin/pages', icon: '🗂️' },
    { name: 'Taxonomy', href: '/admin/taxonomy', icon: '🏷️' },
    { name: 'Media', href: '/admin/media', icon: '🖼️' },
    { name: 'Analytics', href: '/admin/analytics', icon: '📈' },
    { name: 'Queue', href: '/admin/queue', icon: '⚙️' },
    { name: 'Users', href: '/admin/users', icon: '👥' },
    { name: 'API Tokens', href: '/admin/tokens', icon: '🔑' },
    { name: 'Settings', href: '/admin/settings', icon: '⚙️' },
];
</script>

<template>
    <div class="min-h-screen bg-gray-950 text-gray-100">
        <!-- Sidebar -->
        <aside
            class="fixed inset-y-0 left-0 z-50 w-64 bg-gray-900 border-r border-gray-800 transform transition-transform duration-200"
            :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
        >
            <!-- Logo -->
            <div class="flex items-center gap-3 px-6 py-5 border-b border-gray-800">
                <span class="text-2xl">⚡</span>
                <div>
                    <h1 class="text-lg font-bold text-white tracking-tight">Numen</h1>
                    <p class="text-xs text-gray-500">by byte5</p>
                </div>
            </div>

            <!-- Nav -->
            <nav class="mt-4 px-3 space-y-1">
                <Link
                    v-for="item in navigation"
                    :key="item.name"
                    :href="item.href"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-lg text-sm font-medium transition-colors"
                    :class="$page.url.startsWith(item.href)
                        ? 'bg-indigo-500/15 text-indigo-400 border-l-2 border-indigo-400'
                        : 'text-gray-400 hover:bg-gray-800/60 hover:text-white border-l-2 border-transparent'"
                >
                    <span class="text-lg">{{ item.icon }}</span>
                    {{ item.name }}
                </Link>
            </nav>

            <!-- Footer -->
            <div class="absolute bottom-0 left-0 right-0 px-6 py-4 border-t border-gray-800">
                <div v-if="user" class="flex items-center justify-between mb-2">
                    <span class="text-xs text-gray-400">{{ user.name }}</span>
                    <div class="flex items-center gap-3">
                        <Link href="/admin/profile/password" class="text-xs text-gray-600 hover:text-indigo-400 transition">Password</Link>
                        <button @click="logout" class="text-xs text-gray-600 hover:text-red-400 transition">Logout</button>
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <div class="w-2 h-2 rounded-full bg-indigo-400 animate-pulse"></div>
                    <span class="text-xs text-gray-500">AI-Powered CMS</span>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="pl-64">
            <!-- Top bar -->
            <header class="sticky top-0 z-40 flex items-center justify-between px-8 py-4 bg-gray-950/80 backdrop-blur-sm border-b border-gray-800">
                <button @click="sidebarOpen = !sidebarOpen" class="text-gray-400 hover:text-white">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
                    </svg>
                </button>
                <div class="flex items-center gap-4">
                    <span class="text-xs text-gray-500">AI-First Content Management</span>
                </div>
            </header>

            <!-- Page Content -->
            <main class="px-8 py-6">
                <slot />
            </main>
        </div>
    </div>

    <!-- AI Chat Sidebar -->
    <ChatSidebar />
</template>