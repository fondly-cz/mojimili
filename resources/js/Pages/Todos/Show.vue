<template>
    <Layout>
        <Breadcrumbs
            :items="[
                { label: 'Nástěnka', href: '/' },
                { label: 'Projekty', href: '/projects' },
                { label: project.name, href: `/projects/${project.id}` },
                { label: todo.todolist.name, href: `/todolists/${todo.todolist.id}` },
            ]"
        />

        <div class="mb-8 flex flex-col md:flex-row md:items-start justify-between gap-6">
            <div class="flex items-start gap-4 min-w-0 grow">
                <input
                    type="checkbox"
                    :checked="todo.is_done"
                    @click.prevent="toggleDone"
                    class="mt-3 h-6 w-6 shrink-0 rounded-lg border-gray-300 text-brand-primary-from focus:ring-brand-primary-from cursor-pointer"
                    title="Hotovo"
                >
                <div class="min-w-0 grow">
                    <input
                        v-model="name"
                        :readonly="isCustomer"
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
            <div v-if="!isCustomer" class="flex shrink-0 gap-3">
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
                <!-- Subtasks -->
                <section :class="cardClass">
                    <fieldset :disabled="isCustomer"><TodoSubtasks :todo="todo" :heading-class="headingClass" /></fieldset>
                </section>

                <!-- Discussion; the description is its first post. -->
                <section :class="cardClass">
                    <h2 :class="headingClass" class="mb-4">Diskuse ({{ postCount }})</h2>
                    <TodoComments :todo="todo" :mention-users="project.members" @open-attachment="openAttachment" />
                </section>

                <ActivityFeed :url="`/todos/${todo.id}/activity`" title="Historie úkolu" />
            </div>

            <div class="space-y-8">
                <!-- Details -->
                <fieldset :disabled="isCustomer" class="min-w-0">
                <section :class="cardClass" class="space-y-5">
                    <h2 :class="headingClass">Detaily</h2>
                    <div>
                        <label :class="labelClass">Řešitel</label>
                        <ChoicesSelect
                            :model-value="todo.assigned_user_id || ''"
                            :options="[{ value: '', label: 'Nepřiřazeno' }, ...assignableUsers.map(user => ({ value: user.id, label: user.name, avatar: user.avatar ?? null }))]"
                            :disabled="isCustomer"
                            @update:model-value="value => update({ assigned_user_id: value || null })"
                        />
                        <p v-if="$page.props.errors.assigned_user_id" class="mt-1 text-xs text-red-500 font-bold">{{ $page.props.errors.assigned_user_id }}</p>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label :class="labelClass">Termín</label>
                            <input
                                type="date"
                                :value="todo.due_date ? todo.due_date.substring(0, 10) : ''"
                                @change="e => update({ due_date: e.target.value || null, ...(e.target.value ? {} : { due_time: null }) })"
                                :class="inputClass"
                            >
                        </div>
                        <div>
                            <label :class="labelClass">Čas</label>
                            <input
                                type="time"
                                :value="todo.due_time || ''"
                                :disabled="!todo.due_date"
                                @change="e => update({ due_time: e.target.value || null })"
                                :class="inputClass"
                                class="disabled:opacity-40"
                            >
                        </div>
                        <div>
                            <label :class="labelClass">Odhad času (h)</label>
                            <input
                                type="number"
                                min="0"
                                step="0.25"
                                :value="todo.estimated_minutes != null ? todo.estimated_minutes / 60 : ''"
                                @change="e => update({ estimated_minutes: e.target.value === '' ? null : Math.round(Number(e.target.value) * 60) })"
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
                    <div>
                        <label :class="labelClass">Nadřazený úkol</label>
                        <select :value="todo.parent_id || ''" @change="e => update({ parent_id: e.target.value ? Number(e.target.value) : null })" :class="inputClass">
                            <option value="">— samostatný úkol —</option>
                            <option v-for="option in parentOptions" :key="option.id" :value="option.id">{{ option.label }}</option>
                        </select>
                        <p v-if="$page.props.errors.parent_id" class="mt-1 text-xs text-red-500 font-bold">{{ $page.props.errors.parent_id }}</p>
                    </div>
                    <div>
                        <label :class="labelClass">Priorita</label>
                        <TodoPriority :model-value="todo.priority" :disabled="isCustomer" @update:model-value="value => update({ priority: value })" />
                    </div>
                    <div>
                        <label :class="labelClass">Štítky</label>
                        <TodoLabels :todo="todo" :labels="labels" :input-class="inputClass" />
                    </div>
                    <div>
                        <label :class="labelClass">Sledující</label>
                        <ChoicesSelect
                            :model-value="(todo.watchers || []).map(user => user.id)"
                            :options="project.members.map(user => ({ value: user.id, label: user.name, avatar: user.avatar ?? null }))"
                            :disabled="isCustomer"
                            multiple
                            placeholder="Nikdo nesleduje"
                            @update:model-value="ids => update({ watcher_ids: ids })"
                        />
                    </div>
                    <p v-if="todo.creator" class="text-xs font-bold text-gray-400">
                        Založil(a) {{ todo.creator.name }} {{ new Date(todo.created_at).toLocaleString('cs-CZ') }}
                    </p>
                    <p v-if="todo.completed_at" class="text-xs font-bold text-gray-400">
                        Dokončeno {{ new Date(todo.completed_at).toLocaleString('cs-CZ') }}<span v-if="todo.completer"> · {{ todo.completer.name }}</span>
                    </p>
                    <div>
                        <label :class="labelClass">Opakování</label>
                        <TodoRecurrence :todo="todo" />
                    </div>
                </section>
                </fieldset>
            </div>
        </div>

        <!-- Work reports -->
        <section v-if="canReportTime" :class="cardClass" class="mt-8">
            <h2 :class="headingClass">Výkazy práce</h2>
            <p v-if="todo.spent_minutes != null" class="mt-3 text-sm font-bold text-gray-500">
                Celkem spotřebováno {{ formatMinutes(todo.spent_minutes) }}<span v-if="todo.estimated_minutes"> z odhadu {{ formatMinutes(todo.estimated_minutes) }}</span>
            </p>
            <TodoWorkReports
                :todo="todo"
                :users="users"
                :default-rate="project.hourly_rate"
                :user-rates="userRates"
                expanded
            />
        </section>

        <AttachmentLightbox v-model="galleryIndex" :items="galleryItems" />

        <ConfirmModal
            :show="noRightModal"
            title="Úkol nelze uzavřít"
            message="Úkoly smí uzavírat jen manažer projektu."
            confirm-button="Rozumím"
            cancel-button=""
            @close="noRightModal = false"
            @confirm="noRightModal = false"
        />

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
import { Link, router, usePage } from '@inertiajs/vue3'
import '@toast-ui/editor/dist/toastui-editor.css'
import AttachmentLightbox from '../../Components/AttachmentLightbox.vue'
import ChoicesSelect from '../../Components/ChoicesSelect.vue'
import Layout from '../../Components/Layout.vue'
import Breadcrumbs from '../../Components/Breadcrumbs.vue'
import ConfirmModal from '../../Components/ConfirmModal.vue'
import TodoComments from '../../Components/TodoComments.vue'
import TodoPriority from '../../Components/TodoPriority.vue'
import TodoLabels from '../../Components/TodoLabels.vue'
import TodoRecurrence from '../../Components/TodoRecurrence.vue'
import TodoSubtasks from '../../Components/TodoSubtasks.vue'
import ActivityFeed from '../../Components/ActivityFeed.vue'
import TodoWorkReports from '../../Components/TodoWorkReports.vue'
import { inlineImages } from '../../utils/files'
import { formatMinutes } from '../../utils/billing'

