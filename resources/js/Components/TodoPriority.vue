<template>
    <button
        type="button"
        :disabled="disabled"
        @click="cycle"
        class="inline-flex items-center gap-2 rounded-xl px-1 py-1 text-xs font-bold transition-colors enabled:hover:bg-gray-50 disabled:cursor-default"
        :class="current.text"
        :title="`Priorita: ${current.label} (kliknutím změníte)`"
        :aria-label="`Priorita: ${current.label}`"
    >
        <svg xmlns="http://www.w3.org/2000/svg" class="h-7 w-7" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z" fill="currentColor" fill-opacity="0.15" />
            <line x1="12" y1="9" x2="12" y2="13" />
            <line x1="12" y1="17" x2="12.01" y2="17" />
        </svg>
        <span>{{ current.label }}</span>
    </button>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    // 'low' | 'medium' | 'high' | null
    modelValue: { type: String, default: null },
    disabled: Boolean,
})

const emit = defineEmits(['update:modelValue'])

// One icon, four states; a click moves to the next one and wraps around.
const levels = [
    { value: null, label: 'Bez priority', text: 'text-gray-600' },
    { value: 'low', label: 'Nízká', text: 'text-yellow-500' },
    { value: 'medium', label: 'Střední', text: 'text-orange-500' },
    { value: 'high', label: 'Vysoká', text: 'text-red-500' },
]

const index = computed(() => Math.max(0, levels.findIndex(level => level.value === (props.modelValue || null))))
const current = computed(() => levels[index.value])

const cycle = () => emit('update:modelValue', levels[(index.value + 1) % levels.length].value)
</script>
