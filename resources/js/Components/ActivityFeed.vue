<template>
    <section class="mb-8 bg-white rounded-[2rem] border border-gray-50 p-8 shadow-sm">
        <button
            type="button"
            @click="toggle"
            class="flex w-full items-center justify-between text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading"
            :aria-expanded="open"
        >
            <span>{{ title }}</span>
            <span>{{ open ? 'Skrýt' : 'Zobrazit' }}</span>
        </button>

        <div v-if="open" class="mt-6">
            <p v-if="!entries.length && !loading" class="text-sm font-bold text-gray-300">Zatím žádná aktivita.</p>

            <ul class="space-y-4">
                <li v-for="entry in entries" :key="entry.id" class="flex items-start gap-3">
                    <UserAvatar v-if="entry.user" :user="entry.user" class="h-8 w-8 shrink-0 text-[9px]" />
                    <span v-else class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-gray-100 text-[9px] font-black text-gray-400">⚙</span>
                    <div class="min-w-0">
                        <p class="text-sm font-semibold text-gray-700">
                            <span class="font-black text-gray-900">{{ entry.user?.name ?? 'Systém' }}</span>
                            · {{ entry.text }}
                        </p>
                        <p class="text-xs font-semibold text-gray-400">{{ formatDate(entry.created_at) }}</p>
                    </div>
                </li>
            </ul>

            <button
                v-if="hasMore"
                type="button"
                @click="load"
                :disabled="loading"
                class="mt-6 text-xs font-bold text-gray-400 hover:text-brand-primary-from transition-colors disabled:opacity-50"
            >
                Načíst starší
            </button>
        </div>
    </section>
</template>

<script setup>
import { ref } from 'vue'
import UserAvatar from './UserAvatar.vue'

const props = defineProps({
    url: String,
    title: { type: String, default: 'Historie změn' },
})

const open = ref(false)
const loading = ref(false)
const loaded = ref(false)
const entries = ref([])
const hasMore = ref(false)

const load = async () => {
    loading.value = true

    try {
        const before = entries.value.at(-1)?.id
        const { data } = await window.axios.get(props.url, { params: before ? { before } : {} })
        entries.value.push(...data.data)
        hasMore.value = data.has_more
        loaded.value = true
    } finally {
        loading.value = false
    }
}

const toggle = () => {
    open.value = !open.value
    if (open.value && !loaded.value) load()
}

const formatDate = (value) => new Date(value).toLocaleString('cs-CZ', { dateStyle: 'medium', timeStyle: 'short' })
</script>
