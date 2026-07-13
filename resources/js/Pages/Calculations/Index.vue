<template>
    <Layout>
        <Breadcrumbs :items="[{ label: 'Nástěnka', href: '/' }]" />
        <div class="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <h1 class="text-4xl font-extrabold text-gray-900 font-heading tracking-tight">Historie kalkulací</h1>
                <p class="text-gray-500 mt-2 font-medium">Seznam všech vygenerovaných cenových nabídek pro klienty.</p>
            </div>
            <div>
                <Link
                    href="/calculations/create"
                    class="inline-flex items-center gap-2 brand-gradient px-8 py-4 rounded-full font-bold text-white shadow-brand hover:shadow-brand-lg transition-all hover:-translate-y-1 font-heading"
                >
                    ✨ Nová kalkulace
                </Link>
            </div>
        </div>


        <!-- Filters -->
        <div class="mb-8 bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-50">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-end">
                <div class="md:col-span-6">
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
                            placeholder="Hledat podle jména, emailu nebo firmy..."
                            class="block w-full pl-12 pr-4 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                        >
                    </div>
                </div>
                <div class="md:col-span-4">
                    <label for="status" class="block text-sm font-bold text-gray-700 ml-1 mb-2 font-heading">Stav</label>
                    <select
                        v-model="searchForm.status"
                        @change="search"
                        id="status"
                        class="block w-full px-4 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all appearance-none cursor-pointer"
                    >
                        <option value="">Všechny stavy</option>
                        <option value="draft">Návrh</option>
                        <option value="sent">Odesláno</option>
                        <option value="confirmed">Potvrzeno</option>
                    </select>
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

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-50 overflow-hidden">
            <div class="overflow-x-auto min-h-[400px]">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b border-gray-50">
                            <th class="px-8 py-6 text-left w-10">
                                <input 
                                    type="checkbox" 
                                    :checked="selectedIds.length === calculations.data.length && calculations.data.length > 0"
                                    @change="toggleSelectAll"
                                    class="rounded border-gray-300 text-brand-primary-from focus:ring-brand-primary-from"
                                >
                            </th>
                            <th class="px-8 py-6 text-left text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Zákazník</th>
                            <th class="px-8 py-6 text-left text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Kontakt</th>
                            <th class="px-8 py-6 text-left text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Celková cena</th>
                            <th class="px-8 py-6 text-left text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Čas</th>
                            <th class="px-8 py-6 text-left text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Datum</th>
                            <th class="px-8 py-6 text-right text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Akce</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="calc in calculations.data" :key="calc.id" :class="{'bg-brand-primary-from/5': selectedIds.includes(calc.id)}" class="hover:bg-gray-50/50 transition-colors group">
                            <td class="px-8 py-6">
                                <input 
                                    type="checkbox" 
                                    v-model="selectedIds" 
                                    :value="calc.id"
                                    class="rounded border-gray-300 text-brand-primary-from focus:ring-brand-primary-from"
                                >
                            </td>
                            <td class="px-8 py-6">
                                <div class="text-base font-bold text-gray-900 font-heading leading-tight">
                                    {{ calc.customer_name }}
                                </div>
                                <div class="text-xs font-semibold text-gray-400 mt-1">
                                    {{ calc.customer_company }}
                                </div>
                            </td>
                            <td class="px-8 py-6">
                                <div class="text-sm font-bold text-gray-900">{{ calc.customer_email }}</div>
                                <div class="text-xs font-semibold text-gray-400 mt-1">{{ calc.customer_phone }}</div>
                            </td>
                            <td class="px-8 py-6">
                                <span class="text-base font-black brand-text-gradient font-heading">
                                    {{ formatCurrency(calc.total_price) }}
                                </span>
                            </td>
                            <td class="px-8 py-6">
                                <span class="text-sm font-bold text-gray-700 bg-gray-50 px-3 py-1.5 rounded-xl border border-gray-50">
                                    {{ calc.total_days }} dní
                                </span>
                            </td>
                            <td class="px-8 py-6 text-sm font-bold text-gray-400">
                                {{ formatDate(calc.created_at) }}
                            </td>
                            <td class="px-8 py-6 text-right">
                                <div class="flex justify-end gap-2">
                                    <a 
                                        :href="calc.public_url" 
                                        target="_blank"
                                        class="p-2.5 bg-gray-50 text-gray-400 rounded-xl hover:text-brand-primary-from hover:bg-white hover:shadow-sm transition-all"
                                        title="Otevřít veřejný odkaz"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                                        </svg>
                                    </a>
                                    <Link 
                                        :href="`/calculations/${calc.id}/edit`"
                                        class="p-2.5 bg-gray-50 text-gray-400 rounded-xl hover:text-brand-primary-from hover:bg-white hover:shadow-sm transition-all"
                                        title="Upravit kalkulaci"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </Link>
                                    <Link 
                                        :href="`/calculations/${calc.id}`"
                                        class="p-2.5 bg-gray-50 text-gray-400 rounded-xl hover:text-brand-primary-to hover:bg-white hover:shadow-sm transition-all"
                                        title="Zobrazit detail"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                        </svg>
                                    </Link>
                                    <button
                                        @click="deleteCalculation(calc)"
                                        class="p-2.5 bg-gray-50 text-gray-400 rounded-xl hover:text-red-500 hover:bg-white hover:shadow-sm transition-all"
                                        title="Smazat"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
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
        <div v-if="calculations.links && calculations.links.length > 3" class="mt-10 flex flex-col sm:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-4">
                <p class="text-sm font-bold text-gray-400">
                    Zobrazeno {{ calculations.from }} až {{ calculations.to }} z {{ calculations.total }} výsledků
                </p>
                <select
                    v-model="searchForm.per_page"
                    @change="search"
                    class="block w-40 px-4 py-2 bg-white border-2 border-gray-100 rounded-xl text-xs font-bold text-gray-600 focus:ring-brand-primary-from focus:border-brand-primary-from transition-all appearance-none cursor-pointer"
                >
                    <option value="20">20 na stránku</option>
                    <option value="50">50 na stránku</option>
                    <option value="100">100 na stránku</option>
                </select>
            </div>
            <div>
                <nav class="relative z-0 inline-flex gap-2">
                    <Link
                        v-for="link in calculations.links"
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
        </div>

        <!-- Bulk Actions Bar -->
        <Transition
            enter-active-class="ease-out duration-300"
            enter-from-class="translate-y-20 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="ease-in duration-200"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-20 opacity-0"
        >
            <div v-if="selectedIds.length > 0" class="fixed bottom-10 left-1/2 -translate-x-1/2 z-50">
                <div class="bg-gray-900 text-white px-8 py-4 rounded-full shadow-2xl flex items-center gap-6 border border-white/10 backdrop-blur-xl">
                    <div class="flex items-center gap-3 pr-6 border-r border-white/10">
                        <span class="h-6 w-6 bg-brand-primary-from rounded-full flex items-center justify-center text-[10px] font-black">{{ selectedIds.length }}</span>
                        <span class="text-xs font-bold uppercase tracking-widest text-gray-400">Vybráno</span>
                    </div>
                    <button 
                        @click="bulkDelete"
                        class="text-xs font-black uppercase tracking-widest text-red-400 hover:text-red-300 transition-colors flex items-center gap-2"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                        Smazat vybrané
                    </button>
                    <button 
                        @click="selectedIds = []"
                        class="text-xs font-bold text-gray-500 hover:text-white transition-colors"
                    >
                        Zrušit
                    </button>
                </div>
            </div>
        </Transition>

        <!-- Confirmation Modal -->
        <ConfirmModal
            :show="confirmDelete.show"
            :title="confirmDelete.title"
            :message="confirmDelete.message"
            @close="confirmDelete.show = false"
            @confirm="executeDelete"
        />
    </Layout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import Layout from '../../Components/Layout.vue'
