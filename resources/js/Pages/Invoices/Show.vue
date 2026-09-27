<template>
    <Layout>
        <Breadcrumbs
            :items="[
                { label: 'Nástěnka', href: '/' },
                { label: 'Faktury', href: '/invoices' },
            ]"
        />

        <div class="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-4xl font-extrabold text-gray-900 font-heading tracking-tight">{{ invoice.number }}</h1>
                    <span class="inline-flex items-center px-4 py-1.5 text-[10px] font-black uppercase tracking-wider rounded-full shadow-sm bg-green-500 text-white">
                        Vyfakturováno
                    </span>
                </div>
                <p class="text-gray-500 mt-2 font-medium">
                    Vystaveno {{ formatDate(invoice.issued_at) }}
                    <span v-if="invoice.user"> · {{ invoice.user.name }}</span>
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <a
                    v-if="invoice.url"
                    :href="invoice.url"
                    target="_blank"
                    rel="noopener"
                    class="inline-flex items-center gap-2 brand-gradient px-8 py-4 rounded-full font-bold text-white shadow-brand hover:shadow-brand-lg transition-all hover:-translate-y-1 font-heading"
                >
                    Otevřít fakturu ↗
                </a>
                <button
                    @click="openEdit"
                    class="inline-flex items-center gap-2 bg-white border-2 border-gray-100 px-6 py-4 rounded-full font-bold text-gray-400 hover:text-gray-600 hover:border-gray-200 transition-all font-heading uppercase tracking-widest text-[10px]"
                >
                    Upravit
                </button>
                <button
                    @click="confirmDelete = true"
                    class="inline-flex items-center gap-2 bg-white border-2 border-gray-100 px-6 py-4 rounded-full font-bold text-gray-400 hover:text-red-500 hover:border-red-200 transition-all font-heading uppercase tracking-widest text-[10px]"
                >
                    Smazat
                </button>
            </div>
        </div>

        <p v-if="invoice.note" class="mb-8 max-w-4xl text-sm font-medium leading-relaxed text-gray-500 bg-white rounded-[2rem] border border-gray-50 p-8 shadow-sm whitespace-pre-line">
            {{ invoice.note }}
        </p>

        <div class="mb-8 grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div class="bg-white rounded-[2rem] border border-gray-50 p-8 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Výkazy</p>
                <p class="mt-2 text-3xl font-black text-gray-900 font-heading">{{ invoice.work_reports.length }}</p>
            </div>
            <div class="bg-white rounded-[2rem] border border-gray-50 p-8 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Čas</p>
                <p class="mt-2 text-3xl font-black text-gray-900 font-heading">{{ formatMinutes(sumMinutes(invoice.work_reports)) }}</p>
            </div>
            <div class="bg-white rounded-[2rem] border border-gray-50 p-8 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Celkem bez DPH</p>
                <p class="mt-2 text-3xl font-black brand-text-gradient font-heading">{{ formatCurrency(sumAmount(invoice.work_reports)) }}</p>
            </div>
        </div>

        <div v-if="invoice.work_reports.length === 0" class="bg-white rounded-[2.5rem] border border-gray-50 p-16 text-center shadow-sm">
            <p class="text-sm font-bold text-gray-400">Faktura neobsahuje žádné výkazy.</p>
        </div>

        <div v-else class="space-y-8">
            <div
                v-for="group in groups"
                :key="group.project.id"
                class="bg-white rounded-[2.5rem] border border-gray-50 shadow-sm overflow-hidden"
            >
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-50 px-10 py-6">
                    <div>
                        <Link :href="`/projects/${group.project.id}`" class="text-lg font-black text-gray-900 font-heading hover:text-brand-primary-from transition-colors">
                            {{ group.project.name }}
                        </Link>
                        <p class="mt-1 text-xs font-semibold text-gray-400">{{ group.project.company?.name || 'Bez firmy' }}</p>
                    </div>
                    <p class="text-sm font-black text-gray-900 font-heading">
                        {{ formatMinutes(sumMinutes(group.reports)) }} · {{ formatCurrency(sumAmount(group.reports)) }}
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-gray-50 text-left text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">
                                <th class="px-10 py-4">Úkol</th>
                                <th class="px-4 py-4">Datum</th>
                                <th class="px-4 py-4">Kdo</th>
                                <th class="px-4 py-4 text-right">Čas</th>
                                <th class="px-4 py-4 text-right">Sazba</th>
                                <th class="px-4 py-4 text-right">Částka</th>
                                <th class="px-10 py-4"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50">
                            <tr v-for="report in group.reports" :key="report.id" class="font-semibold text-gray-600 group">
                                <td class="px-10 py-4">
                                    <div class="font-bold text-gray-900">{{ report.todo.name }}</div>
                                    <div class="text-xs text-gray-400">
                                        {{ report.todo.todolist.name }}<span v-if="report.description"> · {{ report.description }}</span>
                                    </div>
                                </td>
                                <td class="px-4 py-4 whitespace-nowrap">{{ formatDate(report.date) }}</td>
                                <td class="px-4 py-4 whitespace-nowrap">{{ report.user?.name || '—' }}</td>
                                <td class="px-4 py-4 text-right whitespace-nowrap">{{ formatMinutes(report.minutes) }}</td>
                                <td class="px-4 py-4 text-right whitespace-nowrap">{{ formatCurrency(report.hourly_rate) }}/h</td>
                                <td class="px-4 py-4 text-right whitespace-nowrap font-black text-gray-900">{{ formatCurrency(reportAmount(report)) }}</td>
                                <td class="px-10 py-4 text-right whitespace-nowrap">
                                    <button
                                        type="button"
                                        @click="detach(report)"
                                        class="text-[10px] font-black uppercase tracking-widest text-gray-300 hover:text-red-500 transition-colors opacity-0 group-hover:opacity-100"
                                        title="Vrátit výkaz mezi nevyfakturované"
                                    >
                                        Odebrat
                                    </button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <Modal :show="edit" title="Upravit fakturu" @close="edit = false">
            <template #content>
                <InvoiceFields
                    v-model:number="form.number"
                    v-model:issued-at="form.issued_at"
                    v-model:url="form.url"
                    v-model:note="form.note"
                    :errors="form.errors"
                />
            </template>
            <template #footer>
                <button
                    @click="submit"
                    :disabled="form.processing"
                    class="brand-gradient px-8 py-3 rounded-full font-bold text-white shadow-brand transition-all font-heading disabled:opacity-50"
                >
                    Uložit
                </button>
                <button @click="edit = false" class="px-6 py-3 rounded-full text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-gray-600 transition-colors">
                    Zrušit
                </button>
            </template>
        </Modal>

        <ConfirmModal
            :show="confirmDelete"
            title="Smazat fakturu"
            :message="`Opravdu chcete smazat fakturu ${invoice.number}? Všechny její výkazy se vrátí mezi nevyfakturované.`"
            @close="confirmDelete = false"
            @confirm="destroy"
        />
    </Layout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import Layout from '../../Components/Layout.vue'
