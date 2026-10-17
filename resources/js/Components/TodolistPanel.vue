<template>
    <div class="bg-white rounded-[2.5rem] border border-gray-50 shadow-sm overflow-hidden" :class="{ 'opacity-70': list.archived_at }">
        <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-50 px-10 py-6">
            <div>
                <h3 class="text-lg font-black text-gray-900 font-heading">
                    <span v-if="detail">{{ list.name }}</span>
                    <Link v-else :href="`/todolists/${list.id}`" class="hover:text-brand-primary-from transition-colors">{{ list.name }}</Link>
                    <span v-if="list.archived_at" class="ml-2 rounded-lg bg-gray-100 px-2 py-0.5 align-middle text-[8px] font-black uppercase tracking-widest text-gray-400">archivováno</span>
                </h3>
                <p class="mt-1 text-xs font-semibold text-gray-400">
                    {{ list.todos.filter(t => t.is_done).length }} z {{ list.todos.length }} hotovo
                    <span v-if="list.calculation">
                        · z kalkulace
                        <Link :href="`/calculations/${list.calculation.id}`" class="font-bold hover:text-brand-primary-from transition-colors">
                            {{ list.calculation.customer_company || list.calculation.customer_name }}
                        </Link>
                    </span>
                </p>
            </div>

            <Dropdown align="right" width="48" content-classes="py-1 bg-white">
                <template #trigger>
                    <button
                        type="button"
                        class="flex h-10 w-10 items-center justify-center rounded-full text-gray-400 hover:bg-gray-50 hover:text-gray-600 transition-colors"
                        title="Akce seznamu"
                        aria-label="Akce seznamu"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" viewBox="0 0 24 24" fill="currentColor">
                            <circle cx="5" cy="12" r="1.8" /><circle cx="12" cy="12" r="1.8" /><circle cx="19" cy="12" r="1.8" />
                        </svg>
                    </button>
                </template>
                <template #content>
                    <button
                        v-if="!list.archived_at"
                        type="button"
                        @click="archive"
                        class="block w-full px-4 py-2 text-left text-sm font-semibold text-gray-700 hover:bg-gray-100 transition-colors"
                    >
                        Archivovat
                    </button>
                    <button
                        v-else
                        type="button"
                        @click="unarchive"
                        class="block w-full px-4 py-2 text-left text-sm font-semibold text-gray-700 hover:bg-gray-100 transition-colors"
                    >
                        Obnovit z archivu
                    </button>
                    <button
                        type="button"
                        @click="askDelete"
                        class="block w-full px-4 py-2 text-left text-sm font-semibold text-red-500 hover:bg-red-50 transition-colors"
                    >
                        Smazat
                    </button>
                </template>
            </Dropdown>
        </div>

        <div class="p-10">
            <button
                @click="openNewTodo()"
                class="mb-4 inline-flex items-center gap-2 rounded-full border-2 border-gray-100 bg-white px-5 py-2.5 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:border-brand-primary-from hover:text-brand-primary-from transition-all font-heading"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                </svg>
                Přidat úkol
            </button>

            <p v-if="list.todos.length === 0" class="text-sm font-bold text-gray-300 text-center py-6">
                Zatím žádné úkoly.
            </p>
            <div v-else class="space-y-3">
                <TodoNode
                    v-for="todo in visibleTodos"
                    :key="todo.id"
                    :todo="todo"
                    :all-todos="list.todos"
                    :users="assignableUsers"
                    @toggle="toggleTodo"
                    @assign="assignTodo"
                    @due-date="setDueDate"
                    @add-child="openNewTodo"
                    @remove="askDeleteTodo"
                />
                <button
                    v-if="doneTodos.length > DONE_PREVIEW"
                    @click="showAllDone = !showAllDone"
                    class="pt-2 text-xs font-bold text-gray-400 hover:text-brand-primary-from transition-colors"
                >
                    {{ showAllDone
                        ? 'Skrýt dokončené úkoly'
                        : `+${doneTodos.length - DONE_PREVIEW} ${doneLabel(doneTodos.length - DONE_PREVIEW)}` }}
                </button>
            </div>
        </div>

        <!-- New todo modal -->
        <Modal :show="newTodo.show" :title="newTodo.parent ? 'Nový podúkol' : 'Nový úkol'" @close="newTodo.show = false">
            <template #content>
                <p v-if="newTodo.parent" class="mb-6 text-xs font-bold text-gray-400">pod „{{ newTodo.parent.name }}“</p>
                <div class="space-y-6">
                    <div>
                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Název <span class="text-red-500">*</span></label>
                        <input
                            v-model="todoForm.name"
                            type="text"
                            class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                            placeholder="Co je potřeba udělat?"
                            @keyup.enter="submitTodo"
                        >
                        <p v-if="todoForm.errors.name" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ todoForm.errors.name }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Odhad (dny)</label>
                            <input
                                v-model.number="todoForm.days"
                                type="number"
                                min="0"
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                            >
                        </div>
                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Termín</label>
                            <input
                                v-model="todoForm.due_date"
                                type="date"
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                            >
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <button
                    @click="submitTodo"
                    :disabled="todoForm.processing"
                    class="brand-gradient px-8 py-3 rounded-full font-bold text-white shadow-brand transition-all font-heading disabled:opacity-50"
                >
                    Přidat
                </button>
                <button @click="newTodo.show = false" class="px-6 py-3 rounded-full text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-gray-600 transition-colors">
                    Zrušit
                </button>
            </template>
        </Modal>

        <!-- Delete todolist modal -->
        <Modal :show="deleteList.show" title="Smazat seznam úkolů" @close="deleteList.show = false">
            <template #content>
                <p class="text-sm font-medium leading-relaxed text-gray-500">
                    Opravdu chcete smazat seznam „{{ list.name }}“ včetně všech jeho úkolů ({{ list.todos.length }})?
                </p>
                <label v-if="reports.length" class="mt-6 flex cursor-pointer items-start gap-3 rounded-2xl bg-gray-50 p-5">
                    <input
                        type="checkbox"
                        v-model="deleteList.deleteReports"
                        class="mt-0.5 h-5 w-5 shrink-0 rounded-lg border-gray-300 text-red-500 focus:ring-red-500"
                    >
                    <span>
                        <span class="block text-sm font-black text-gray-800">Smazat i vykázané časy</span>
                        <span class="block text-xs font-semibold text-gray-400">
                            K úkolům je vykázáno {{ reports.length }} {{ reports.length === 1 ? 'záznam' : 'záznamů' }} ({{ formatMinutes(reportedMinutes) }}).
                            Bez zaškrtnutí zůstanou v rozpisu k fakturaci.
                            <template v-if="invoicedCount">Již vyfakturované časy ({{ invoicedCount }}) zůstanou vždy.</template>
                        </span>
                    </span>
                </label>
            </template>
            <template #footer>
                <button
                    @click="submitDelete"
                    :disabled="deleteList.processing"
                    class="rounded-2xl bg-red-500 px-8 py-3 text-xs font-black uppercase tracking-widest text-white shadow-lg shadow-red-500/20 hover:bg-red-600 transition-all font-heading disabled:opacity-50"
                >
                    Smazat seznam
                </button>
                <button @click="deleteList.show = false" class="px-6 py-3 rounded-full text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-gray-600 transition-colors">
                    Zrušit
                </button>
            </template>
        </Modal>

        <ConfirmModal
            :show="confirmTodo.show"
            title="Smazat úkol"
            :message="`Opravdu chcete smazat úkol &quot;${confirmTodo.item?.name}&quot;? Případné podúkoly zůstanou v seznamu.`"
            @close="confirmTodo.show = false"
            @confirm="submitDeleteTodo"
        />
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import Dropdown from './Dropdown.vue'
import Modal from './Modal.vue'
import ConfirmModal from './ConfirmModal.vue'
import TodoNode from './TodoNode.vue'
import { formatMinutes } from '../utils/billing'

