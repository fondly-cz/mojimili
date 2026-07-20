<script setup>
import { Link } from '@inertiajs/vue3';

const items = [
    {
        name: 'Nástěnka',
        href: '/',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" /></svg>',
        component: 'Dashboard',
    },
    {
        name: 'Firmy',
        href: '/companies',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" /></svg>',
        component: 'Companies/',
    },
    {
        name: 'Kalkulace',
        href: '/calculations',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z" /></svg>',
        component: 'Calculations/',
    },
    {
        name: 'Projekty',
        href: '/projects',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01" /></svg>',
        component: 'Projects/',
    },
    {
        name: 'Ceník služeb',
        href: '/services',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>',
        component: 'Services/',
        adminOnly: true,
    },
    {
        name: 'Moje firma',
        href: '/my-company',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>',
        component: 'MyCompany/',
    },
    {
        name: 'MCP / API klíče',
        href: '/settings/api-keys',
        icon: '<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" /></svg>',
        component: 'Settings/',
        adminOnly: true,
    },
];
</script>

<template>
    <div
        class="fixed inset-y-0 left-0 z-60 flex w-72 flex-col border-r border-gray-100 bg-white shadow-sm"
    >
        <!-- Logo -->
        <div class="p-8 pb-10">
            <Link href="/" class="group flex items-center gap-3">
                <img
                    src="/logo.svg"
                    alt="MojiMili Logo"
                    class="h-10 w-auto transition-transform group-hover:scale-105"
                />
                <span
                    class="brand-text-gradient font-heading text-2xl font-black tracking-tight"
                    >MojiMili</span
                >
            </Link>
        </div>

        <!-- Navigation -->
        <nav class="flex-1 space-y-1.5 overflow-y-auto px-4">
            <template v-for="item in items" :key="item.name">
                <Link
                    v-if="
                        !item.adminOnly ||
                        $page.props.auth.user?.role === 'admin'
                    "
                    :href="item.href"
                    class="font-heading group flex items-center gap-3 rounded-2xl px-4 py-3.5 font-bold transition-all duration-300"
                    :class="[
                        $page.component.startsWith(item.component) ||
                        ($page.component === 'Dashboard' &&
                            item.component === 'Dashboard')
                            ? 'brand-gradient shadow-brand translate-x-1 text-white'
                            : 'text-gray-400 hover:bg-gray-50 hover:text-gray-900',
                    ]"
                >
                    <div
                        class="shrink-0 transition-colors"
                        :class="[
                            $page.component.startsWith(item.component) ||
                            ($page.component === 'Dashboard' &&
                                item.component === 'Dashboard')
                                ? 'text-white'
                                : 'group-hover:text-brand-primary-from text-gray-300',
                        ]"
                        v-html="item.icon"
                    ></div>
                    <span class="text-[15px] leading-none">{{
                        item.name
                    }}</span>
                </Link>
            </template>
        </nav>

        <!-- Footer / Upgrade Card -->
        <div class="p-6">
            <div
                class="group relative overflow-hidden rounded-[2rem] bg-gray-50 p-6"
            >
                <div
                    class="absolute -right-4 -bottom-4 opacity-10 transition-transform duration-500 group-hover:rotate-12"
                >
                    <svg
                        xmlns="http://www.w3.org/2000/svg"
                        class="h-24 w-24"
                        fill="none"
                        viewBox="0 0 24 24"
                        stroke="currentColor"
                    >
                        <path
                            stroke-linecap="round"
                            stroke-linejoin="round"
                            stroke-width="2"
                            d="M13 10V3L4 14h7v7l9-11h-7z"
                        />
                    </svg>
                </div>
                <div class="relative z-10">
                    <h5
                        class="font-heading mb-1 text-sm font-black tracking-wider text-gray-900 uppercase"
                    >
                        MojiMili Pro
                    </h5>
                    <p
                        class="mb-4 text-xs leading-relaxed font-medium text-gray-400"
                    >
                        Upgrade for extra features and premium support.
                    </p>
                    <button
                        class="hover:bg-brand-primary-from hover:border-brand-primary-from w-full rounded-xl border border-gray-100 bg-white py-2.5 text-xs font-bold tracking-widest text-gray-900 uppercase shadow-sm transition-all hover:text-white"
                    >
                        Upgrade Now
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
