<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
    views: {
        type: Array,
        default: () => [],
    },
})

const PAGE_SIZE = 20
const limit = ref(PAGE_SIZE)

const customerViews = computed(() => props.views.filter(view => !view.user_id))
const uniqueIps = computed(() => new Set(customerViews.value.map(view => view.ip_address)).size)
const lastCustomerView = computed(() => customerViews.value[0]?.viewed_at)
const visibleViews = computed(() => props.views.slice(0, limit.value))

const deviceLabels = {
    desktop: '🖥️ Počítač',
    mobile: '📱 Mobil',
    tablet: '📱 Tablet',
    bot: '🤖 Bot / náhled',
    unknown: '❔ Neznámé',
}

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
        <div class="px-8 py-6 border-b border-gray-100 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h2 class="text-xl font-extrabold text-gray-900 font-heading">👁️ Zobrazení kalkulace</h2>
                <p class="text-sm text-gray-500 mt-1">Každé otevření veřejného odkazu – IP adresa, zařízení a prohlížeč.</p>
            </div>
            <div class="flex gap-6 text-sm">
                <div>
                    <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Zákazník</div>
                    <div class="text-lg font-extrabold text-gray-900">{{ customerViews.length }}×</div>
                </div>
                <div>
                    <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Unikátní IP</div>
                    <div class="text-lg font-extrabold text-gray-900">{{ uniqueIps }}</div>
                </div>
                <div>
                    <div class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Naposledy</div>
                    <div class="text-lg font-extrabold text-gray-900">{{ lastCustomerView ? formatDateTime(lastCustomerView) : '—' }}</div>
                </div>
            </div>
        </div>

        <div v-if="views.length === 0" class="px-8 py-10 text-center text-sm text-gray-400 font-medium">
            Veřejný odkaz zatím nikdo neotevřel.
        </div>

        <div v-else class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="text-left text-[10px] font-black text-gray-400 uppercase tracking-widest">
                        <th class="px-8 py-3">Kdy</th>
                        <th class="px-4 py-3">IP adresa</th>
                        <th class="px-4 py-3">Zařízení</th>
                        <th class="px-4 py-3">Systém</th>
                        <th class="px-8 py-3">Prohlížeč</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="view in visibleViews" :key="view.id" class="hover:bg-gray-50/50">
                        <td class="px-8 py-3 whitespace-nowrap font-semibold text-gray-700">
                            {{ formatDateTime(view.viewed_at) }}
                            <span v-if="view.user" class="ml-2 px-2 py-0.5 bg-gray-100 text-gray-500 rounded-full text-[10px] font-bold uppercase tracking-wider" :title="`Přihlášený uživatel: ${view.user.name}`">
                                interní · {{ view.user.name }}
                            </span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap font-mono text-gray-600">
                            {{ view.ip_address || '—' }}
                            <span v-if="view.country" class="ml-1 text-xs text-gray-400">({{ view.country }})</span>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ deviceLabels[view.device] || view.device }}</td>
                        <td class="px-4 py-3 whitespace-nowrap text-gray-600">{{ view.platform || '—' }}</td>
                        <td class="px-8 py-3 whitespace-nowrap text-gray-600" :title="view.user_agent">{{ view.browser || '—' }}</td>
                    </tr>
                </tbody>
            </table>

            <div v-if="views.length > limit" class="px-8 py-4 border-t border-gray-100 text-center">
                <button type="button" @click="limit += PAGE_SIZE" class="text-sm font-bold text-brand-primary-from hover:underline">
                    Zobrazit další ({{ views.length - limit }})
                </button>
            </div>
        </div>
    </section>
</template>
