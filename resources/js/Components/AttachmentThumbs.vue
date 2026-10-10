<template>
    <ul v-if="attachments.length" class="flex flex-wrap gap-2">
        <li v-for="attachment in attachments" :key="attachment.url">
            <!-- A real link keeps middle-click / new tab working; a plain click opens the gallery. -->
            <a
                :href="attachment.url"
                target="_blank"
                rel="noopener"
                class="group/file block"
                :title="attachment.caption || attachment.original_name"
                @click.exact.prevent="$emit('open', attachment)"
            >
                <img
                    v-if="attachment.is_image"
                    :src="attachment.url"
                    :alt="attachment.caption || attachment.original_name"
                    loading="lazy"
                    class="h-24 w-24 object-cover rounded-xl border border-gray-100 group-hover/file:border-brand-primary-from transition-colors"
                >
                <span v-else class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-lg border border-gray-100 bg-gray-50 text-[10px] font-bold text-gray-600 group-hover/file:border-brand-primary-from transition-colors">
                    {{ attachment.is_pdf ? '📄' : '📎' }} {{ attachment.original_name }}
                    <span class="text-gray-400 font-semibold">{{ formatSize(attachment.size) }}</span>
                </span>
                <span v-if="attachment.caption" class="mt-1 block max-w-24 truncate text-[9px] font-bold text-gray-400">{{ attachment.caption }}</span>
            </a>
        </li>
    </ul>
</template>

<script setup>
import { formatSize } from '../utils/files'

defineProps({
    attachments: { type: Array, default: () => [] },
})

defineEmits(['open'])
</script>
