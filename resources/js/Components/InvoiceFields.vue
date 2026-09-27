<template>
    <div class="space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
            <div>
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Číslo / označení <span class="text-red-500">*</span></label>
                <input v-model="number" type="text" required :class="inputClass" placeholder="Např. 2026-09-karel">
                <p v-if="errors.number" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ errors.number }}</p>
            </div>
            <div>
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Datum vystavení</label>
                <input v-model="issuedAt" type="date" :class="inputClass">
                <p v-if="errors.issued_at" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ errors.issued_at }}</p>
            </div>
        </div>
        <div>
            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Odkaz na fakturu</label>
            <input v-model="url" type="url" :class="inputClass" placeholder="https://app.fakturoid.cz/...">
            <p v-if="errors.url" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ errors.url }}</p>
        </div>
        <div>
            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Poznámka</label>
            <textarea v-model="note" rows="3" :class="[inputClass, 'resize-none']"></textarea>
            <p v-if="errors.note" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ errors.note }}</p>
        </div>
        <p v-if="errors.work_report_ids" class="text-xs text-red-500 font-bold ml-1">{{ errors.work_report_ids }}</p>
    </div>
</template>

<script setup>
defineProps({
    errors: Object,
})

const number = defineModel('number')
const issuedAt = defineModel('issuedAt')
const url = defineModel('url')
const note = defineModel('note')

const inputClass = 'block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all'
</script>
