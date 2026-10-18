<template>
    <div class="space-y-3">
        <ChoicesSelect
            :model-value="selectedIds"
            :options="labels.map(label => ({ value: label.id, label: label.name }))"
            multiple
            placeholder="Bez štítků"
            @update:model-value="save"
        />

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
import ChoicesSelect from './ChoicesSelect.vue'

const props = defineProps({
    todo: Object,
    labels: Array,
    inputClass: String,
})

const selectedIds = computed(() => props.todo.labels.map(label => label.id))

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
