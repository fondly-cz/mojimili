<template>
    <div class="mt-3">
        <button
            type="button"
            @click="open = !open"
            class="inline-flex flex-wrap items-center gap-2 text-[10px] font-black uppercase tracking-widest transition-colors"
            :class="open ? 'text-brand-primary-from' : 'text-gray-300 hover:text-brand-primary-from'"
        >
            <span v-if="reports.length === 0">+ Vykázat čas</span>
            <template v-else>
                <span>{{ formatMinutes(totalMinutes) }} · {{ formatCurrency(totalAmount) }}</span>
                <span class="px-2 py-0.5 rounded-lg border text-[8px]" :class="billingState.classes">
                    {{ billingState.label }}
                </span>
            </template>
        </button>

        <div v-if="open" class="mt-3 rounded-2xl border border-gray-100 bg-gray-50/50 p-4 space-y-3">
            <p v-if="$page.props.errors.work_report" class="text-xs text-red-500 font-bold">{{ $page.props.errors.work_report }}</p>

            <div v-if="reports.length > 0" class="overflow-x-auto">
                <table class="w-full text-xs">
                    <thead>
                        <tr class="text-left text-[9px] font-black uppercase tracking-widest text-gray-400">
                            <th class="py-1.5 pr-3">Datum</th>
                            <th class="py-1.5 pr-3">Kdo</th>
                            <th class="py-1.5 pr-3">Popis</th>
                            <th class="py-1.5 pr-3 text-right">Čas</th>
                            <th class="py-1.5 pr-3 text-right">Sazba</th>
                            <th class="py-1.5 pr-3 text-right">Částka</th>
                            <th class="py-1.5 pr-3">Faktura</th>
                            <th class="py-1.5"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr v-for="report in reports" :key="report.id" class="font-semibold text-gray-600">
                            <td class="py-2 pr-3 whitespace-nowrap">
                                {{ formatDate(report.date) }}
                                <span v-if="report.started_at" class="block text-[10px] text-gray-400">{{ formatTimeRange(report) }}</span>
                            </td>
                            <td class="py-2 pr-3 whitespace-nowrap">{{ report.user?.name || '—' }}</td>
                            <td class="py-2 pr-3 text-gray-400">{{ report.description || '' }}</td>
                            <td class="py-2 pr-3 text-right whitespace-nowrap">{{ formatMinutes(report.minutes) }}</td>
                            <td class="py-2 pr-3 text-right whitespace-nowrap">{{ formatCurrency(report.hourly_rate) }}/h</td>
                            <td class="py-2 pr-3 text-right whitespace-nowrap font-black text-gray-900">{{ formatCurrency(reportAmount(report)) }}</td>
                            <td class="py-2 pr-3 whitespace-nowrap">
                                <Link
                                    v-if="report.invoice"
                                    :href="`/invoices/${report.invoice.id}`"
                                    class="px-2 py-0.5 rounded-lg bg-green-50 border border-green-100 text-[9px] font-black text-green-600 hover:bg-green-100 transition-colors"
                                >
                                    {{ report.invoice.number }}
                                </Link>
                                <span v-else class="px-2 py-0.5 rounded-lg bg-amber-50 border border-amber-100 text-[9px] font-black text-amber-600">
                                    Nevyfakturováno
                                </span>
                            </td>
                            <td class="py-2 text-right whitespace-nowrap">
                                <template v-if="!report.invoice_id">
                                    <button type="button" @click="startEdit(report)" class="text-[9px] font-black uppercase tracking-widest text-gray-300 hover:text-brand-primary-from transition-colors">Upravit</button>
                                    <button type="button" @click="confirmRemove(report)" class="ml-2 text-[9px] font-black uppercase tracking-widest text-gray-300 hover:text-red-500 transition-colors">Smazat</button>
                                </template>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <form @submit.prevent="submit" class="grid grid-cols-2 md:grid-cols-12 gap-2 items-end">
                <div class="md:col-span-2">
                    <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Datum</label>
                    <input v-model="form.date" type="date" required :class="inputClass">
                </div>
                <div class="md:col-span-1">
                    <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Od</label>
                    <input v-model="timeFrom" @input="onRangeInput" type="time" :class="inputClass">
                </div>
                <div class="md:col-span-1">
                    <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Do</label>
                    <input v-model="timeTo" @input="onRangeInput" type="time" :class="inputClass">
                </div>
                <div class="md:col-span-1">
                    <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Čas</label>
                    <input v-model="duration" @input="onDurationInput" type="text" required placeholder="1:30" :class="inputClass">
                </div>
                <div class="md:col-span-1">
                    <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Kč/h</label>
                    <input v-model="form.hourly_rate" type="number" min="0" step="0.01" :placeholder="rateFor(form.user_id)" :class="inputClass">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Kdo</label>
                    <select v-model="form.user_id" :class="inputClass">
                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                    </select>
                </div>
                <div class="col-span-2 md:col-span-3">
                    <label class="block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1">Popis</label>
                    <input v-model="form.description" type="text" placeholder="Co se dělalo" :class="inputClass">
                </div>
                <div class="col-span-2 md:col-span-1">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full brand-gradient px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest text-white disabled:opacity-50"
                    >
                        {{ editingId ? 'Uložit' : 'Přidat' }}
                    </button>
                </div>
                <p v-if="durationError || firstError" class="col-span-2 md:col-span-12 text-xs text-red-500 font-bold">
                    {{ durationError || firstError }}
                </p>
                <p v-if="editingId" class="col-span-2 md:col-span-12">
                    <button type="button" @click="resetForm" class="text-[9px] font-black uppercase tracking-widest text-gray-400 hover:text-gray-600">Zrušit úpravu</button>
                </p>
            </form>
        </div>

        <ConfirmModal
            :show="removing !== null"
            title="Smazat výkaz"
            :message="removing ? `Opravdu chcete smazat výkaz ${formatMinutes(removing.minutes)} z ${formatDate(removing.date)}?` : ''"
            @close="removing = null"
            @confirm="remove"
        />
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import ConfirmModal from './ConfirmModal.vue'
import { addMinutesToTime, formatClock, formatCurrency, formatDate, formatMinutes, formatTimeRange, minutesBetween, parseDuration, reportAmount } from '../utils/billing'

