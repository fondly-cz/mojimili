<template>
    <Layout>
        <Breadcrumbs
            :items="[
                { label: 'Nástěnka', href: '/' },
                { label: 'Projekty', href: '/projects' },
            ]"
        />

        <div class="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <div class="flex items-center gap-3">
                    <h1 class="text-4xl font-extrabold text-gray-900 font-heading tracking-tight">{{ project.name }}</h1>
                    <span
                        class="inline-flex items-center px-4 py-1.5 text-[10px] font-black uppercase tracking-wider rounded-full shadow-sm"
                        :class="statusStyles[project.status]"
                    >
                        {{ statusLabels[project.status] || project.status }}
                    </span>
                </div>
                <p class="text-gray-500 mt-2 font-medium">
                    <Link v-if="project.company" :href="`/companies/${project.company.id}`" class="font-bold hover:text-brand-primary-from transition-colors">
                        {{ project.company.name }}
                    </Link>
                    <span v-else>Bez navázané firmy</span>
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <button
                    @click="openFromCalculation"
                    class="inline-flex items-center gap-2 bg-white border-2 border-gray-100 px-6 py-4 rounded-full font-bold text-gray-500 hover:text-brand-primary-from hover:border-brand-primary-from transition-all font-heading uppercase tracking-widest text-[10px]"
                >
                    Z kalkulace
                </button>
                <button
                    @click="newList.show = true"
                    class="inline-flex items-center gap-2 brand-gradient px-8 py-4 rounded-full font-bold text-white shadow-brand hover:shadow-brand-lg transition-all hover:-translate-y-1 font-heading"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Nový seznam
                </button>
                <Link
                    :href="`/projects/${project.id}/edit`"
                    class="inline-flex items-center gap-2 bg-white border-2 border-gray-100 px-6 py-4 rounded-full font-bold text-gray-400 hover:text-gray-600 hover:border-gray-200 transition-all font-heading uppercase tracking-widest text-[10px]"
                >
                    Upravit
                </Link>
            </div>
        </div>

        <p v-if="project.description" class="mb-8 max-w-4xl text-sm font-medium leading-relaxed text-gray-500 bg-white rounded-[2rem] border border-gray-50 p-8 shadow-sm">
            {{ project.description }}
        </p>

        <!-- Progress summary -->
        <div class="mb-8 grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div class="bg-white rounded-[2rem] border border-gray-50 p-8 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Seznamy</p>
                <p class="mt-2 text-3xl font-black text-gray-900 font-heading">{{ project.todolists.length }}</p>
            </div>
            <div class="bg-white rounded-[2rem] border border-gray-50 p-8 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Hotové úkoly</p>
                <p class="mt-2 text-3xl font-black brand-text-gradient font-heading">{{ doneCount }} / {{ totalCount }}</p>
            </div>
            <div class="bg-white rounded-[2rem] border border-gray-50 p-8 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Odhad práce</p>
                <p class="mt-2 text-3xl font-black text-gray-900 font-heading">{{ totalDays }} dní</p>
            </div>
        </div>

        <!-- Todolists -->
        <div v-if="project.todolists.length === 0" class="bg-white rounded-[2.5rem] border border-gray-50 p-16 text-center shadow-sm">
            <p class="text-sm font-bold text-gray-400">Projekt zatím nemá žádný seznam úkolů.</p>
            <button @click="openFromCalculation" class="mt-3 text-sm font-black brand-text-gradient">
                Vytvořit seznam z kalkulace
            </button>
        </div>

        <div v-else class="space-y-8">
            <div
                v-for="list in project.todolists"
                :key="list.id"
                class="bg-white rounded-[2.5rem] border border-gray-50 shadow-sm overflow-hidden"
            >
                <div class="flex flex-wrap items-center justify-between gap-4 border-b border-gray-50 px-10 py-6">
                    <div>
                        <h3 class="text-lg font-black text-gray-900 font-heading">{{ list.name }}</h3>
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
                    <div class="flex items-center gap-3">
                        <button
                            @click="openNewTodo(list)"
                            class="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-brand-primary-from transition-colors"
                        >
                            + Úkol
                        </button>
                        <button
                            @click="deleteList(list)"
                            class="text-[10px] font-black uppercase tracking-widest text-gray-300 hover:text-red-500 transition-colors"
                        >
                            Smazat seznam
                        </button>
                    </div>
                </div>

                <div class="p-10">
                    <p v-if="list.todos.length === 0" class="text-sm font-bold text-gray-300 text-center py-6">
                        Zatím žádné úkoly.
                    </p>
                    <div v-else class="space-y-3">
                        <TodoNode
                            v-for="todo in rootTodos(list)"
                            :key="todo.id"
                            :todo="todo"
                            :all-todos="list.todos"
                            :users="users"
                            @toggle="toggleTodo"
                            @assign="assignTodo"
                            @due-date="setDueDate"
                            @add-child="openNewTodo(list, $event)"
                            @remove="deleteTodo"
                        />
                    </div>
                </div>
            </div>
        </div>

        <!-- New todolist modal -->
        <Modal :show="newList.show" title="Nový seznam úkolů" @close="newList.show = false">
            <template #content>
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Název <span class="text-red-500">*</span></label>
                <input
                    v-model="listForm.name"
                    type="text"
                    class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                    placeholder="Např. Etapa 1"
                    @keyup.enter="submitList"
                >
                <p v-if="listForm.errors.name" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ listForm.errors.name }}</p>
            </template>
            <template #footer>
                <button
                    @click="submitList"
                    :disabled="listForm.processing"
                    class="brand-gradient px-8 py-3 rounded-full font-bold text-white shadow-brand transition-all font-heading disabled:opacity-50"
                >
                    Vytvořit
                </button>
                <button @click="newList.show = false" class="px-6 py-3 rounded-full text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-gray-600 transition-colors">
                    Zrušit
                </button>
            </template>
        </Modal>

        <!-- New todo modal -->
        <Modal
            :show="newTodo.show"
            :title="newTodo.parent ? 'Nový podúkol' : 'Nový úkol'"
            @close="newTodo.show = false"
        >
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

        <CreateTodolistFromCalculationModal
            :show="fromCalculation.show"
            :calculations="calculations"
            :project-id="project.id"
            @close="fromCalculation.show = false"
        />

        <ConfirmModal
            :show="confirm.show"
            :title="confirm.title"
            :message="confirm.message"
            @close="confirm.show = false"
            @confirm="executeConfirm"
        />
    </Layout>
