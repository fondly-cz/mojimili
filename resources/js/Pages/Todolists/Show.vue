<template>
    <Layout>
        <Breadcrumbs
            :items="[
                { label: 'Nástěnka', href: '/' },
                { label: 'Projekty', href: '/projects' },
                { label: todolist.project.name, href: `/projects/${todolist.project.id}` },
                { label: todolist.name },
            ]"
        />

        <div class="mb-8 flex flex-wrap items-center justify-between gap-4">
            <Link
                :href="`/projects/${todolist.project.id}`"
                class="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-brand-primary-from transition-colors"
            >
                ← Zpět na projekt
            </Link>
            <p v-if="reportedMinutes != null" class="text-xs font-bold text-gray-400">Vykázáno v seznamu: {{ formatMinutes(reportedMinutes) }}</p>
        </div>

        <TodolistPanel :list="todolist" :assignable-users="assignableUsers" detail />
    </Layout>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import Layout from '../../Components/Layout.vue'
import Breadcrumbs from '../../Components/Breadcrumbs.vue'
import TodolistPanel from '../../Components/TodolistPanel.vue'
import { formatMinutes } from '../../utils/billing'

const props = defineProps({
    todolist: Object,
    reportedMinutes: { type: Number, default: null },
})

// A todo can be handed only to a person from the project; someone removed since stays on the todos they hold.
const assignableUsers = computed(() => {
    const members = props.todolist.project.members
    const memberIds = new Set(members.map(member => member.id))
    const former = props.todolist.todos
        .map(todo => todo.assignee)
        .filter(assignee => assignee && !memberIds.has(assignee.id))

    return [...members, ...new Map(former.map(user => [user.id, user])).values()]
})
</script>
