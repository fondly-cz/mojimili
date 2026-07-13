<script setup>
import { Link } from '@inertiajs/vue3';

defineProps({
    items: {
        type: Array,
        required: true,
    },
});
</script>

<template>
    <nav aria-label="Breadcrumb" class="mb-4">
        <ol class="flex items-center flex-wrap gap-x-2 gap-y-1 text-xs font-black uppercase tracking-widest font-heading">
            <li v-for="(item, index) in items" :key="index" class="flex items-center gap-x-2">
                <component
                    :is="item.href ? Link : 'span'"
                    :href="item.href || undefined"
                    class="transition-colors flex items-center"
                    :class="item.href ? 'text-gray-400 hover:text-brand-primary-from' : 'text-gray-400'"
                >
                    <svg
                        v-if="item.href === '/'"
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-4 w-4"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                        aria-label="Nástěnka"
                    >
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                    </svg>
                    <template v-else>{{ item.label }}</template>
                </component>
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    class="h-3 w-3 text-gray-300 shrink-0"
                    fill="none"
                    viewBox="0 0 24 24"
                    stroke="currentColor"
                >
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7" />
                </svg>
            </li>
        </ol>
    </nav>
</template>
