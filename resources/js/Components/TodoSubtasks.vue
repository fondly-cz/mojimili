<template>
    <div>
        <p v-if="todo.children.length" class="mb-4 text-xs font-bold text-gray-400">{{ doneCount }} z {{ todo.children.length }} hotovo</p>

        <ul v-if="todo.children.length" class="mb-4 space-y-2">
            <li
                v-for="(child, index) in todo.children"
                :key="child.id"
                class="group flex items-start gap-3 rounded-xl border border-gray-50 p-3 hover:border-brand-primary-from/30 transition-colors"
            >
                <input
                    type="checkbox"
                    :checked="child.is_done"
                    @change="patch(child, { is_done: !child.is_done })"
                    class="mt-0.5 h-4 w-4 shrink-0 rounded border-gray-300 text-brand-primary-from focus:ring-brand-primary-from cursor-pointer"
                    :aria-label="`Hotovo: ${child.name}`"
                >
                <div class="min-w-0 grow">
                    <Link
                        :href="`/todos/${child.id}`"
                        class="text-sm font-bold hover:text-brand-primary-from transition-colors"
                        :class="child.is_done ? 'line-through text-gray-400' : 'text-gray-700'"
                    >
                        {{ child.name }}
                    </Link>
                    <div class="mt-1 flex flex-wrap items-center gap-1.5 text-[9px] font-black uppercase tracking-widest text-gray-400">
                        <span v-if="child.assignee">{{ child.assignee.name }}</span>
                        <span v-if="child.due_date">· {{ new Date(child.due_date).toLocaleDateString('cs-CZ') }}<span v-if="child.due_time"> {{ child.due_time }}</span></span>
                        <span v-if="child.children_count">· {{ child.children_count }} podúkolů</span>
                        <span
                            v-for="label in child.labels || []"
                            :key="label.id"
                            class="px-1.5 py-0.5 rounded text-white"
                            :style="{ backgroundColor: label.color }"
                        >{{ label.name }}</span>
                    </div>
                </div>
                <div class="flex shrink-0 items-center gap-1 opacity-0 group-hover:opacity-100 focus-within:opacity-100 transition-opacity">
                    <button type="button" :disabled="index === 0" @click="move(index, -1)" class="p-1 text-gray-300 hover:text-brand-primary-from disabled:invisible" aria-label="Posunout výš">↑</button>
                    <button type="button" :disabled="index === todo.children.length - 1" @click="move(index, 1)" class="p-1 text-gray-300 hover:text-brand-primary-from disabled:invisible" aria-label="Posunout níž">↓</button>
                    <button type="button" @click="deleting = child" class="p-1 text-gray-300 hover:text-red-500" :aria-label="`Smazat podúkol ${child.name}`">×</button>
                </div>
            </li>
        </ul>

        <form @submit.prevent="add" class="flex items-center gap-2">
            <input
                v-model="form.name"
                type="text"
                placeholder="Nový podúkol..."
                class="block w-full px-3 py-2 bg-gray-50 border-gray-100 rounded-xl text-xs font-semibold text-gray-700 focus:ring-brand-primary-from focus:border-brand-primary-from"
            >
            <button type="submit" :disabled="form.processing || !form.name.trim()" class="shrink-0 text-[10px] font-black uppercase tracking-widest text-brand-primary-from disabled:text-gray-300">
                Přidat
            </button>
        </form>
        <p v-if="form.errors.name || form.errors.parent_id" class="mt-2 text-xs text-red-500 font-bold">{{ form.errors.name || form.errors.parent_id }}</p>

        <ConfirmModal
            :show="!!deleting"
            title="Smazat podúkol"
            :message="`Opravdu chcete smazat podúkol „${deleting?.name}“? Jeho vlastní podúkoly se přesunou o úroveň výš.`"
            @close="deleting = null"
            @confirm="destroy"
        />
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import ConfirmModal from './ConfirmModal.vue'

const props = defineProps({
    todo: Object,
})

const doneCount = computed(() => props.todo.children.filter(child => child.is_done).length)

const patch = (child, data) => router.patch(`/todos/${child.id}`, data, { preserveScroll: true })

const form = useForm({ name: '', parent_id: props.todo.id })

const add = () => {
    form.post(`/todolists/${props.todo.todolist_id}/todos`, {
        preserveScroll: true,
        onSuccess: () => form.reset('name'),
    })
}

// Only the siblings are renumbered; the reorder endpoint keeps them within this list.
const move = (index, offset) => {
    const ids = props.todo.children.map(child => child.id)
    ;[ids[index], ids[index + offset]] = [ids[index + offset], ids[index]]
    router.post(`/todolists/${props.todo.todolist_id}/reorder`, { ids }, { preserveScroll: true })
}

const deleting = ref(null)
const destroy = () => router.delete(`/todos/${deleting.value.id}`, {
    preserveScroll: true,
    onSuccess: () => { deleting.value = null },
})
</script>