const props = defineProps({
    todo: Object,
    users: Array,
    defaultRate: [String, Number],
    userRates: { type: Object, default: () => ({}) },
})

// Mirrors Project::rateFor(): the person's rate in the project, then the project default.
const rateFor = (userId) => props.userRates[userId] ?? props.defaultRate ?? '0'

const page = usePage()

const inputClass = 'block w-full px-3 py-2 bg-white border-gray-100 rounded-xl text-xs font-semibold text-gray-700 focus:ring-brand-primary-from focus:border-brand-primary-from'

const open = ref(false)
const reports = computed(() => props.todo.work_reports || [])
const totalMinutes = computed(() => reports.value.reduce((sum, r) => sum + r.minutes, 0))
const totalAmount = computed(() => reports.value.reduce((sum, r) => sum + reportAmount(r), 0))

const billingState = computed(() => {
    const invoiced = reports.value.filter(r => r.invoice_id).length

    if (invoiced === reports.value.length) {
        return { label: 'Vyfakturováno', classes: 'bg-green-50 border-green-100 text-green-600' }
    }
    if (invoiced > 0) {
        return { label: 'Částečně vyfakturováno', classes: 'bg-blue-50 border-blue-100 text-blue-600' }
    }
    return { label: 'K fakturaci', classes: 'bg-amber-50 border-amber-100 text-amber-600' }
})

const today = () => new Date().toISOString().substring(0, 10)

const nextDay = (date) => {
    const day = new Date(`${date}T00:00:00Z`)
    day.setUTCDate(day.getUTCDate() + 1)
    return day.toISOString().substring(0, 10)
}

const editingId = ref(null)
const duration = ref('')
const durationError = ref('')
// Optional from–to range ("HH:MM"); it keeps the duration in sync both ways.
const timeFrom = ref('')
const timeTo = ref('')

const onRangeInput = () => {
    const minutes = minutesBetween(timeFrom.value, timeTo.value)
    if (minutes) duration.value = formatClock(minutes)
}

const onDurationInput = () => {
    const minutes = parseDuration(duration.value)
    if (minutes && timeFrom.value) timeTo.value = addMinutesToTime(timeFrom.value, minutes)
}

const form = useForm({
    date: today(),
    minutes: null,
    started_at: null,
    ended_at: null,
    hourly_rate: '',
    description: '',
    user_id: page.props.auth?.user?.id ?? null,
})

const firstError = computed(() => Object.values(form.errors)[0])

const resetForm = () => {
    editingId.value = null
    duration.value = ''
    durationError.value = ''
    timeFrom.value = ''
    timeTo.value = ''
    form.reset()
    form.clearErrors()
}

const startEdit = (report) => {
    editingId.value = report.id
    form.date = report.date.substring(0, 10)
    form.hourly_rate = report.hourly_rate
    form.description = report.description || ''
    form.user_id = report.user_id
    duration.value = formatClock(report.minutes)
    timeFrom.value = report.started_at ? report.started_at.substring(11, 16) : ''
    timeTo.value = report.ended_at ? report.ended_at.substring(11, 16) : ''
}

const submit = () => {
    const minutes = parseDuration(duration.value)

    if (!minutes) {
        durationError.value = 'Zadejte čas jako minuty (90), hodiny a minuty (1:30) nebo hodiny (1,5).'
        return
    }

    if (Boolean(timeFrom.value) !== Boolean(timeTo.value)) {
        durationError.value = 'Vyplňte čas od i do, nebo nechte obojí prázdné.'
        return
    }

    durationError.value = ''
    form.minutes = minutes

    // The server derives minutes from the range; an end before the start means past midnight.
    if (timeFrom.value) {
        form.started_at = `${form.date} ${timeFrom.value}`
        form.ended_at = `${timeTo.value <= timeFrom.value ? nextDay(form.date) : form.date} ${timeTo.value}`
    } else {
        form.started_at = null
        form.ended_at = null
    }

    // An empty rate lets the server fall back to the project's default.
    form.transform(data => ({ ...data, hourly_rate: data.hourly_rate === '' ? null : data.hourly_rate }))

    const options = { preserveScroll: true, onSuccess: resetForm }

    if (editingId.value) {
        form.patch(`/work-reports/${editingId.value}`, options)
    } else {
        form.post(`/todos/${props.todo.id}/work-reports`, options)
    }
}

const removing = ref(null)

const confirmRemove = (report) => {
    removing.value = report
}

const remove = () => {
    router.delete(`/work-reports/${removing.value.id}`, {
        preserveScroll: true,
        onFinish: () => removing.value = null,
    })
}
</script>
