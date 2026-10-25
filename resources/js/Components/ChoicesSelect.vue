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
    // [{ value, label, avatar? }]; an option with an `avatar` key (url or null) is drawn as a person:
    // profile picture, or initials when there is none.
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

const escapeHtml = (text) => String(text).replace(/[&<>"']/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[char])

// Same initials as UserAvatar.
const initialsOf = (name) => {
    const words = name.trim().split(/\s+/).filter(Boolean)
    if (words.length === 0) return '?'

    return (words.length > 1 ? words[0][0] + words[words.length - 1][0] : words[0].slice(0, 2)).toUpperCase()
}

const hasPeople = () => props.options.some(option => 'avatar' in option)

const personHtml = (option) => {
    const badge = option.avatar
        ? `<img src="${escapeHtml(option.avatar)}" alt="" class="choices-avatar">`
        : `<span class="choices-avatar choices-avatar--initials brand-gradient">${escapeHtml(initialsOf(option.label))}</span>`

    return `<span class="choices-person">${badge}<span>${escapeHtml(option.label)}</span></span>`
}

const selected = () => (props.multiple ? props.modelValue : [props.modelValue]).map(String)

const sync = () => {
    if (!choices) return
    const chosen = selected()
    syncing = true
    choices.clearStore()
    choices.setChoices(
        props.options.map(option => ({
            value: String(option.value),
            label: 'avatar' in option ? personHtml(option) : option.label,
            customProperties: { name: option.label },
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
        // Person options carry markup (avatar) in the label, so search runs on the plain name instead.
        allowHTML: hasPeople(),
        searchFields: hasPeople() ? ['customProperties.name'] : ['label', 'value'],
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
.choices-select .choices-person { display: inline-flex; align-items: center; gap: 0.5rem; }
.choices-select .choices-avatar {
    display: inline-flex;
    height: 1.25rem;
    width: 1.25rem;
    flex-shrink: 0;
    align-items: center;
    justify-content: center;
    border-radius: 9999px;
    object-fit: cover;
}
.choices-select .choices-avatar--initials { color: white; font-size: 0.5rem; font-weight: 900; }
</style>
