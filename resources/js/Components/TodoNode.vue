<template>
    <div>
        <div
            class="group flex items-start gap-4 rounded-2xl border-2 border-gray-50 bg-white p-4 transition-all hover:border-brand-primary-from/30"
            :class="{ 'opacity-60': todo.is_done }"
        >
            <input
                type="checkbox"
                :checked="todo.is_done"
                @change="$emit('toggle', todo)"
                class="mt-1 h-5 w-5 shrink-0 rounded-lg border-gray-300 text-brand-primary-from focus:ring-brand-primary-from cursor-pointer"
            >

            <div class="grow min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <Link
                        :href="`/todos/${todo.id}`"
                        class="text-sm font-black text-gray-900 font-heading leading-tight hover:text-brand-primary-from transition-colors"
                        :class="{ 'line-through text-gray-400': todo.is_done }"
                    >
                        {{ todo.name }}
                    </Link>
                    <span v-if="todo.priority" class="px-2 py-0.5 text-[8px] font-black rounded-lg uppercase tracking-widest" :class="priorityStyles[todo.priority]" title="Priorita">
                        {{ priorityLabels[todo.priority] }}
                    </span>
                    <span
                        v-for="label in todo.labels || []"
                        :key="label.id"
                        class="px-2 py-0.5 text-[8px] font-black rounded-lg text-white uppercase tracking-widest"
                        :style="{ backgroundColor: label.color }"
                    >
                        {{ label.name }}
                    </span>
                    <span v-if="todo.days > 0" class="px-2 py-0.5 bg-gray-50 text-[8px] font-black rounded-lg text-gray-400 uppercase tracking-widest">
                        {{ todo.days }} dní
                    </span>
                    <span v-if="todo.estimated_minutes" class="px-2 py-0.5 bg-gray-50 text-[8px] font-black rounded-lg text-gray-400 uppercase tracking-widest" title="Odhadovaný čas">
                        odhad {{ formatMinutes(todo.estimated_minutes) }}
                    </span>
                    <span v-if="todo.due_time" class="px-2 py-0.5 bg-gray-50 text-[8px] font-black rounded-lg text-gray-400 uppercase tracking-widest" title="Čas termínu">
                        do {{ todo.due_time }}
                    </span>
                    <span v-if="todo.calculation_item_id" class="px-2 py-0.5 bg-blue-50 text-[8px] font-black rounded-lg text-blue-600 uppercase tracking-widest border border-blue-100" title="Vzniklo z položky kalkulace">
                        z kalkulace
                    </span>
                    <span v-if="children.length > 0" class="px-2 py-0.5 bg-gray-100 text-[8px] font-black rounded-lg text-gray-400 uppercase tracking-widest">
                        {{ doneChildren }}/{{ children.length }} podúkolů
                    </span>
                    <span v-if="todo.recurrence_frequency" class="px-2 py-0.5 bg-gray-50 text-[8px] font-black rounded-lg text-brand-primary-from uppercase tracking-widest" title="Opakovaný úkol">
                        ↻ opakuje se
                    </span>
                    <span v-if="reportedMinutes > 0" class="px-2 py-0.5 bg-gray-50 text-[8px] font-black rounded-lg text-gray-400 uppercase tracking-widest" title="Vykázaný čas">
                        {{ formatMinutes(reportedMinutes) }}
                    </span>
                    <span v-if="todo.comments_count > 0" class="px-2 py-0.5 bg-gray-50 text-[8px] font-black rounded-lg text-gray-400 uppercase tracking-widest" title="Komentáře">
                        💬 {{ todo.comments_count }}
                    </span>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-3">
                    <select
                        :value="todo.assigned_user_id || ''"
                        @change="e => $emit('assign', { todo, userId: e.target.value || null })"
                        class="px-2.5 py-1 text-[10px] font-bold text-gray-500 bg-gray-50 border-none rounded-lg focus:ring-1 focus:ring-brand-primary-from transition-all cursor-pointer"
                    >
                        <option value="">Nepřiřazeno</option>
                        <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                    </select>

                    <input
                        type="date"
                        :value="todo.due_date ? todo.due_date.substring(0, 10) : ''"
                        @change="e => $emit('due-date', { todo, dueDate: e.target.value || null })"
                        class="px-2.5 py-1 text-[10px] font-bold text-gray-500 bg-gray-50 border-none rounded-lg focus:ring-1 focus:ring-brand-primary-from transition-all"
                    >

                    <button
                        type="button"
                        @click="$emit('add-child', todo)"
                        class="text-[10px] font-black uppercase tracking-widest text-gray-300 hover:text-brand-primary-from transition-colors"
                    >
                        + Podúkol
                    </button>
                </div>
            </div>

            <div class="flex shrink-0 items-center gap-1">
                <Link
                    :href="`/todos/${todo.id}`"
                    class="p-2 rounded-xl text-gray-300 hover:text-brand-primary-from hover:bg-gray-50 transition-colors"
                    title="Detail úkolu"
                    aria-label="Detail úkolu"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                    </svg>
                </Link>
                <button
                    type="button"
                    @click="$emit('remove', todo)"
                    class="p-2 rounded-xl text-gray-300 hover:text-red-500 hover:bg-red-50 transition-colors"
                    title="Smazat úkol"
                    aria-label="Smazat úkol"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- Children -> recursive mapping -->
        <div v-if="children.length > 0" class="mt-3 ml-6 space-y-3 border-l-2 border-dashed border-gray-100 pl-4">
            <TodoNode
                v-for="child in children"
                :key="child.id"
                :todo="child"
                :all-todos="allTodos"
                :users="users"
                @toggle="$emit('toggle', $event)"
                @assign="$emit('assign', $event)"
                @due-date="$emit('due-date', $event)"
                @add-child="$emit('add-child', $event)"
                @remove="$emit('remove', $event)"
            />
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { Link } from '@inertiajs/vue3'
import { formatMinutes } from '../utils/billing'

const props = defineProps({
    todo: Object,
    allTodos: Array,
    users: Array,
})

defineEmits(['toggle', 'assign', 'due-date', 'add-child', 'remove'])

const priorityLabels = { high: 'Vysoká', medium: 'Střední', low: 'Nízká' }
const priorityStyles = {
    high: 'bg-red-50 text-red-600',
    medium: 'bg-amber-50 text-amber-600',
    low: 'bg-gray-50 text-gray-400',
}

const children = computed(() => props.allTodos.filter(t => t.parent_id === props.todo.id))

const doneChildren = computed(() => children.value.filter(t => t.is_done).length)

const reportedMinutes = computed(() => (props.todo.work_reports || []).reduce((sum, r) => sum + r.minutes, 0))
</script>
