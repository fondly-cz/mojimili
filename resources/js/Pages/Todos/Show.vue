<template>
    <Layout>
        <Breadcrumbs
            :items="[
                { label: 'Nástěnka', href: '/' },
                { label: 'Projekty', href: '/projects' },
                { label: project.name, href: `/projects/${project.id}` },
                { label: todo.todolist.name },
            ]"
        />

        <div class="mb-8 flex flex-col md:flex-row md:items-start justify-between gap-6">
            <div class="flex items-start gap-4 min-w-0 grow">
                <input
                    type="checkbox"
                    :checked="todo.is_done"
                    @change="update({ is_done: !todo.is_done })"
                    class="mt-3 h-6 w-6 shrink-0 rounded-lg border-gray-300 text-brand-primary-from focus:ring-brand-primary-from cursor-pointer"
                    title="Hotovo"
                >
                <div class="min-w-0 grow">
                    <input
                        v-model="name"
                        @blur="saveName"
                        @keyup.enter="$event.target.blur()"
                        class="block w-full bg-transparent border-none p-0 text-4xl font-extrabold text-gray-900 font-heading tracking-tight focus:ring-0"
                        :class="{ 'line-through text-gray-400': todo.is_done }"
                    >
                    <p v-if="todo.parent" class="mt-2 text-sm font-medium text-gray-500">
                        Podúkol úkolu
                        <Link :href="`/todos/${todo.parent.id}`" class="font-bold hover:text-brand-primary-from transition-colors">{{ todo.parent.name }}</Link>
                    </p>
                    <p v-if="$page.props.errors.name" class="mt-2 text-xs text-red-500 font-bold">{{ $page.props.errors.name }}</p>
                </div>
            </div>
            <div class="flex shrink-0 gap-3">
                <Link
                    :href="`/projects/${project.id}`"
                    class="inline-flex items-center gap-2 bg-white border-2 border-gray-100 px-6 py-4 rounded-full font-bold text-gray-400 hover:text-gray-600 hover:border-gray-200 transition-all font-heading uppercase tracking-widest text-[10px]"
                >
                    ← Projekt
                </Link>
                <button
                    type="button"
                    @click="confirmDelete = true"
                    class="inline-flex items-center gap-2 bg-white border-2 border-gray-100 px-5 py-4 rounded-full text-gray-400 hover:text-red-500 hover:border-red-100 transition-all"
                    title="Smazat úkol"
                    aria-label="Smazat úkol"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" />
                    </svg>
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <div class="lg:col-span-2 space-y-8 min-w-0">
                <!-- Description -->
                <section :class="cardClass">
                    <div class="flex items-center justify-between gap-4 mb-4">
                        <h2 :class="headingClass">Popis</h2>
                        <button
                            v-if="!editingDescription"
                            type="button"
                            @click="startDescription"
                            class="text-[10px] font-black uppercase tracking-widest text-gray-300 hover:text-brand-primary-from transition-colors"
                        >
                            {{ todo.description ? 'Upravit' : '+ Přidat popis' }}
                        </button>
                    </div>

                    <template v-if="editingDescription">
                        <RichEditor v-model="description" height="320px" placeholder="Popis úkolu..." />
                        <div class="mt-4 flex items-center gap-3">
                            <button
                                type="button"
                                @click="saveDescription"
                                class="brand-gradient px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest text-white"
                            >
                                Uložit popis
                            </button>
                            <button type="button" @click="editingDescription = false" class="text-[9px] font-black uppercase tracking-widest text-gray-400 hover:text-gray-600">Zrušit</button>
                        </div>
                    </template>
                    <!-- Sanitized on the server (RichText) before it is stored. -->
                    <div v-else-if="todo.description" class="toastui-editor-contents" v-html="todo.description"></div>
                    <p v-else class="text-sm font-bold text-gray-300">Úkol zatím nemá popis.</p>
                </section>

                <!-- Comments -->
                <section :class="cardClass">
                    <h2 :class="headingClass" class="mb-4">Komentáře ({{ (todo.comments || []).length }})</h2>
                    <TodoComments :todo="todo" />
                </section>
            </div>

            <div class="space-y-8">
                <!-- Details -->
                <section :class="cardClass" class="space-y-5">
                    <h2 :class="headingClass">Detaily</h2>
                    <div>
                        <label :class="labelClass">Řešitel</label>
                        <select :value="todo.assigned_user_id || ''" @change="e => update({ assigned_user_id: e.target.value || null })" :class="inputClass">
                            <option value="">Nepřiřazeno</option>
                            <option v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</option>
                        </select>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label :class="labelClass">Termín</label>
                            <input
                                type="date"
                                :value="todo.due_date ? todo.due_date.substring(0, 10) : ''"
                                @change="e => update({ due_date: e.target.value || null })"
                                :class="inputClass"
                            >
                        </div>
                        <div>
                            <label :class="labelClass">Odhad (dny)</label>
                            <input
                                type="number"
                                min="0"
                                :value="todo.days"
                                @change="e => update({ days: Number(e.target.value) || 0 })"
                                :class="inputClass"
                            >
                        </div>
                    </div>
                    <p v-if="todo.completed_at" class="text-xs font-bold text-gray-400">
                        Dokončeno {{ new Date(todo.completed_at).toLocaleString('cs-CZ') }}
                    </p>
                    <div>
                        <label :class="labelClass">Opakování</label>
                        <TodoRecurrence :todo="todo" />
                    </div>
                </section>

                <!-- Subtasks -->
                <section v-if="todo.children.length" :class="cardClass">
                    <h2 :class="headingClass" class="mb-4">Podúkoly</h2>
                    <ul class="space-y-2">
                        <li v-for="child in todo.children" :key="child.id">
                            <Link
                                :href="`/todos/${child.id}`"
                                class="text-sm font-bold hover:text-brand-primary-from transition-colors"
                                :class="child.is_done ? 'line-through text-gray-400' : 'text-gray-700'"
                            >
                                {{ child.name }}
                            </Link>
                        </li>
                    </ul>
                </section>
            </div>
        </div>

        <!-- Work reports -->
        <section :class="cardClass" class="mt-8">
            <h2 :class="headingClass">Výkazy práce</h2>
            <TodoWorkReports
                :todo="todo"
                :users="users"
                :default-rate="project.hourly_rate"
                :user-rates="userRates"
                expanded
            />
        </section>

        <ConfirmModal
            :show="confirmDelete"
            title="Smazat úkol"
            :message="`Opravdu chcete smazat úkol „${todo.name}“ včetně jeho výkazů a komentářů? Případné podúkoly zůstanou v seznamu.`"
            @close="confirmDelete = false"
            @confirm="destroy"
        />
    </Layout>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import '@toast-ui/editor/dist/toastui-editor.css'
