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
                    <span v-if="project.due_date"> · termín {{ new Date(project.due_date).toLocaleDateString('cs-CZ') }}</span>
                </p>
            </div>
            <div class="flex flex-wrap gap-3">
                <button
                    v-if="isStaff"
                    @click="openFromCalculation"
                    class="inline-flex items-center gap-2 bg-white border-2 border-gray-100 px-6 py-4 rounded-full font-bold text-gray-500 hover:text-brand-primary-from hover:border-brand-primary-from transition-all font-heading uppercase tracking-widest text-[10px]"
                >
                    Z kalkulace
                </button>
                <button
                    v-if="!isCustomer"
                    @click="newList.show = true"
                    class="inline-flex items-center gap-2 brand-gradient px-8 py-4 rounded-full font-bold text-white shadow-brand hover:shadow-brand-lg transition-all hover:-translate-y-1 font-heading"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                    </svg>
                    Nový seznam
                </button>
                <Link
                    v-if="isStaff"
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

        <nav class="mb-8 flex gap-1 overflow-x-auto border-b border-gray-100" aria-label="Sekce projektu">
            <button
                v-for="item in tabs"
                :key="item.key"
                type="button"
                @click="selectTab(item.key)"
                class="-mb-px whitespace-nowrap border-b-2 px-5 py-3 text-sm font-bold font-heading transition-colors"
                :class="tab === item.key
                    ? 'border-brand-primary-from text-brand-primary-from'
                    : 'border-transparent text-gray-400 hover:text-gray-600'"
            >
                {{ item.label }}
                <span v-if="item.count !== undefined" class="ml-1 text-xs font-black text-gray-300">{{ item.count }}</span>
            </button>
        </nav>

        <ProjectMembers
            v-if="tab === 'members'"
            :project-id="project.id"
            :members="project.members"
            :known-people="knownPeople"
            :invitations="project.invitations"
            :todolists="project.todolists"
        />

        <ActivityFeed v-if="tab === 'activity'" :url="`/projects/${project.id}/activity`" title="Historie projektu" />

        <ProjectDocuments v-if="tab === 'files'" :project-id="project.id" :documents="project.documents" />

        <ProjectNotes v-if="tab === 'notes'" :project-id="project.id" :notes="project.notes" />

        <ProjectReports v-if="tab === 'reports'" :todolists="project.todolists" />

        <template v-if="tab === 'todos'">
        <!-- Progress summary -->
        <div class="mb-8 grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div class="bg-white rounded-[2rem] border border-gray-50 p-8 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Seznamy</p>
                <p class="mt-2 text-3xl font-black text-gray-900 font-heading">{{ activeLists.length }}</p>
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

        <!-- Billing summary -->
        <div v-if="isStaff" class="mb-8 grid grid-cols-1 sm:grid-cols-3 gap-6">
            <div class="bg-white rounded-[2rem] border border-gray-50 p-8 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Vykázáno</p>
                <p class="mt-2 text-3xl font-black text-gray-900 font-heading">{{ formatMinutes(billing.minutes) }}</p>
                <p class="mt-1 text-xs font-bold text-gray-400">
                    {{ formatCurrency(billing.amount) }}
                    · výchozí sazba {{ project.hourly_rate ? `${formatCurrency(project.hourly_rate)}/h` : 'nenastavena' }}
                </p>
                <p v-if="project.budget || project.budget_minutes" class="mt-1 text-xs font-bold" :class="overBudget ? 'text-red-500' : 'text-gray-400'">
                    Rozpočet
                    <span v-if="project.budget">{{ formatCurrency(project.budget) }}</span>
                    <span v-if="project.budget && project.budget_minutes"> / </span>
                    <span v-if="project.budget_minutes">{{ formatMinutes(project.budget_minutes) }}</span>
                </p>
            </div>
            <div class="bg-white rounded-[2rem] border border-gray-50 p-8 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Vyfakturováno</p>
                <p class="mt-2 text-3xl font-black text-green-600 font-heading">{{ formatCurrency(billing.invoicedAmount) }}</p>
                <p class="mt-1 text-xs font-bold text-gray-400">{{ formatMinutes(billing.invoicedMinutes) }}</p>
            </div>
            <div class="bg-white rounded-[2rem] border border-gray-50 p-8 shadow-sm">
                <p class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">K fakturaci</p>
                <p class="mt-2 text-3xl font-black text-amber-500 font-heading">{{ formatCurrency(billing.amount - billing.invoicedAmount) }}</p>
                <Link
                    :href="`/billing?project_id=${project.id}`"
                    class="mt-1 inline-block text-xs font-black brand-text-gradient"
                >
                    Vyfakturovat →
                </Link>
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
            <TodolistPanel
                v-for="list in activeLists"
                :key="list.id"
                :list="list"
                :assignable-users="assignableUsers"
            />

            <div v-if="archivedLists.length">
                <button
                    @click="showArchived = !showArchived"
                    class="text-xs font-bold text-gray-400 hover:text-brand-primary-from transition-colors"
                >
                    {{ showArchived ? 'Skrýt archivované seznamy' : `Archivované seznamy (${archivedLists.length})` }}
                </button>
                <div v-if="showArchived" class="mt-4 space-y-8">
                    <TodolistPanel
                        v-for="list in archivedLists"
                        :key="list.id"
                        :list="list"
                        :assignable-users="assignableUsers"
                    />
                </div>
            </div>
        </div>
        </template>

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

        <CreateTodolistFromCalculationModal
            :show="fromCalculation.show"
            :calculations="calculations"
            :project-id="project.id"
            @close="fromCalculation.show = false"
        />

    </Layout>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { Link, useForm, usePage } from '@inertiajs/vue3'
