<template>
    <div class="space-y-3">
        <div class="flex flex-wrap gap-2">
            <span
                v-for="label in todo.labels"
                :key="label.id"
                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-[10px] font-black text-white"
                :style="{ backgroundColor: label.color }"
            >
                {{ label.name }}
                <button type="button" @click="save(selectedIds.filter(id => id !== label.id))" class="opacity-70 hover:opacity-100" :aria-label="`Odebrat štítek ${label.name}`">×</button>
            </span>
            <span v-if="!todo.labels.length" class="text-xs font-bold text-gray-300">Bez štítků</span>
        </div>

        <select
            v-if="available.length"
            value=""
            @change="e => { if (e.target.value) save([...selectedIds, Number(e.target.value)]); e.target.value = '' }"
            :class="inputClass"
        >
            <option value="">+ Přidat štítek</option>
            <option v-for="label in available" :key="label.id" :value="label.id">{{ label.name }}</option>
        </select>

        <form v-if="creating" @submit.prevent="create" class="flex items-center gap-2">
            <input v-model="form.color" type="color" class="h-8 w-8 shrink-0 cursor-pointer rounded-lg border-none bg-transparent p-0" title="Barva">
            <input v-model="form.name" type="text" placeholder="Název štítku" :class="inputClass">
            <button type="submit" :disabled="form.processing" class="text-[10px] font-black uppercase tracking-widest text-brand-primary-from disabled:opacity-50">Uložit</button>
        </form>
        <button v-else type="button" @click="creating = true" class="text-[10px] font-black uppercase tracking-widest text-gray-300 hover:text-brand-primary-from transition-colors">
            + Nový štítek
        </button>
        <p v-if="form.errors.name" class="text-xs text-red-500 font-bold">{{ form.errors.name }}</p>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'

const props = defineProps({
    todo: Object,
    labels: Array,
    inputClass: String,
})

const selectedIds = computed(() => props.todo.labels.map(label => label.id))
const available = computed(() => props.labels.filter(label => !selectedIds.value.includes(label.id)))

const save = (labelIds) => router.patch(`/todos/${props.todo.id}`, { label_ids: labelIds }, { preserveScroll: true })

const creating = ref(false)
const form = useForm({ name: '', color: '#77787a' })

// A new label is created first, then attached once the page reloads with its id.
const create = () => {
    const name = form.name.trim()
    form.post('/labels', {
        preserveScroll: true,
        onSuccess: (page) => {
            const label = page.props.labels.find(l => l.name === name)
            if (label) save([...selectedIds.value, label.id])
            form.reset()
            creating.value = false
        },
    })
}
</script>