const props = defineProps({
    list: Object,
    assignableUsers: Array,
    // The list's own page shows its name as a plain heading.
    detail: Boolean,
})

// Done todos sink to the bottom, newest first, and only the first few show until expanded.
const DONE_PREVIEW = 2
const showAllDone = ref(false)

const rootTodos = computed(() => props.list.todos.filter(t => t.parent_id === null))
const doneTodos = computed(() => rootTodos.value
    .filter(t => t.is_done)
    .sort((a, b) => (b.completed_at || '').localeCompare(a.completed_at || '')))
const visibleTodos = computed(() => [
    ...rootTodos.value.filter(t => !t.is_done),
    ...(showAllDone.value || props.detail ? doneTodos.value : doneTodos.value.slice(0, DONE_PREVIEW)),
])

const doneLabel = (count) => {
    if (count === 1) return 'dokončený úkol'
    if (count >= 2 && count <= 4) return 'dokončené úkoly'
    return 'dokončených úkolů'
}

// --- Todo creation ---
const newTodo = reactive({ show: false, parent: null })
const todoForm = useForm({ name: '', days: 0, due_date: '', parent_id: null })

const openNewTodo = (parent = null) => {
    newTodo.parent = parent
    todoForm.reset()
    todoForm.parent_id = parent?.id ?? null
    newTodo.show = true
}

const submitTodo = () => {
    todoForm.post(`/todolists/${props.list.id}/todos`, {
        preserveScroll: true,
        onSuccess: () => {
            todoForm.reset()
            newTodo.show = false
        },
    })
}

// --- Todo mutations ---
const toggleTodo = (todo) => router.patch(`/todos/${todo.id}`, { is_done: !todo.is_done }, { preserveScroll: true })
const assignTodo = ({ todo, userId }) => router.patch(`/todos/${todo.id}`, { assigned_user_id: userId }, { preserveScroll: true })
const setDueDate = ({ todo, dueDate }) => router.patch(`/todos/${todo.id}`, { due_date: dueDate }, { preserveScroll: true })

const confirmTodo = reactive({ show: false, item: null })
const askDeleteTodo = (todo) => {
    confirmTodo.item = todo
    confirmTodo.show = true
}
const submitDeleteTodo = () => {
    router.delete(`/todos/${confirmTodo.item.id}`, {
        preserveScroll: true,
        onSuccess: () => confirmTodo.show = false,
    })
}

// --- List actions ---
const archive = () => router.post(`/todolists/${props.list.id}/archive`, {}, { preserveScroll: true })
const unarchive = () => router.delete(`/todolists/${props.list.id}/archive`, { preserveScroll: true })

const reports = computed(() => props.list.todos.flatMap(t => t.work_reports || []))
const reportedMinutes = computed(() => reports.value.reduce((sum, r) => sum + r.minutes, 0))
const invoicedCount = computed(() => reports.value.filter(r => r.invoice_id).length)

const deleteList = reactive({ show: false, deleteReports: false, processing: false })

const askDelete = () => {
    deleteList.deleteReports = false
    deleteList.show = true
}

const submitDelete = () => {
    router.delete(`/todolists/${props.list.id}`, {
        data: { delete_work_reports: deleteList.deleteReports },
        preserveScroll: true,
        onStart: () => deleteList.processing = true,
        onFinish: () => deleteList.processing = false,
        onSuccess: () => deleteList.show = false,
    })
}
</script>