const props = defineProps({
    todo: Object,
    users: Array,
    labels: Array,
    listTodos: Array,
})

// Todos of the same list in tree order, without this todo and its own subtasks.
const parentOptions = computed(() => {
    const options = []
    const walk = (parentId, depth) => {
        for (const item of props.listTodos.filter(t => t.parent_id === parentId)) {
            if (item.id === props.todo.id) continue
            options.push({ id: item.id, label: `${'— '.repeat(depth)}${item.name}` })
            walk(item.id, depth + 1)
        }
    }
    walk(null, 0)

    return options
})

const cardClass = 'bg-white rounded-[2rem] border border-gray-50 p-8 shadow-sm'
const headingClass = 'text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading'
const labelClass = 'block text-[9px] font-black text-gray-400 uppercase tracking-widest mb-1'
const inputClass = 'block w-full px-3 py-2 bg-gray-50 border-gray-100 rounded-xl text-xs font-semibold text-gray-700 focus:ring-brand-primary-from focus:border-brand-primary-from'

const project = computed(() => props.todo.todolist.project)

// A customer reads and comments; a collaborator reports time but cannot see money or other people's time.
const role = computed(() => usePage().props.auth.user?.role)
const isCustomer = computed(() => role.value === 'customer')
const canReportTime = computed(() => !isCustomer.value)

// A todo can be handed only to a person from the project; someone removed since stays selected.
const assignableUsers = computed(() => {
    const { assignee } = props.todo
    const members = project.value.members

    return assignee && !members.some(member => member.id === assignee.id) ? [...members, assignee] : members
})

// Mirrors Project::rateFor(): the person's rate in the project, then the project default.
const userRates = computed(() => Object.fromEntries(
    (project.value.user_rates || []).map(user => [user.id, user.pivot.hourly_rate])
))

// Only administrators and managers close tasks; anyone else gets told so instead.
const canClose = computed(() => ['admin', 'manager'].includes(role.value))
const noRightModal = ref(false)
const toggleDone = () => {
    if (!canClose.value) {
        noRightModal.value = true
        return
    }
    update({ is_done: !props.todo.is_done })
}

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

const attachments = computed(() => props.todo.attachments || [])

const postCount = computed(() =>
    (props.todo.comments || []).length + (props.todo.description || attachments.value.length ? 1 : 0)
)

// One gallery in page order: the description (images in its text, then its files), then every comment the same way.
const galleryItems = computed(() => [
    ...inlineImages(props.todo.description),
    ...attachments.value,
    ...(props.todo.comments || []).flatMap(comment => [...inlineImages(comment.body), ...comment.attachments]),
])
const galleryIndex = ref(null)

const openAttachment = (attachment) => {
    const index = galleryItems.value.findIndex(item => item.url === attachment.url)
    galleryIndex.value = index === -1 ? null : index
}

const confirmDelete = ref(false)

// The server redirects back to the project once the todo is gone.
const destroy = () => router.delete(`/todos/${props.todo.id}`)
</script>
