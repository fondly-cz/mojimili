<template>
    <Layout>
        <Breadcrumbs :items="[{ label: 'Nástěnka', href: '/' }]" />
        <div class="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <h1 class="text-4xl font-extrabold text-gray-900 font-heading tracking-tight">Správa uživatelů</h1>
                <p class="text-gray-500 mt-2 font-medium">Správa přístupů a rolí pro členy vašeho týmu.</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-8 bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-50">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-end">
                <div class="md:col-span-10">
                    <label for="search" class="block text-sm font-bold text-gray-700 ml-1 mb-2 font-heading">Vyhledávání</label>
                    <div class="relative">
                        <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            v-model="searchForm.search"
                            @input="search"
                            type="text"
                            id="search"
                            placeholder="Hledat podle jména, emailu..."
                            class="block w-full pl-12 pr-4 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                        >
                    </div>
                </div>
                <div class="md:col-span-2">
                    <button
                        @click="clearFilters"
                        class="w-full flex items-center justify-center gap-2 px-4 py-3.5 border-2 border-gray-100 rounded-2xl text-sm font-bold text-gray-400 hover:text-brand-primary-from hover:border-brand-primary-from transition-all"
                    >
                        Resetovat
                    </button>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-50 overflow-hidden">
            <div class="overflow-x-auto min-h-[400px]">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b border-gray-50">
                            <th class="px-8 py-6 text-left text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Uživatel</th>
                            <th class="px-8 py-6 text-left text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Kontakt</th>
                            <th class="px-8 py-6 text-left text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Role</th>
                            <th class="px-8 py-6 text-right text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Akce</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50/50 transition-colors group">
                            <td class="px-8 py-6">
                                <div class="flex items-center gap-4">
                                    <div class="h-12 w-12 bg-gray-50 rounded-2xl flex items-center justify-center text-brand-primary-from font-black shadow-sm group-hover:bg-white transition-colors">
                                        {{ user.name.charAt(0) }}
                                    </div>
                                    <div>
                                        <div class="text-base font-bold text-gray-900 font-heading leading-tight">
                                            {{ user.name }}
                                        </div>
                                        <div v-if="user.google_id" class="text-[10px] font-black text-blue-500 uppercase tracking-widest mt-1 flex items-center gap-1">
                                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12.48 10.92v3.28h7.84c-.24 1.84-.9 3.03-1.63 3.96-1.07 1.07-2.52 2.23-5.26 2.23-4.38 0-7.75-3.53-7.75-7.91s3.37-7.91 7.75-7.91c2.31 0 4.1.84 5.37 2.05l2.42-2.42c-2.1-1.95-4.87-3.21-7.79-3.21-6.19 0-11.23 5.04-11.23 11.23s5.04 11.23 11.23 11.23c3.34 0 5.86-1.1 7.91-3.21 2.1-2.1 2.77-5.06 2.77-7.46 0-.71-.06-1.33-.16-1.95h-10.51z"/></svg>
                                            Google Auth
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-8 py-6">
                                <div class="text-sm font-bold text-gray-900">{{ user.email }}</div>
                                <div class="text-xs font-semibold text-gray-400 mt-1">{{ user.phone || 'Bez telefonu' }}</div>
                            </td>
                            <td class="px-8 py-6">
                                <span 
                                    class="inline-flex items-center px-4 py-1.5 text-[10px] font-black uppercase tracking-wider rounded-full shadow-sm"
                                    :class="{
                                        'bg-brand-primary-from text-white': user.role === 'admin',
                                        'bg-brand-accent text-white': user.role === 'manager',
                                        'bg-gray-100 text-gray-400': !user.role
                                    }"
                                >
                                    {{ getRoleLabel(user.role) }}
                                </span>
                            </td>
                            <td class="px-8 py-6 text-right">
                                <div class="flex justify-end gap-2">
                                    <button
                                        @click="editUser(user)"
                                        class="p-2.5 bg-gray-50 text-gray-400 rounded-xl hover:text-brand-primary-from hover:bg-white hover:shadow-sm transition-all"
                                        title="Upravit (připravujeme)"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="users.links.length > 3" class="mt-10 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-sm font-bold text-gray-400">
                Zobrazeno {{ users.from }} až {{ users.to }} z {{ users.total }} uživatelů
            </p>
            <nav class="relative z-0 inline-flex gap-2">
                <Link
                    v-for="link in users.links"
                    :key="link.label"
                    :href="link.url"
                    v-html="link.label"
                    class="relative inline-flex items-center px-4 py-2 text-sm font-bold rounded-xl transition-all border-2"
                    :class="{
                        'brand-gradient text-white border-transparent shadow-sm': link.active,
                        'bg-white border-gray-100 text-gray-400 hover:border-brand-primary-from hover:text-brand-primary-from': !link.active,
                        'opacity-30 cursor-not-allowed': !link.url
                    }"
                />
            </nav>
        </div>
    </Layout>
</template>

<script setup>
import { reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import Layout from '../../Components/Layout.vue'
import Breadcrumbs from '../../Components/Breadcrumbs.vue'

const props = defineProps({
    users: Object,
    filters: Object,
})

const searchForm = reactive({
    search: props.filters.search || '',
})

const search = () => {
    router.get('/users', searchForm, {
        preserveState: true,
        replace: true,
    })
}

const clearFilters = () => {
    searchForm.search = ''
    search()
}

const getRoleLabel = (role) => {
    const roles = {
        'admin': 'Administrátor',
        'manager': 'Manažer'
    }
    return roles[role] || 'Bez role'
}

const editUser = (user) => {
    // Placeholder for future implementation
    alert('Úprava uživatele ' + user.name + ' bude dostupná v příští aktualizaci.')
}
</script>
