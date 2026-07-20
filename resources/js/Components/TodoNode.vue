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
                    <h4
                        class="text-sm font-black text-gray-900 font-heading leading-tight"
                        :class="{ 'line-through text-gray-400': todo.is_done }"
                    >
                        {{ todo.name }}
                    </h4>
                    <span v-if="todo.days > 0" class="px-2 py-0.5 bg-gray-50 text-[8px] font-black rounded-lg text-gray-400 uppercase tracking-widest">
                        {{ todo.days }} dní
                    </span>
                    <span v-if="todo.calculation_item_id" class="px-2 py-0.5 bg-blue-50 text-[8px] font-black rounded-lg text-blue-600 uppercase tracking-widest border border-blue-100" title="Vzniklo z položky kalkulace">
                        z kalkulace
                    </span>
                    <span v-if="children.length > 0" class="px-2 py-0.5 bg-gray-100 text-[8px] font-black rounded-lg text-gray-400 uppercase tracking-widest">
                        {{ doneChildren }}/{{ children.length }} podúkolů
                    </span>
                </div>

                <p v-if="todo.description" class="mt-1.5 text-xs font-medium text-gray-400 leading-relaxed">
                    {{ todo.description }}
                </p>

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

                    <button
                        type="button"
                        @click="$emit('remove', todo)"
                        class="text-[10px] font-black uppercase tracking-widest text-gray-300 hover:text-red-500 transition-colors opacity-0 group-hover:opacity-100"
                    >
                        Smazat
                    </button>
                </div>
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

const props = defineProps({
    todo: Object,
    allTodos: Array,
    users: Array,
})

defineEmits(['toggle', 'assign', 'due-date', 'add-child', 'remove'])

const children = computed(() => props.allTodos.filter(t => t.parent_id === props.todo.id))

const doneChildren = computed(() => children.value.filter(t => t.is_done).length)
</script>
