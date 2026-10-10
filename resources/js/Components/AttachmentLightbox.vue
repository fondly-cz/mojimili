<template>
    <Teleport to="body">
        <div
            v-if="current"
            class="fixed inset-0 z-[100] flex flex-col bg-gray-950/95 text-white select-none"
            role="dialog"
            aria-modal="true"
            :aria-label="current.original_name"
        >
            <!-- Toolbar -->
            <div class="flex shrink-0 items-center gap-3 px-4 py-3">
                <span class="shrink-0 text-[10px] font-black uppercase tracking-widest text-white/50">{{ index + 1 }} / {{ items.length }}</span>
                <span class="min-w-0 grow truncate text-xs font-bold text-white/80">{{ current.original_name }}</span>
                <a v-if="!current.inline" :href="downloadUrl(current)" :class="toolClass" title="Stáhnout" aria-label="Stáhnout">
                    <ArrowDownTrayIcon class="h-5 w-5" />
                </a>
                <a :href="current.url" target="_blank" rel="noopener" :class="toolClass" title="Otevřít v nové záložce" aria-label="Otevřít v nové záložce">
                    <ArrowTopRightOnSquareIcon class="h-5 w-5" />
                </a>
                <button type="button" @click="close" :class="toolClass" title="Zavřít (Esc)" aria-label="Zavřít">
                    <XMarkIcon class="h-6 w-6" />
                </button>
            </div>

            <!-- Stage: a click on the empty area closes, a horizontal swipe moves between files. -->
            <div class="relative min-h-0 grow">
                <div
                    class="absolute inset-0 flex items-center justify-center overflow-hidden px-4 sm:px-16 touch-none"
                    @pointerdown="onPointerDown"
                    @pointermove="onPointerMove"
                    @pointerup="onPointerUp"
                    @pointercancel="pointer = null"
                >
                    <img
                        v-if="current.is_image"
                        :key="index"
                        :src="current.url"
                        :alt="current.caption || current.original_name"
                        draggable="false"
                        class="max-h-full max-w-full object-contain"
                        :class="[zoomed ? 'cursor-grab' : 'cursor-zoom-in', pointer ? '' : 'transition-transform duration-200']"
                        :style="imageStyle"
                    >
                    <iframe
                        v-else-if="current.is_pdf"
                        :key="current.url"
                        :src="current.url"
                        :title="current.original_name"
                        class="h-full w-full max-w-5xl rounded-xl bg-white"
                    ></iframe>
                    <div v-else class="w-full max-w-sm rounded-[2rem] bg-white p-8 text-center text-gray-700">
                        <div class="text-4xl">📎</div>
                        <p class="mt-4 break-words text-sm font-extrabold text-gray-900">{{ current.original_name }}</p>
                        <p class="mt-1 text-xs font-bold text-gray-400">{{ formatSize(current.size) }}</p>
                        <a
                            :href="downloadUrl(current)"
                            class="brand-gradient mt-6 inline-block rounded-xl px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-white"
                        >
                            Stáhnout
                        </a>
                    </div>
                </div>

                <template v-if="items.length > 1">
                    <button type="button" @click="go(-1)" :class="[toolClass, navClass, 'left-2']" title="Předchozí (←)" aria-label="Předchozí">
                        <ChevronLeftIcon class="h-7 w-7" />
                    </button>
                    <button type="button" @click="go(1)" :class="[toolClass, navClass, 'right-2']" title="Další (→)" aria-label="Další">
                        <ChevronRightIcon class="h-7 w-7" />
                    </button>
                </template>
            </div>

            <p v-if="current.caption" class="shrink-0 px-4 pt-3 text-center text-sm font-semibold text-white/80">{{ current.caption }}</p>

            <!-- Thumbnails -->
            <div v-if="items.length > 1" class="flex shrink-0 gap-2 overflow-x-auto px-4 py-3">
                <button
                    v-for="(item, i) in items"
                    :key="i"
                    :ref="el => { if (i === index) activeThumb = el }"
                    type="button"
                    @click="emit('update:modelValue', i)"
                    class="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-lg border-2 bg-white/10 text-[9px] font-black uppercase transition-opacity first:ml-auto last:mr-auto"
                    :class="i === index ? 'border-white opacity-100' : 'border-transparent opacity-50 hover:opacity-90'"
                    :title="item.caption || item.original_name"
                >
                    <img v-if="item.is_image" :src="item.url" alt="" loading="lazy" class="h-full w-full object-cover">
                    <span v-else class="truncate px-1">{{ extension(item) }}</span>
                </button>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, nextTick, onUnmounted, ref, watch } from 'vue'
