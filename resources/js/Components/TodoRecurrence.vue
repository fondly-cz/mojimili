<template>
    <div class="mt-3">
        <button
            type="button"
            @click="toggleOpen"
            class="text-[10px] font-black uppercase tracking-widest transition-colors"
            :class="todo.recurrence_frequency || open ? 'text-brand-primary-from' : 'text-gray-300 hover:text-brand-primary-from'"
            :title="todo.recurrence_frequency ? 'Upravit opakování' : 'Nastavit opakování'"
        >
            ↻ {{ todo.recurrence_frequency ? summary : 'Opakovat' }}
        </button>

        <form
            v-if="open"
            @submit.prevent="save"
            class="mt-3 grid grid-cols-2 md:grid-cols-12 gap-3 items-end rounded-2xl border border-gray-100 bg-gray-50/50 p-4"
        >
            <div class="md:col-span-3">
                <label :class="labelClass">Opakovat</label>
                <select v-model="form.recurrence_frequency" :class="inputClass">
                    <option v-for="option in frequencies" :key="option.value" :value="option.value">{{ option.label }}</option>
                </select>
            </div>
            <div class="md:col-span-3">
                <label :class="labelClass">Každý</label>
                <div class="flex items-center gap-2">
                    <input v-model.number="form.recurrence_interval" type="number" min="1" max="365" :class="inputClass">
                    <span class="text-[10px] font-bold text-gray-400 whitespace-nowrap">{{ unit }}</span>
                </div>
            </div>
            <div class="md:col-span-3">
                <label :class="labelClass">Ukončení</label>
                <select v-model="endMode" :class="inputClass">
                    <option value="never">Bez ukončení</option>
                    <option value="count">Po počtu opakování</option>
                    <option value="date">K datu</option>
                </select>
            </div>
            <div v-if="endMode === 'count'" class="md:col-span-3">
                <label :class="labelClass">Ještě opakovat (×)</label>
                <input v-model.number="form.recurrence_remaining" type="number" min="0" max="1000" :class="inputClass">
            </div>
            <div v-if="endMode === 'date'" class="md:col-span-3">
                <label :class="labelClass">Do</label>
                <input v-model="form.recurrence_ends_on" type="date" :class="inputClass">
            </div>

            <div class="col-span-2 md:col-span-12 flex flex-wrap items-center gap-x-6 gap-y-2">
                <label v-if="form.recurrence_frequency === 'daily'" class="inline-flex items-center gap-2 text-[11px] font-bold text-gray-500 cursor-pointer">
                    <input v-model="form.recurrence_working_days_only" type="checkbox" class="rounded border-gray-300 text-brand-primary-from focus:ring-brand-primary-from">
                    Jen pracovní dny
                </label>
                <label class="inline-flex items-center gap-2 text-[11px] font-bold text-gray-500 cursor-pointer">
                    <input v-model="form.recurrence_copy_description" type="checkbox" class="rounded border-gray-300 text-brand-primary-from focus:ring-brand-primary-from">
                    Kopírovat popis
                </label>
            </div>

            <p class="col-span-2 md:col-span-12 text-[11px] font-medium text-gray-400 leading-relaxed">
                Po dokončení úkolu se vytvoří jeho další výskyt se stejným řešitelem a otevřenými podúkoly.
                Termín se posune od {{ todo.due_date ? 'aktuálního termínu' : 'dneška' }} o zvolené období.
            </p>

            <p v-if="firstError" class="col-span-2 md:col-span-12 text-xs text-red-500 font-bold">{{ firstError }}</p>

            <div class="col-span-2 md:col-span-12 flex flex-wrap items-center gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="brand-gradient px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest text-white disabled:opacity-50"
                >
                    Uložit
                </button>
                <button
                    v-if="todo.recurrence_frequency"
                    type="button"
                    @click="stop"
                    class="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-red-500 transition-colors"
                >
                    Zrušit opakování
                </button>
                <button type="button" @click="open = false" class="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-gray-600">
                    Zavřít
                </button>
            </div>
        </form>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'

