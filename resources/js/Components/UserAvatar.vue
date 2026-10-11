<template>
    <img v-if="user?.avatar" :src="user.avatar" :alt="label" :title="label" class="shrink-0 rounded-full object-cover">
    <span v-else class="flex shrink-0 items-center justify-center rounded-full brand-gradient font-black uppercase text-white" :title="label">
        {{ initials }}
    </span>
</template>

<script setup>
import { computed } from 'vue'

// Size and font size come from the parent's classes.
const props = defineProps({
    user: Object,
    // For authors that are not users of the app (e.g. imported comments).
    name: String,
})

const label = computed(() => props.user?.name || props.name || '')

const initials = computed(() => {
    const words = label.value.trim().split(/\s+/).filter(Boolean)
    if (words.length === 0) return '?'

    return words.length > 1 ? words[0][0] + words[words.length - 1][0] : words[0].slice(0, 2)
})
</script>
