<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const page = usePage();
const isHome = computed(() => page.url === '/');
</script>

<template>
    <div class="min-h-screen bg-white text-slate-900 flex flex-col">
        <!-- Nav -->
        <nav class="sticky top-0 z-50 bg-white/90 backdrop-blur-lg border-b border-slate-200/50 shadow-sm">
            <div class="max-w-6xl mx-auto flex items-center justify-between px-6 py-4">
                <Link href="/" class="flex items-center gap-2 group">
                    <span class="text-2xl">⚡</span>
                    <span class="font-bold text-slate-900 text-lg group-hover:text-indigo-500 transition">Numen</span>
                    <span class="text-xs text-slate-400 hidden sm:inline ml-1">by byte5.labs</span>
                </Link>

                <div class="flex items-center gap-6">
                    <Link href="/" class="text-sm font-medium transition"
                        :class="isHome ? 'text-indigo-500' : 'text-slate-600 hover:text-indigo-500'">
                        Home
                    </Link>
                    <Link href="/blog" class="text-sm font-medium transition"
                        :class="page.url.startsWith('/blog') ? 'text-indigo-500' : 'text-slate-600 hover:text-indigo-500'">
                        Blog
                    </Link>
                    <a href="/api/documentation" class="text-sm text-slate-600 hover:text-indigo-500 transition hidden sm:inline font-medium">
                        API
                    </a>
                    <a href="https://github.com/byte5digital/numen" target="_blank"
                       class="text-sm text-slate-600 hover:text-indigo-500 transition hidden sm:inline font-medium">
                        GitHub
                    </a>
                    <Link href="/login" class="px-5 py-2 bg-indigo-500 text-white rounded-full text-sm font-medium hover:bg-indigo-600 transition shadow-md">
                        Admin
                    </Link>
                </div>
            </div>
        </nav>

        <!-- Page Content -->
        <main class="flex-1">
            <slot />
        </main>

        <!-- Footer -->
        <footer class="bg-slate-900 py-12 px-6 mt-auto">
            <div class="max-w-6xl mx-auto flex flex-col md:flex-row items-center justify-between gap-6">
                <div class="flex items-center gap-2">
                    <span>⚡</span>
                    <span class="text-sm text-white/75">Numen by byte5.labs</span>
                </div>
                <div class="flex items-center gap-6 text-sm text-white/50">
                    <Link href="/" class="hover:text-white transition">Home</Link>
                    <Link href="/blog" class="hover:text-white transition">Blog</Link>
                    <a href="/api/documentation" class="hover:text-white transition">API</a>
                    <a href="https://github.com/byte5digital/numen" target="_blank" class="hover:text-white transition">GitHub</a>
                    <a href="https://www.byte5.de" target="_blank" class="hover:text-white transition">byte5.de</a>
                </div>
                <p class="text-xs text-white/40">AI-First Content Management</p>
            </div>
        </footer>
    </div>
</template>
