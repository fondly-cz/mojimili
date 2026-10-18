<template>
    <div class="choices-select">
        <select ref="el" :multiple="multiple" />
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import Choices from 'choices.js'
import 'choices.js/public/assets/styles/choices.min.css'

const props = defineProps({
    // Selected value(s): an array of ids when multiple, otherwise a single id or ''.
    modelValue: { type: [Array, String, Number], default: '' },
    // [{ value, label }]
    options: { type: Array, required: true },
    multiple: { type: Boolean, default: false },
    placeholder: { type: String, default: '' },
    disabled: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const el = ref(null)
let choices = null
// setChoices re-renders the list; its own events must not be sent back up as user input.
let syncing = false

const selected = () => (props.multiple ? props.modelValue : [props.modelValue]).map(String)

const sync = () => {
    if (!choices) return
    const chosen = selected()
    syncing = true
    choices.clearStore()
    choices.setChoices(
        props.options.map(option => ({
            value: String(option.value),
            label: option.label,
            selected: chosen.includes(String(option.value)),
        })),
        'value',
        'label',
        true,
    )
    syncing = false
}

onMounted(() => {
    choices = new Choices(el.value, {
        removeItemButton: props.multiple,
        shouldSort: false,
        searchEnabled: true,
        searchPlaceholderValue: 'Hledat…',
        placeholder: !!props.placeholder,
        placeholderValue: props.placeholder,
        itemSelectText: '',
        noResultsText: 'Nic nenalezeno',
        noChoicesText: 'Není co vybrat',
        removeItemLabelText: value => `Odebrat ${value}`,
    })
    sync()
    if (props.disabled) choices.disable()

    el.value.addEventListener('change', () => {
        if (syncing) return
        const value = choices.getValue(true)
        emit('update:modelValue', props.multiple ? value.map(v => (isNaN(v) ? v : Number(v))) : (value === '' || value == null ? '' : isNaN(value) ? value : Number(value)))
    })
})

watch(() => [props.options, props.modelValue], sync, { deep: true })
watch(() => props.disabled, disabled => (disabled ? choices?.disable() : choices?.enable()))

onBeforeUnmount(() => choices?.destroy())
</script>

<style>
.choices-select .choices { margin-bottom: 0; }
.choices-select .choices__inner {
    min-height: 0;
    padding: 0.25rem 0.5rem;
    background-color: rgb(249 250 251);
    border: 1px solid rgb(243 244 246);
    border-radius: 0.75rem;
    font-size: 0.75rem;
    font-weight: 600;
    color: rgb(55 65 81);
}
.choices-select .choices.is-focused .choices__inner,
.choices-select .choices.is-open .choices__inner { border-color: var(--color-brand-primary-from, #6366f1); }
.choices-select .choices__list--dropdown,
.choices-select .choices__list[aria-expanded] {
    border-radius: 0.75rem;
    font-size: 0.75rem;
    z-index: 30;
}
.choices-select .choices__list--multiple .choices__item {
    background-color: var(--color-brand-primary-from, #6366f1);
    border-color: transparent;
    border-radius: 0.5rem;
    font-weight: 800;
}
.choices-select .choices.is-disabled .choices__inner { opacity: 0.6; cursor: default; }
</style>