import Layout from '../../Components/Layout.vue'
import Breadcrumbs from '../../Components/Breadcrumbs.vue'
import ConfirmModal from '../../Components/ConfirmModal.vue'
import RichEditor from '../../Components/RichEditor.vue'
import TodoComments from '../../Components/TodoComments.vue'
import TodoRecurrence from '../../Components/TodoRecurrence.vue'
import TodoWorkReports from '../../Components/TodoWorkReports.vue'

const props = defineProps({
    todo: Object,
    users: Array,
})

const cardClass = 'bg-white rounded-[2rem] border border-gray-50 p-8 shadow-sm'
const headingClass = 'text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading'
const labelClass = 'block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1'
const inputClass = 'block w-full px-3 py-2 bg-gray-50 border-gray-100 rounded-xl text-xs font-semibold text-gray-700 focus:ring-brand-primary-from focus:border-brand-primary-from'

const project = computed(() => props.todo.todolist.project)

// Mirrors Project::rateFor(): the person's rate in the project, then the project default.
const userRates = computed(() => Object.fromEntries(
    (project.value.user_rates || []).map(user => [user.id, user.pivot.hourly_rate])
))

const update = (data) => router.patch(`/todos/${props.todo.id}`, data, { preserveScroll: true })

const name = ref(props.todo.name)
watch(() => props.todo.name, (value) => { name.value = value })

const saveName = () => {
    const trimmed = name.value.trim()
    if (!trimmed) {
        name.value = props.todo.name
        return
    }
    if (trimmed !== props.todo.name) update({ name: trimmed })
}

const editingDescription = ref(false)
const description = ref('')

const startDescription = () => {
    description.value = props.todo.description || ''
    editingDescription.value = true
}

const saveDescription = () => {
    router.patch(`/todos/${props.todo.id}`, { description: description.value || null }, {
        preserveScroll: true,
        onSuccess: () => { editingDescription.value = false },
    })
}

const confirmDelete = ref(false)

// The server redirects back to the project once the todo is gone.
const destroy = () => router.delete(`/todos/${props.todo.id}`)
</script>