import Breadcrumbs from '../../Components/Breadcrumbs.vue'
import Modal from '../../Components/Modal.vue'
import ConfirmModal from '../../Components/ConfirmModal.vue'
import InvoiceFields from '../../Components/InvoiceFields.vue'
import { formatCurrency, formatDate, formatMinutes, reportAmount } from '../../utils/billing'

const props = defineProps({
    invoice: Object,
})

const sumMinutes = (reports) => reports.reduce((sum, r) => sum + r.minutes, 0)
const sumAmount = (reports) => reports.reduce((sum, r) => sum + reportAmount(r), 0)

const groups = computed(() => {
    const map = new Map()

    for (const report of props.invoice.work_reports) {
        const project = report.todo.todolist.project
        if (!map.has(project.id)) {
            map.set(project.id, { project, reports: [] })
        }
        map.get(project.id).reports.push(report)
    }

    return [...map.values()]
})

// --- Edit ---
const edit = ref(false)
const form = useForm({ number: '', url: '', issued_at: '', note: '' })

const openEdit = () => {
    form.number = props.invoice.number
    form.url = props.invoice.url || ''
    form.issued_at = props.invoice.issued_at ? props.invoice.issued_at.substring(0, 10) : ''
    form.note = props.invoice.note || ''
    form.clearErrors()
    edit.value = true
}

const submit = () => {
    form.put(`/invoices/${props.invoice.id}`, {
        preserveScroll: true,
        onSuccess: () => edit.value = false,
    })
}

// --- Detach / delete ---
const detach = (report) => {
    router.post(`/invoices/${props.invoice.id}/detach`, { work_report_ids: [report.id] }, { preserveScroll: true })
}

const confirmDelete = ref(false)

const destroy = () => {
    router.delete(`/invoices/${props.invoice.id}`)
}
</script>
