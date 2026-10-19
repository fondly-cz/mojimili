<script setup>
defineProps({
    history: {
        type: Array,
        default: () => [],
    },
})

const formatDateTime = (value) => new Date(value).toLocaleString('cs-CZ', {
    day: 'numeric',
    month: 'numeric',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
})
</script>

<template>
    <section class="mt-10 bg-white shadow-brand rounded-brand border border-gray-50 overflow-hidden print:hidden">
        <div class="px-8 py-6 border-b border-gray-100">
            <h2 class="text-xl font-extrabold text-gray-900 font-heading">🕘 Historie úprav</h2>
            <p class="text-sm text-gray-500 mt-1">Kdo a kdy kalkulaci změnil, včetně potvrzení zákazníkem.</p>
        </div>

        <div v-if="history.length === 0" class="px-8 py-10 text-center text-sm text-gray-400 font-medium">
            Zatím žádné záznamy.
        </div>

        <ul v-else class="divide-y divide-gray-50">
            <li v-for="entry in history" :key="entry.id" class="px-8 py-3 flex flex-col sm:flex-row sm:items-baseline gap-1 sm:gap-6 hover:bg-gray-50/50">
                <span class="text-sm font-semibold text-gray-500 whitespace-nowrap sm:w-40 shrink-0">{{ formatDateTime(entry.created_at) }}</span>
                <span class="text-sm font-bold text-gray-700 sm:w-40 shrink-0">{{ entry.user?.name || 'Zákazník / systém' }}</span>
                <span class="text-sm text-gray-700">{{ entry.text }}</span>
            </li>
        </ul>
    </section>
</template>
