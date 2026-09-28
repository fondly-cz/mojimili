<template>
    <div>
        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Termín projektu</label>
        <input v-model="form.due_date" type="date" :class="inputClass">
        <p v-if="form.errors.due_date" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.due_date }}</p>
    </div>

    <div>
        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Rozpočet (Kč bez DPH)</label>
        <input v-model="form.budget" type="number" min="0" step="0.01" :class="inputClass" placeholder="Např. 150000">
        <p v-if="form.errors.budget" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.budget }}</p>
    </div>

    <div>
        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Časový rozpočet (h)</label>
        <input v-model="budgetHours" type="number" min="0" step="0.25" :class="inputClass" placeholder="Např. 100">
        <p v-if="form.errors.budget_minutes" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.budget_minutes }}</p>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    form: Object,
})

const inputClass = 'block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all'

// Stored in minutes like the work reports, edited in hours.
const budgetHours = computed({
    get: () => (props.form.budget_minutes === '' || props.form.budget_minutes == null ? '' : props.form.budget_minutes / 60),
    set: (value) => {
        // eslint-disable-next-line vue/no-mutating-props -- the parent's Inertia form is edited in place.
        props.form.budget_minutes = value === '' || value == null ? '' : Math.round(Number(value) * 60)
    },
})
</script>