import {
    ArrowDownTrayIcon,
    ArrowTopRightOnSquareIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline'
import { formatSize } from '../utils/files'

/**
 * Full screen gallery of attachments: images with zoom, PDF preview, a download card for other files.
 * Items marked `inline` are images from rich text (see inlineImages) and have no download endpoint.
 * v-model is the index of the open item, null while the gallery is closed.
 */
const props = defineProps({
    items: { type: Array, default: () => [] },
    modelValue: { type: Number, default: null },
})

const emit = defineEmits(['update:modelValue'])

const toolClass = 'inline-flex shrink-0 items-center justify-center rounded-full p-2 text-white/70 hover:bg-white/10 hover:text-white transition-colors'
const navClass = 'absolute top-1/2 -translate-y-1/2 bg-black/30'

const index = computed(() => props.modelValue)
const current = computed(() => (index.value === null ? null : props.items[index.value] ?? null))

const close = () => emit('update:modelValue', null)

const go = (step) => {
    const count = props.items.length
    emit('update:modelValue', (index.value + step + count) % count)
}

const downloadUrl = (item) => `${item.url}?download=1`

const extension = (item) => item.original_name.includes('.') ? item.original_name.split('.').pop() : '?'

// A tap zooms the image in around the tapped point, dragging then pans it.
const zoomed = ref(false)
const origin = ref('50% 50%')
const offset = ref({ x: 0, y: 0 })

const imageStyle = computed(() => ({
    transformOrigin: origin.value,
    transform: zoomed.value
        ? `translate(${offset.value.x}px, ${offset.value.y}px) scale(2.5)`
        : 'none',
}))

const resetZoom = () => {
    zoomed.value = false
    offset.value = { x: 0, y: 0 }
}

const zoomAt = (event, image) => {
    const rect = image.getBoundingClientRect()
    origin.value = `${((event.clientX - rect.left) / rect.width) * 100}% ${((event.clientY - rect.top) / rect.height) * 100}%`
    zoomed.value = true
}

const pointer = ref(null)

const onPointerDown = (event) => {
    const image = event.target.tagName === 'IMG' ? event.target : null
    pointer.value = {
        x: event.clientX,
        y: event.clientY,
        offset: { ...offset.value },
        image,
        onBackdrop: event.target === event.currentTarget,
    }
    // Only a drag on the image is captured, so links on the file card still get their clicks.
    if (image) event.currentTarget.setPointerCapture(event.pointerId)
}

const onPointerMove = (event) => {
    if (!pointer.value || !zoomed.value) return
    offset.value = {
        x: pointer.value.offset.x + event.clientX - pointer.value.x,
        y: pointer.value.offset.y + event.clientY - pointer.value.y,
    }
}

const onPointerUp = (event) => {
    const start = pointer.value
    pointer.value = null
    if (!start) return

    const dx = event.clientX - start.x
    const dy = event.clientY - start.y

    // A tap: zoom the image in or out, or close when it hit the empty backdrop.
    if (Math.hypot(dx, dy) < 6) {
        if (start.image) zoomed.value ? resetZoom() : zoomAt(event, start.image)
        else if (start.onBackdrop) close()
        return
    }

    if (!zoomed.value && props.items.length > 1 && Math.abs(dx) > 50 && Math.abs(dx) > Math.abs(dy)) {
        go(dx < 0 ? 1 : -1)
    }
}

const onKeydown = (event) => {
    if (event.key === 'Escape') close()
    else if (event.key === 'ArrowLeft' && props.items.length > 1) go(-1)
    else if (event.key === 'ArrowRight' && props.items.length > 1) go(1)
}

const activeThumb = ref(null)

watch(index, (value, previous) => {
    resetZoom()

    if (value !== null && previous === null) {
        window.addEventListener('keydown', onKeydown)
        document.body.style.overflow = 'hidden'
    } else if (value === null && previous !== null) {
        window.removeEventListener('keydown', onKeydown)
        document.body.style.overflow = ''
    }

    nextTick(() => activeThumb.value?.scrollIntoView({ block: 'nearest', inline: 'center' }))
})

onUnmounted(() => {
    window.removeEventListener('keydown', onKeydown)
    document.body.style.overflow = ''
})
</script>