const props = defineProps({
    todo: Object,
})

const frequencies = [
    { value: 'daily', label: 'Denně', unit: ['den', 'dny', 'dní'] },
    { value: 'weekly', label: 'Týdně', unit: ['týden', 'týdny', 'týdnů'] },
    { value: 'monthly', label: 'Měsíčně', unit: ['měsíc', 'měsíce', 'měsíců'] },
    { value: 'quarterly', label: 'Čtvrtletně', unit: ['čtvrtletí', 'čtvrtletí', 'čtvrtletí'] },
    { value: 'yearly', label: 'Ročně', unit: ['rok', 'roky', 'let'] },
]

const labelClass = 'block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1'
const inputClass = 'block w-full px-2.5 py-2 bg-white border-gray-100 rounded-xl text-xs font-semibold text-gray-700 focus:ring-brand-primary-from focus:border-brand-primary-from'

const open = ref(false)
const endMode = ref('never')

const form = useForm({
    recurrence_frequency: 'weekly',
    recurrence_interval: 1,
    recurrence_working_days_only: false,
    recurrence_ends_on: null,
    recurrence_remaining: null,
    recurrence_copy_description: true,
})

const firstError = computed(() => Object.values(form.errors)[0])

const plural = (count, forms) => (count === 1 ? forms[0] : count >= 2 && count <= 4 ? forms[1] : forms[2])

const unit = computed(() => {
    const frequency = frequencies.find(f => f.value === form.recurrence_frequency)
    return frequency ? plural(form.recurrence_interval || 1, frequency.unit) : ''
})

const formatDate = (value) => new Date(value.substring(0, 10)).toLocaleDateString('cs-CZ')

const hasRemaining = (todo) => todo.recurrence_remaining !== null && todo.recurrence_remaining !== undefined

const summary = computed(() => {
    const todo = props.todo
    const frequency = frequencies.find(f => f.value === todo.recurrence_frequency)
    if (!frequency) return ''

    let text = todo.recurrence_interval > 1
        ? `${frequency.label} (každý ${todo.recurrence_interval}.)`
        : frequency.label
    if (todo.recurrence_frequency === 'daily' && todo.recurrence_working_days_only) text += ', prac. dny'
    if (hasRemaining(todo)) text += ` · ještě ${todo.recurrence_remaining}×`
    if (todo.recurrence_ends_on) text += ` · do ${formatDate(todo.recurrence_ends_on)}`

    return text
})

const toggleOpen = () => {
    open.value = !open.value
    if (!open.value) return

    const todo = props.todo
    form.clearErrors()
    form.recurrence_frequency = todo.recurrence_frequency || 'weekly'
    form.recurrence_interval = todo.recurrence_interval || 1
    form.recurrence_working_days_only = !!todo.recurrence_working_days_only
    form.recurrence_ends_on = todo.recurrence_ends_on ? todo.recurrence_ends_on.substring(0, 10) : null
    form.recurrence_remaining = todo.recurrence_remaining ?? null
    form.recurrence_copy_description = todo.recurrence_copy_description ?? true
    endMode.value = todo.recurrence_ends_on ? 'date' : (hasRemaining(todo) ? 'count' : 'never')
}

const save = () => {
    form
        .transform(data => ({
            ...data,
            recurrence_ends_on: endMode.value === 'date' ? data.recurrence_ends_on : null,
            recurrence_remaining: endMode.value === 'count' ? (data.recurrence_remaining ?? 0) : null,
        }))
        .patch(`/todos/${props.todo.id}`, {
            preserveScroll: true,
            onSuccess: () => { open.value = false },
        })
}

const stop = () => {
    router.patch(`/todos/${props.todo.id}`, { recurrence_frequency: null }, {
        preserveScroll: true,
        onSuccess: () => { open.value = false },
    })
}
</script>
