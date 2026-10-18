<template>
    <section class="mb-8 bg-white rounded-[2rem] border border-gray-50 p-8 shadow-sm">
        <div class="flex items-center justify-between gap-4 mb-6">
            <h2 class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Výkazy ({{ filtered.length }})</h2>
            <button
                v-if="hasFilters"
                type="button"
                @click="reset"
                class="text-[10px] font-black uppercase tracking-widest text-gray-300 hover:text-brand-primary-from transition-colors"
            >
                Zrušit filtry
            </button>
        </div>

        <div class="mb-6 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
            <input v-model="filters.search" type="text" placeholder="Hledat v úkolu, seznamu, popisu…" :class="field">
            <select v-model="filters.listId" :class="field">
                <option value="">Všechny seznamy</option>
                <option v-for="list in todolists" :key="list.id" :value="list.id">{{ list.name }}</option>
            </select>
            <select v-model="filters.userId" :class="field">
                <option value="">Všichni lidé</option>
                <option v-for="person in people" :key="person.id" :value="person.id">{{ person.name }}</option>
            </select>
            <label class="flex items-center gap-2 text-xs font-bold text-gray-400">
                Od <input v-model="filters.from" type="date" :class="field">
            </label>
            <label class="flex items-center gap-2 text-xs font-bold text-gray-400">
                Do <input v-model="filters.to" type="date" :class="field">
            </label>
            <select v-if="isStaff" v-model="filters.invoiced" :class="field">
                <option value="">Vyfakturované i ne</option>
                <option value="yes">Vyfakturováno</option>
                <option value="no">Nevyfakturováno</option>
            </select>
        </div>

        <p v-if="!rows.length" class="text-sm font-bold text-gray-300">V projektu zatím není vykázaný žádný čas.</p>
        <p v-else-if="!filtered.length" class="text-sm font-bold text-gray-300">Žádný výkaz neodpovídá filtrům.</p>

        <div v-else class="overflow-x-auto">
            <table class="w-full text-xs">
                <thead>
                    <tr class="text-left text-[9px] font-black uppercase tracking-widest text-gray-400">
                        <th class="py-1.5 pr-3">Datum</th>
                        <th class="py-1.5 pr-3">Úkol</th>
                        <th class="py-1.5 pr-3">Kdo</th>
                        <th class="py-1.5 pr-3">Popis</th>
                        <th class="py-1.5 pr-3 text-right">Čas</th>
                        <th v-if="isStaff" class="py-1.5 pr-3 text-right">Částka</th>
                        <th v-if="isStaff" class="py-1.5">Faktura</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="row in filtered" :key="row.report.id" class="font-semibold text-gray-600">
                        <td class="py-2 pr-3 whitespace-nowrap">{{ formatDate(row.report.date) }}</td>
                        <td class="py-2 pr-3">
                            <Link :href="`/todos/${row.todoId}`" class="font-black text-gray-800 hover:text-brand-primary-from transition-colors">{{ row.todoName }}</Link>
                            <span class="block text-[10px] text-gray-400">{{ row.listName }}</span>
                        </td>
                        <td class="py-2 pr-3 whitespace-nowrap">{{ row.report.user?.name || '—' }}</td>
                        <td class="py-2 pr-3 text-gray-400">{{ row.report.description || '' }}</td>
                        <td class="py-2 pr-3 text-right whitespace-nowrap">{{ formatMinutes(row.report.minutes) }}</td>
                        <td v-if="isStaff" class="py-2 pr-3 text-right whitespace-nowrap font-black text-gray-900">{{ formatCurrency(reportAmount(row.report)) }}</td>
                        <td v-if="isStaff" class="py-2 whitespace-nowrap">
                            <Link
                                v-if="row.report.invoice"
                                :href="`/invoices/${row.report.invoice.id}`"
                                class="px-2 py-0.5 rounded-lg bg-green-50 border border-green-100 text-[9px] font-black text-green-600 hover:bg-green-100 transition-colors"
                            >
                                {{ row.report.invoice.number }}
                            </Link>
                            <span v-else class="px-2 py-0.5 rounded-lg bg-amber-50 border border-amber-100 text-[9px] font-black text-amber-600">Nevyfakturováno</span>
                        </td>
                    </tr>
                </tbody>
                <tfoot>
                    <tr class="font-black text-gray-900 border-t border-gray-100">
                        <td class="pt-3 pr-3" colspan="4">Celkem</td>
                        <td class="pt-3 pr-3 text-right whitespace-nowrap">{{ formatMinutes(totalMinutes) }}</td>
                        <td v-if="isStaff" class="pt-3 pr-3 text-right whitespace-nowrap">{{ formatCurrency(totalAmount) }}</td>
                        <td v-if="isStaff"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>
</template>

<script setup>
import { computed, reactive } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import { formatCurrency, formatDate, formatMinutes, reportAmount } from '../utils/billing'

const props = defineProps({
    todolists: Array,
})

const isStaff = computed(() => ['admin', 'manager'].includes(usePage().props.auth.user?.role))

const field = 'block w-full px-4 py-2.5 bg-gray-50 border-gray-100 rounded-xl text-sm font-semibold text-gray-700 focus:ring-brand-primary-from focus:border-brand-primary-from'

const emptyFilters = () => ({ search: '', listId: '', userId: '', from: '', to: '', invoiced: '' })
const filters = reactive(emptyFilters())
const reset = () => Object.assign(filters, emptyFilters())
const hasFilters = computed(() => Object.values(filters).some(value => value !== ''))

// Every report logged on a todo of the project, newest first.
const rows = computed(() => props.todolists
    .flatMap(list => list.todos.flatMap(todo => (todo.work_reports || []).map(report => ({
        report,
        todoId: todo.id,
        todoName: todo.name,
        listId: list.id,
        listName: list.name,
    }))))
    .sort((a, b) => String(b.report.date).localeCompare(String(a.report.date)) || b.report.id - a.report.id))

const people = computed(() => [...new Map(rows.value
    .map(row => row.report.user)
    .filter(Boolean)
    .map(user => [user.id, user])).values()])

const filtered = computed(() => {
    const search = filters.search.trim().toLowerCase()

    return rows.value.filter(({ report, todoName, listId, listName }) => {
        const day = String(report.date).slice(0, 10)

        return (!search || [todoName, listName, report.description].some(text => (text || '').toLowerCase().includes(search)))
            && (filters.listId === '' || listId === filters.listId)
            && (filters.userId === '' || report.user?.id === filters.userId)
            && (!filters.from || day >= filters.from)
            && (!filters.to || day <= filters.to)
            && (filters.invoiced === '' || (filters.invoiced === 'yes') === !!report.invoice_id)
    })
})

const totalMinutes = computed(() => filtered.value.reduce((sum, row) => sum + row.report.minutes, 0))
const totalAmount = computed(() => filtered.value.reduce((sum, row) => sum + reportAmount(row.report), 0))
</script>
