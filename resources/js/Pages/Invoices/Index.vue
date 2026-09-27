<template>
    <Layout>
        <Breadcrumbs :items="[{ label: 'Nástěnka', href: '/' }]" />
        <div class="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <h1 class="text-4xl font-extrabold text-gray-900 font-heading tracking-tight">Faktury</h1>
                <p class="text-gray-500 mt-2 font-medium">Faktury a výkazy práce, které do nich byly vyfakturovány.</p>
            </div>
            <div>
                <Link
                    href="/billing"
                    class="inline-flex items-center gap-2 brand-gradient px-8 py-4 rounded-full font-bold text-white shadow-brand hover:shadow-brand-lg transition-all hover:-translate-y-1 font-heading"
                >
                    K fakturaci
                </Link>
            </div>
        </div>

        <div class="mb-8 bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-50">
            <label for="search" class="block text-sm font-bold text-gray-700 ml-1 mb-2 font-heading">Vyhledávání</label>
            <input
                v-model="searchForm.search"
                @input="search"
                type="text"
                id="search"
                placeholder="Hledat podle čísla nebo poznámky..."
                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
            >
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-50 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b border-gray-50">
                            <th class="px-8 py-6 text-left text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Faktura</th>
                            <th class="px-8 py-6 text-left text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Vystaveno</th>
                            <th class="px-8 py-6 text-right text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Výkazy</th>
                            <th class="px-8 py-6 text-right text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Čas</th>
                            <th class="px-8 py-6 text-right text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Částka</th>
                            <th class="px-8 py-6 text-right text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Odkaz</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-if="invoices.data.length === 0">
                            <td colspan="6" class="px-8 py-20 text-center">
                                <p class="text-sm font-bold text-gray-400">Zatím tu není žádná faktura.</p>
                                <Link href="/billing" class="mt-3 inline-block text-sm font-black brand-text-gradient">Vyfakturovat výkazy</Link>
                            </td>
                        </tr>
                        <tr v-for="invoice in invoices.data" :key="invoice.id" class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-8 py-6">
                                <Link :href="`/invoices/${invoice.id}`" class="text-base font-bold text-gray-900 font-heading hover:text-brand-primary-from transition-colors">
                                    {{ invoice.number }}
                                </Link>
                                <div v-if="invoice.note" class="text-xs font-semibold text-gray-400 mt-1 line-clamp-1 max-w-md">{{ invoice.note }}</div>
                            </td>
                            <td class="px-8 py-6 text-sm font-bold text-gray-600">{{ formatDate(invoice.issued_at) }}</td>
                            <td class="px-8 py-6 text-right text-sm font-bold text-gray-600">{{ invoice.work_reports_count }}</td>
                            <td class="px-8 py-6 text-right text-sm font-bold text-gray-600 whitespace-nowrap">{{ formatMinutes(invoice.total_minutes) }}</td>
                            <td class="px-8 py-6 text-right text-sm font-black text-gray-900 whitespace-nowrap">{{ formatCurrency(invoice.total_amount) }}</td>
                            <td class="px-8 py-6 text-right">
                                <a
                                    v-if="invoice.url"
                                    :href="invoice.url"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-xs font-black uppercase tracking-widest text-brand-primary-from hover:underline"
                                >
                                    Otevřít ↗
                                </a>
                                <span v-else class="text-sm font-bold text-gray-300">—</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="invoices.links.length > 3" class="mt-10 flex justify-end">
            <nav class="relative z-0 inline-flex gap-2">
                <Link
                    v-for="link in invoices.links"
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
import { formatCurrency, formatDate, formatMinutes } from '../../utils/billing'

const props = defineProps({
    invoices: Object,
    filters: Object,
})

const searchForm = reactive({
    search: props.filters.search || '',
})

let timeout = null
const search = () => {
    clearTimeout(timeout)
    timeout = setTimeout(() => {
        router.get('/invoices', searchForm.search ? { search: searchForm.search } : {}, { preserveState: true, replace: true })
    }, 300)
}
</script>