import Layout from '../../Components/Layout.vue'
import Breadcrumbs from '../../Components/Breadcrumbs.vue'
import Modal from '../../Components/Modal.vue'
import TodolistPanel from '../../Components/TodolistPanel.vue'
import ActivityFeed from '../../Components/ActivityFeed.vue'
import ProjectDocuments from '../../Components/ProjectDocuments.vue'
import ProjectNotes from '../../Components/ProjectNotes.vue'
import ProjectReports from '../../Components/ProjectReports.vue'
import ProjectMembers from '../../Components/ProjectMembers.vue'
import CreateTodolistFromCalculationModal from '../../Components/CreateTodolistFromCalculationModal.vue'
import { formatCurrency, formatMinutes, reportAmount } from '../../utils/billing'

const isCustomer = computed(() => usePage().props.auth.user?.role === 'customer')
const isStaff = computed(() => ['admin', 'manager'].includes(usePage().props.auth.user?.role))

const props = defineProps({
    project: Object,
    knownPeople: Array,
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

// Tasks open by default; the chosen tab lives in ?tab= so a reload or shared link keeps it.
// A customer sees neither the people, the reported time nor the history of the project.
const tabs = computed(() => [
    { key: 'todos', label: 'Úkoly' },
    { key: 'notes', label: 'Poznámky', count: props.project.notes.length },
    { key: 'members', label: 'Uživatelé', count: props.project.members.length, hiddenFromCustomer: true },
    { key: 'files', label: 'Soubory', count: props.project.documents.length },
    { key: 'reports', label: 'Výkazy', hiddenFromCustomer: true },
    { key: 'activity', label: 'Aktivity', hiddenFromCustomer: true },
].filter(item => !(isCustomer.value && item.hiddenFromCustomer)))

const initialTab = new URLSearchParams(window.location.search).get('tab')
const tab = ref(tabs.value.some(item => item.key === initialTab) ? initialTab : 'todos')

const selectTab = (key) => {
    tab.value = key
    const url = new URL(window.location.href)
    if (key === 'todos') {
        url.searchParams.delete('tab')
    } else {
        url.searchParams.set('tab', key)
    }
    window.history.replaceState(window.history.state, '', url)
}

// A todo can be handed only to a person from the project; someone removed since stays on the todos they hold.
const assignableUsers = computed(() => {
    const memberIds = new Set(props.project.members.map(member => member.id))
    const former = allTodos.value
        .map(todo => todo.assignee)
        .filter(assignee => assignee && !memberIds.has(assignee.id))

    return [...props.project.members, ...new Map(former.map(user => [user.id, user])).values()]
})

const allTodos = computed(() => props.project.todolists.flatMap(l => l.todos))

const totalCount = computed(() => allTodos.value.length)
const doneCount = computed(() => allTodos.value.filter(t => t.is_done).length)
const totalDays = computed(() => allTodos.value.reduce((sum, t) => sum + (t.days || 0), 0))

const billing = computed(() => {
    const reports = allTodos.value.flatMap(t => t.work_reports || [])
    const invoiced = reports.filter(r => r.invoice_id)

    return {
        minutes: reports.reduce((sum, r) => sum + r.minutes, 0),
        amount: reports.reduce((sum, r) => sum + reportAmount(r), 0),
        invoicedMinutes: invoiced.reduce((sum, r) => sum + r.minutes, 0),
        invoicedAmount: invoiced.reduce((sum, r) => sum + reportAmount(r), 0),
    }
})

const overBudget = computed(() =>
    (props.project.budget && billing.value.amount > Number(props.project.budget))
    || (props.project.budget_minutes && billing.value.minutes > props.project.budget_minutes)
)

const activeLists = computed(() => props.project.todolists.filter(l => !l.archived_at))
const archivedLists = computed(() => props.project.todolists.filter(l => l.archived_at))
const showArchived = ref(false)

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

// --- From calculation ---
const fromCalculation = reactive({ show: false })

const openFromCalculation = () => {
    fromCalculation.show = true
}
</script>
