<template>
    <Layout>
        <Breadcrumbs :items="[{ label: 'Nástěnka', href: '/' }]" />
        <div class="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <h1 class="text-4xl font-extrabold text-gray-900 font-heading tracking-tight">K fakturaci</h1>
                <p class="text-gray-500 mt-2 font-medium">Nevyfakturované výkazy práce. Vyberte je a vložte do faktury.</p>
            </div>
            <div>
                <Link
                    href="/invoices"
                    class="inline-flex items-center gap-2 bg-white border-2 border-gray-100 px-6 py-4 rounded-full font-bold text-gray-500 hover:text-brand-primary-from hover:border-brand-primary-from transition-all font-heading uppercase tracking-widest text-[10px]"
                >
                    Vystavené faktury
                </Link>
            </div>
        </div>

        <!-- Filters -->
        <div class="mb-8 bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-50">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-end">
                <div class="md:col-span-3">
                    <label class="block text-sm font-bold text-gray-700 ml-1 mb-2 font-heading">Firma</label>
                    <select v-model="filterForm.company_id" @change="applyFilters" :class="selectClass">
                        <option value="">Všechny firmy</option>
                        <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.name }}</option>
                    </select>
                </div>
                <div class="md:col-span-3">
                    <label class="block text-sm font-bold text-gray-700 ml-1 mb-2 font-heading">Projekt</label>
                    <select v-model="filterForm.project_id" @change="applyFilters" :class="selectClass">
                        <option value="">Všechny projekty</option>
                        <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-bold text-gray-700 ml-1 mb-2 font-heading">Kdo</label>
                    <select v-model="filterForm.user_id" @change="applyFilters" :class="selectClass">
                        <option value="">Všichni</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                    </select>
                </div>
                <div class="md:col-span-3 grid grid-cols-2 gap-2">
                    <div>
                        <label class="block text-sm font-bold text-gray-700 ml-1 mb-2 font-heading">Od</label>
                        <input v-model="filterForm.date_from" @change="applyFilters" type="date" :class="selectClass">
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-gray-700 ml-1 mb-2 font-heading">Do</label>
                        <input v-model="filterForm.date_to" @change="applyFilters" type="date" :class="selectClass">
                    </div>
                </div>
                <div class="md:col-span-1">
                    <button
                        @click="clearFilters"
                        class="w-full flex items-center justify-center px-4 py-3.5 border-2 border-gray-100 rounded-2xl text-sm font-bold text-gray-400 hover:text-brand-primary-from hover:border-brand-primary-from transition-all"
                        title="Resetovat filtry"
                    >
                        ✕
                    </button>
                </div>
            </div>
        </div>

        <!-- Totals -->
        <div class="mb-8 grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div class="bg-white rounded-[2rem] border border-gray-50 p-8 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Nevyfakturovaný čas</p>
                <p class="mt-2 text-3xl font-black text-gray-900 font-heading">{{ formatMinutes(sumMinutes(reports)) }}</p>
            </div>
            <div class="bg-white rounded-[2rem] border border-gray-50 p-8 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Nevyfakturovaná částka</p>
                <p class="mt-2 text-3xl font-black text-amber-500 font-heading">{{ formatCurrency(sumAmount(reports)) }}</p>
            </div>
        </div>

        <div v-if="reports.length === 0" class="bg-white rounded-[2.5rem] border border-gray-50 p-16 text-center shadow-sm">
            <p class="text-sm font-bold text-gray-400">Vše je vyfakturováno. Žádné výkazy nečekají.</p>
        </div>

        <div v-else class="space-y-8 pb-32">
            <div
                v-for="group in groups"
                :key="group.key"
                class="bg-white rounded-[2.5rem] border border-gray-50 shadow-sm overflow-hidden"
            >
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-50 px-10 py-6">
                    <label class="flex items-center gap-4 cursor-pointer">
                        <input
                            type="checkbox"
                            :checked="isGroupSelected(group)"
                            :indeterminate.prop="isGroupPartial(group)"
                            @change="toggleGroup(group)"
                            class="h-5 w-5 rounded-lg border-gray-300 text-brand-primary-from focus:ring-brand-primary-from"
                        >
                        <div>
                            <Link :href="`/projects/${group.project.id}`" class="text-lg font-black text-gray-900 font-heading hover:text-brand-primary-from transition-colors">
                                {{ group.project.name }}
                            </Link>
                            <p class="mt-1 text-xs font-semibold text-gray-400">{{ group.project.company?.name || 'Bez firmy' }}</p>
                        </div>
                    </label>
                    <p class="text-sm font-black text-gray-900 font-heading">
                        {{ formatMinutes(sumMinutes(group.reports)) }} · {{ formatCurrency(sumAmount(group.reports)) }}
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-50 text-left text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">
                                <th class="px-10 py-4 w-10"></th>
                                <th class="px-4 py-4">Úkol</th>
                                <th class="px-4 py-4">Datum</th>
                                <th class="px-4 py-4">Kdo</th>
                                <th class="px-4 py-4 text-right">Čas</th>
                                <th class="px-4 py-4 text-right">Sazba</th>
                                <th class="px-10 py-4 text-right">Částka</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr
                                v-for="report in group.reports"
                                :key="report.id"
                                class="font-semibold text-gray-600 hover:bg-gray-50/50 transition-colors cursor-pointer"
                                :class="{ 'bg-brand-primary-from/5': selectedIds.includes(report.id) }"
                                @click="toggleReport(report.id)"
                            >
                                <td class="px-10 py-4">
                                    <input
                                        type="checkbox"
                                        :checked="selectedIds.includes(report.id)"
                                        @click.stop
                                        @change="toggleReport(report.id)"
                                        class="rounded border-gray-300 text-brand-primary-from focus:ring-brand-primary-from"
                                    >
                                </td>
                                <td class="px-4 py-4">
                                    <div class="font-bold text-gray-900">{{ report.todo.name }}</div>
                                    <div class="text-xs text-gray-400">
                                        {{ report.todo.todolist.name }}<span v-if="report.description"> · {{ report.description }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">{{ formatDate(report.date) }}</td>
                                <td class="px-4 py-4 whitespace-nowrap">{{ report.user?.name || '—' }}</td>
                                <td class="px-4 py-4 text-right whitespace-nowrap">{{ formatMinutes(report.minutes) }}</td>
                                <td class="px-4 py-4 text-right whitespace-nowrap">{{ formatCurrency(report.hourly_rate) }}/h</td>
                                <td class="px-10 py-4 text-right whitespace-nowrap font-black text-gray-900">{{ formatCurrency(reportAmount(report)) }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Selection bar -->
        <Transition
            enter-active-class="ease-out duration-300"
            enter-from-class="translate-y-20 opacity-0"
            enter-to-class="translate-y-0 opacity-100"
            leave-active-class="ease-in duration-200"
            leave-from-class="translate-y-0 opacity-100"
            leave-to-class="translate-y-20 opacity-0"
        >
            <div v-if="selectedIds.length > 0" class="fixed bottom-10 left-1/2 -translate-x-1/2 z-50 max-w-[calc(100vw-2rem)]">
                <div class="bg-gray-900 text-white px-8 py-4 rounded-full shadow-2xl flex flex-wrap items-center gap-6 border border-white/10 backdrop-blur-xl">
                    <div class="flex items-center gap-3 pr-6 border-r border-white/10">
                        <span class="h-6 min-w-6 px-1.5 bg-brand-primary-from rounded-full flex items-center justify-center text-[10px] font-black">{{ selectedIds.length }}</span>
                        <span class="text-xs font-bold text-gray-300">{{ formatMinutes(sumMinutes(selectedReports)) }} · {{ formatCurrency(sumAmount(selectedReports)) }}</span>
                    </div>
                    <button @click="openCreate" class="text-xs font-black uppercase tracking-widest text-green-400 hover:text-green-300 transition-colors">
                        Vytvořit fakturu
                    </button>
                    <button
                        v-if="invoices.length > 0"
                        @click="attach.show = true"
                        class="text-xs font-black uppercase tracking-widest text-blue-300 hover:text-blue-200 transition-colors"
                    >
                        Přidat do faktury
                    </button>
                    <button @click="selectedIds = []" class="text-xs font-bold text-gray-500 hover:text-white transition-colors">
                        Zrušit
                    </button>
                </div>
            </div>
        </Transition>

        <!-- Create invoice -->
        <Modal :show="create.show" title="Vytvořit fakturu" @close="create.show = false">
            <template #content>
                <p class="mb-6 text-xs font-bold text-gray-400">
                    {{ selectedIds.length }} výkazů · {{ formatMinutes(sumMinutes(selectedReports)) }} · {{ formatCurrency(invoicedTotal(invoiceForm.hourly_rate)) }} bez DPH
                </p>
                <div class="mb-6">
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Sazba pro všechny vybrané výkazy (Kč/h)</label>
                    <input v-model="invoiceForm.hourly_rate" type="number" min="0" step="0.01" :class="selectClass" placeholder="Ponechat sazby výkazů">
                    <p v-if="invoiceForm.errors.hourly_rate" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ invoiceForm.errors.hourly_rate }}</p>
                </div>
                <InvoiceFields
                    v-model:number="invoiceForm.number"
                    v-model:issued-at="invoiceForm.issued_at"
                    v-model:url="invoiceForm.url"
                    v-model:note="invoiceForm.note"
                    :errors="invoiceForm.errors"
                />
            </template>
            <template #footer>
                <button
                    @click="submitCreate"
                    :disabled="invoiceForm.processing"
                    class="brand-gradient px-8 py-3 rounded-full font-bold text-white shadow-brand transition-all font-heading disabled:opacity-50"
                >
                    Označit jako vyfakturované
                </button>
                <button @click="create.show = false" class="px-6 py-3 rounded-full text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-gray-600 transition-colors">
                    Zrušit
                </button>
            </template>
        </Modal>

        <!-- Attach to existing invoice -->
        <Modal :show="attach.show" title="Přidat do existující faktury" @close="attach.show = false">
            <template #content>
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Faktura</label>
                <select v-model="attachForm.invoice_id" :class="selectClass">
                    <option :value="null" disabled>Vyberte fakturu</option>
                    <option v-for="invoice in invoices" :key="invoice.id" :value="invoice.id">
                        {{ invoice.number }}<template v-if="invoice.issued_at"> ({{ formatDate(invoice.issued_at) }})</template>
                    </option>
                </select>
                <p v-if="attachForm.errors.work_report_ids" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ attachForm.errors.work_report_ids }}</p>
                <label class="mt-6 block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Sazba pro všechny vybrané výkazy (Kč/h)</label>
                <input v-model="attachForm.hourly_rate" type="number" min="0" step="0.01" :class="selectClass" placeholder="Ponechat sazby výkazů">
                <p v-if="attachForm.errors.hourly_rate" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ attachForm.errors.hourly_rate }}</p>
                <p class="mt-4 text-xs font-bold text-gray-400">
                    {{ selectedIds.length }} výkazů · {{ formatMinutes(sumMinutes(selectedReports)) }} · {{ formatCurrency(invoicedTotal(attachForm.hourly_rate)) }} bez DPH
                </p>
            </template>
            <template #footer>
                <button
                    @click="submitAttach"
                    :disabled="attachForm.processing || !attachForm.invoice_id"
                    class="brand-gradient px-8 py-3 rounded-full font-bold text-white shadow-brand transition-all font-heading disabled:opacity-50"
                >
                    Přidat
                </button>
                <button @click="attach.show = false" class="px-6 py-3 rounded-full text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-gray-600 transition-colors">
                    Zrušit
                </button>
            </template>
        </Modal>
    </Layout>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import Layout from '../../Components/Layout.vue'