import Breadcrumbs from '../../Components/Breadcrumbs.vue'
import ConfirmModal from '../../Components/ConfirmModal.vue'

const props = defineProps({
    calculations: Object,
    filters: Object,
})

const searchForm = reactive({
    search: props.filters?.search || '',
    status: props.filters?.status || '',
    per_page: props.filters?.per_page || 20,
})

const search = () => {
    router.get('/calculations', {
        search: searchForm.search,
        status: searchForm.status,
        per_page: searchForm.per_page,
    }, {
        preserveState: true,
        replace: true,
    })
}

const clearFilters = () => {
    searchForm.search = ''
    searchForm.status = ''
    searchForm.per_page = 20
    search()
}

const selectedIds = ref([])

const confirmDelete = reactive({
    show: false,
    title: '',
    message: '',
    type: 'single',
    item: null
})

const deleteCalculation = (calc) => {
    confirmDelete.title = 'Smazat kalkulaci'
    confirmDelete.message = `Opravdu chcete smazat kalkulaci pro "${calc.customer_name}"?`
    confirmDelete.type = 'single'
    confirmDelete.item = calc
    confirmDelete.show = true
}

const toggleSelectAll = (e) => {
    if (e.target.checked) {
        selectedIds.value = props.calculations.data.map(c => c.id)
    } else {
        selectedIds.value = []
    }
}

const bulkDelete = () => {
    confirmDelete.title = 'Hromadné smazání'
    confirmDelete.message = `Opravdu chcete smazat ${selectedIds.value.length} vybraných kalkulací?`
    confirmDelete.type = 'bulk'
    confirmDelete.show = true
}

const executeDelete = () => {
    if (confirmDelete.type === 'single') {
        router.delete(`/calculations/${confirmDelete.item.id}`, {
            onSuccess: () => confirmDelete.show = false
        })
    } else if (confirmDelete.type === 'bulk') {
        router.post('/calculations/bulk-delete', { ids: selectedIds.value }, {
            onSuccess: () => {
                confirmDelete.show = false
                selectedIds.value = []
            }
        })
    }
}

const formatCurrency = (value) => {
    return new Intl.NumberFormat('cs-CZ', { style: 'currency', currency: 'CZK' }).format(value)
}

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('cs-CZ')
}

const copyLink = (url) => {
    navigator.clipboard.writeText(url)
    alert('Odkaz byl zkopírován do schránky!')
}
</script>
