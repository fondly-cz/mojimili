<template>
    <div>
        <label
            class="flex flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed px-4 py-4 text-center cursor-pointer transition-colors"
            :class="dragging ? 'border-brand-primary-from bg-brand-primary-from/5' : 'border-gray-200 hover:border-brand-primary-from'"
            @dragover.prevent="dragging = true"
            @dragleave.prevent="dragging = false"
            @drop.prevent="onDrop"
        >
            <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">
                📎 Přetáhněte soubory sem nebo klikněte
            </span>
            <span class="text-[10px] font-semibold text-gray-300">Víc souborů najednou, každý do 20 MB</span>
            <input type="file" multiple class="hidden" @change="onSelect">
        </label>

        <ul v-if="modelValue.length" class="mt-2 flex flex-wrap gap-2">
            <li
                v-for="(file, index) in modelValue"
                :key="`${file.name}-${index}`"
                class="flex items-center gap-2 px-2.5 py-1 rounded-lg border border-gray-100 bg-gray-50 text-[10px] font-bold text-gray-600"
            >
                {{ file.name }}
                <span class="text-gray-400 font-semibold">{{ formatSize(file.size) }}</span>
                <button type="button" @click="removeAt(index)" class="text-gray-300 hover:text-red-500" title="Odebrat">✕</button>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { formatSize } from '../utils/files'

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
})

const emit = defineEmits(['update:modelValue'])

const dragging = ref(false)

// Every pick or drop adds to the files chosen so far instead of replacing them.
const add = (files) => emit('update:modelValue', [...props.modelValue, ...files])

const onSelect = (event) => {
    add([...event.target.files])
    // Lets the same file be picked again after it was removed.
    event.target.value = ''
}

const onDrop = (event) => {
    dragging.value = false
    add([...event.dataTransfer.files])
}

const removeAt = (index) => emit('update:modelValue', props.modelValue.filter((_, i) => i !== index))
</script>