import Breadcrumbs from '../../Components/Breadcrumbs.vue'
import Modal from '../../Components/Modal.vue'
import InvoiceFields from '../../Components/InvoiceFields.vue'
import { formatCurrency, formatDate, formatMinutes, reportAmount } from '../../utils/billing'

const props = defineProps({
    reports: Array,
    filters: Object,
    projects: Array,
    companies: Array,
    users: Array,
    invoices: Array,
})

const selectClass = 'block w-full px-4 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all'

const sumMinutes = (reports) => reports.reduce((sum, r) => sum + r.minutes, 0)
const sumAmount = (reports) => reports.reduce((sum, r) => sum + reportAmount(r), 0)

// --- Grouping by project ---
const groups = computed(() => {
    const map = new Map()

    for (const report of props.reports) {
        const project = report.todo.todolist.project
        if (!map.has(project.id)) {
            map.set(project.id, { key: project.id, project, reports: [] })
        }
        map.get(project.id).reports.push(report)
    }

    return [...map.values()].sort((a, b) =>
        (a.project.company?.name || '').localeCompare(b.project.company?.name || '', 'cs')
        || a.project.name.localeCompare(b.project.name, 'cs'))
})

// --- Selection ---
const selectedIds = ref([])
const selectedReports = computed(() => props.reports.filter(r => selectedIds.value.includes(r.id)))