</template>

<script setup>
import { computed, reactive } from 'vue'
import { Link, router, useForm } from '@inertiajs/vue3'
import Layout from '../../Components/Layout.vue'
import Breadcrumbs from '../../Components/Breadcrumbs.vue'
import Modal from '../../Components/Modal.vue'
import ConfirmModal from '../../Components/ConfirmModal.vue'
import TodoNode from '../../Components/TodoNode.vue'
import CreateTodolistFromCalculationModal from '../../Components/CreateTodolistFromCalculationModal.vue'

const props = defineProps({
    project: Object,
    users: Array,
    calculations: Array,
})

const statusLabels = {
    active: 'Aktivní',
    on_hold: 'Pozastavený',
    done: 'Dokončený',
    archived: 'Archivovaný',
}

const statusStyles = {
    active: 'bg-green-500 text-white shadow-green-100',
    on_hold: 'bg-amber-500 text-white shadow-amber-100',
    done: 'bg-brand-accent text-white shadow-blue-100',
    archived: 'bg-gray-400 text-white shadow-gray-100',
}

const allTodos = computed(() => props.project.todolists.flatMap(l => l.todos))
const totalCount = computed(() => allTodos.value.length)
const doneCount = computed(() => allTodos.value.filter(t => t.is_done).length)
const totalDays = computed(() => allTodos.value.reduce((sum, t) => sum + (t.days || 0), 0))

const rootTodos = (list) => list.todos.filter(t => t.parent_id === null)

// --- Todolist creation ---
const newList = reactive({ show: false })
const listForm = useForm({ name: '' })

const submitList = () => {
    listForm.post(`/projects/${props.project.id}/todolists`, {
        preserveScroll: true,
        onSuccess: () => {
            listForm.reset()
            newList.show = false
        },
    })
}

// --- Todo creation ---
const newTodo = reactive({ show: false, list: null, parent: null })
const todoForm = useForm({ name: '', days: 0, due_date: '', parent_id: null })

const openNewTodo = (list, parent = null) => {
    newTodo.list = list
    newTodo.parent = parent
    todoForm.reset()
    todoForm.parent_id = parent?.id ?? null
    newTodo.show = true
}

const submitTodo = () => {
    todoForm.post(`/todolists/${newTodo.list.id}/todos`, {
        preserveScroll: true,
        onSuccess: () => {
            todoForm.reset()
            newTodo.show = false
        },
    })
}

// --- Todo mutations ---
const toggleTodo = (todo) => {
    router.patch(`/todos/${todo.id}`, { is_done: !todo.is_done }, { preserveScroll: true })
}

const assignTodo = ({ todo, userId }) => {
    router.patch(`/todos/${todo.id}`, { assigned_user_id: userId }, { preserveScroll: true })
}

const setDueDate = ({ todo, dueDate }) => {
    router.patch(`/todos/${todo.id}`, { due_date: dueDate }, { preserveScroll: true })
}

// --- From calculation ---
const fromCalculation = reactive({ show: false })

const openFromCalculation = () => {
    fromCalculation.show = true
}

// --- Deletion ---
const confirm = reactive({ show: false, title: '', message: '', type: null, item: null })

const deleteList = (list) => {
    confirm.title = 'Smazat seznam úkolů'
    confirm.message = `Opravdu chcete smazat seznam "${list.name}" včetně všech jeho úkolů?`
    confirm.type = 'list'
    confirm.item = list
    confirm.show = true
}

const deleteTodo = (todo) => {
    confirm.title = 'Smazat úkol'
    confirm.message = `Opravdu chcete smazat úkol "${todo.name}"? Případné podúkoly zůstanou v seznamu.`
    confirm.type = 'todo'
    confirm.item = todo
    confirm.show = true
}

const executeConfirm = () => {
    const url = confirm.type === 'list'
        ? `/todolists/${confirm.item.id}`
        : `/todos/${confirm.item.id}`

    router.delete(url, {
        preserveScroll: true,
        onSuccess: () => confirm.show = false,
    })
}
</script>
