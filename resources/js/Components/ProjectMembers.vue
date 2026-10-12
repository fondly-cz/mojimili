<template>
    <section class="mb-8 bg-white rounded-[2rem] border border-gray-50 p-8 shadow-sm">
        <h2 class="mb-4 text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Lidé v projektu ({{ members.length }})</h2>

        <div class="flex flex-wrap items-center gap-2">
            <p v-if="!members.length" class="text-sm font-bold text-gray-300">K projektu zatím není nikdo přiřazený, v úkolech proto nejde nikoho zmínit přes @.</p>

            <span
                v-for="member in members"
                :key="member.id"
                class="inline-flex items-center gap-2 rounded-full border border-gray-100 bg-gray-50 py-1 pl-1 pr-3 text-xs font-bold text-gray-700"
            >
                <UserAvatar :user="member" class="h-6 w-6 text-[8px]" />
                {{ member.name }}
                <button
                    type="button"
                    @click="remove(member)"
                    class="text-gray-300 hover:text-red-500 transition-colors"
                    :title="`Odebrat ${member.name} z projektu`"
                    :aria-label="`Odebrat ${member.name} z projektu`"
                >
                    ×
                </button>
            </span>

            <select
                v-if="candidates.length"
                value=""
                @change="add"
                class="rounded-full border-gray-100 bg-white py-1.5 pl-4 pr-8 text-[10px] font-black uppercase tracking-widest text-gray-400 focus:ring-brand-primary-from focus:border-brand-primary-from"
                aria-label="Přidat osobu do projektu"
            >
                <option value="">+ Přidat osobu</option>
                <option v-for="user in candidates" :key="user.id" :value="user.id">{{ user.name }}</option>
            </select>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue'
import { router } from '@inertiajs/vue3'
import UserAvatar from './UserAvatar.vue'

const props = defineProps({
    projectId: Number,
    members: Array,
    users: Array,
})

const candidates = computed(() => {
    const memberIds = new Set(props.members.map(member => member.id))

    return props.users.filter(user => !memberIds.has(user.id))
})

const add = (event) => {
    const userId = event.target.value
    event.target.value = ''
    if (!userId) return

    router.post(`/projects/${props.projectId}/members`, { user_id: userId }, { preserveScroll: true })
}

const remove = (member) => {
    router.delete(`/projects/${props.projectId}/members/${member.id}`, { preserveScroll: true })
}
</script>