// Drop selections that disappeared after filtering or invoicing.
watch(() => props.reports, (reports) => {
    const ids = new Set(reports.map(r => r.id))
    selectedIds.value = selectedIds.value.filter(id => ids.has(id))
})

const toggleReport = (id) => {
    selectedIds.value = selectedIds.value.includes(id)
        ? selectedIds.value.filter(x => x !== id)
        : [...selectedIds.value, id]
}

const isGroupSelected = (group) => group.reports.every(r => selectedIds.value.includes(r.id))
const isGroupPartial = (group) => !isGroupSelected(group) && group.reports.some(r => selectedIds.value.includes(r.id))

const toggleGroup = (group) => {
    const ids = group.reports.map(r => r.id)
    selectedIds.value = isGroupSelected(group)
        ? selectedIds.value.filter(id => !ids.includes(id))
        : [...new Set([...selectedIds.value, ...ids])]
}

// --- Filters ---
const filterForm = reactive({
    company_id: props.filters.company_id || '',
    project_id: props.filters.project_id || '',
    user_id: props.filters.user_id || '',
    date_from: props.filters.date_from || '',
    date_to: props.filters.date_to || '',
})

const applyFilters = () => {
    const query = Object.fromEntries(Object.entries(filterForm).filter(([, value]) => value !== ''))
    router.get('/billing', query, { preserveState: true, replace: true })
}

const clearFilters = () => {
    Object.keys(filterForm).forEach(key => filterForm[key] = '')
    applyFilters()
}

// --- Create invoice ---
const create = reactive({ show: false })
const invoiceForm = useForm({
    number: '',
    url: '',
    issued_at: new Date().toISOString().substring(0, 10),
    note: '',
    hourly_rate: '',
    work_report_ids: [],
})

// Total of the selection, as it will be invoiced with an optional override rate.
const invoicedTotal = (rate) => rate === '' || rate === null
    ? sumAmount(selectedReports.value)
    : Math.round(sumMinutes(selectedReports.value) / 60 * Number(rate) * 100) / 100

const openCreate = () => {
    invoiceForm.clearErrors()
    create.show = true
}

const submitCreate = () => {
    invoiceForm.work_report_ids = selectedIds.value
    invoiceForm.post('/invoices', {
        onSuccess: () => {
            invoiceForm.reset()
            create.show = false
        },
    })
}

// --- Attach to existing invoice ---
const attach = reactive({ show: false })
const attachForm = useForm({ invoice_id: null, hourly_rate: '', work_report_ids: [] })

const submitAttach = () => {
    attachForm.work_report_ids = selectedIds.value
    attachForm.post(`/invoices/${attachForm.invoice_id}/attach`, {
        onSuccess: () => {
            attachForm.reset()
            attach.show = false
        },
    })
}
</script>
