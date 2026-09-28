<template>
    <Layout>
        <Breadcrumbs :items="[{ label: 'Nástěnka', href: '/' }]" />
        <div class="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <h1 class="text-4xl font-extrabold text-gray-900 font-heading tracking-tight">Správa uživatelů</h1>
                <p class="text-gray-500 mt-2 font-medium">Správa přístupů a rolí pro členy vašeho týmu.</p>
            </div>
            <div class="flex gap-3">
                <button
                    @click="openCreateModal"
                    class="inline-flex items-center gap-2 brand-gradient px-8 py-4 rounded-full font-bold text-white shadow-brand hover:shadow-brand-lg transition-all hover:-translate-y-1 font-heading"
                >
                    ➕ Přidat uživatele
                </button>
            </div>
        </div>

        <div v-if="$page.props.errors.user" class="mb-8 px-8 py-5 bg-red-50 border border-red-100 rounded-[2rem] text-sm font-bold text-red-600">
            {{ $page.props.errors.user }}
        </div>

        <!-- Filters -->
        <div class="mb-8 bg-white p-8 rounded-[2.5rem] shadow-sm border border-gray-50">
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-end">
                <div class="md:col-span-7">
                    <label for="search" class="block text-sm font-bold text-gray-700 ml-1 mb-2 font-heading">Vyhledávání</label>
                    <div class="relative">
                        <svg xmlns="http://www.w3.org/2000/svg" class="absolute left-4 top-1/2 -translate-y-1/2 h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                        </svg>
                        <input
                            v-model="searchForm.search"
                            @input="search"
                            type="text"
                            id="search"
                            placeholder="Hledat podle jména, emailu..."
                            class="block w-full pl-12 pr-4 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                        >
                    </div>
                </div>
                <div class="md:col-span-3">
                    <label for="role-filter" class="block text-sm font-bold text-gray-700 ml-1 mb-2 font-heading">Role</label>
                    <select
                        v-model="searchForm.role"
                        @change="search"
                        id="role-filter"
                        class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all appearance-none cursor-pointer"
                    >
                        <option value="">Všechny</option>
                        <option v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</option>
                        <option value="none">Bez role</option>
                    </select>
                </div>
                <div class="md:col-span-2">
                    <button
                        @click="clearFilters"
                        class="w-full flex items-center justify-center gap-2 px-4 py-3.5 border-2 border-gray-100 rounded-2xl text-sm font-bold text-gray-400 hover:text-brand-primary-from hover:border-brand-primary-from transition-all"
                    >
                        Resetovat
                    </button>
                </div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-50 overflow-hidden">
            <div class="overflow-x-auto min-h-[400px]">
                <table class="w-full border-collapse">
                    <thead>
                        <tr class="border-b border-gray-50">
                            <th class="px-8 py-6 text-left text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Uživatel</th>
                            <th class="px-8 py-6 text-left text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Kontakt</th>
                            <th class="px-8 py-6 text-left text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Role</th>
                            <th class="px-8 py-6 text-right text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Akce</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="user in users.data" :key="user.id" class="hover:bg-gray-50/50 transition-colors group">
                            <td class="px-8 py-6">
                                <div class="flex items-center gap-4">
                                    <div class="h-12 w-12 bg-gray-50 rounded-2xl flex items-center justify-center text-brand-primary-from font-black shadow-sm group-hover:bg-white transition-colors">
                                        {{ user.name.charAt(0) }}
                                    </div>
                                    <div>
                                        <div class="text-base font-bold text-gray-900 font-heading leading-tight">
                                            {{ user.name }}
                                            <span v-if="isCurrentUser(user)" class="ml-1 text-[10px] font-black uppercase tracking-widest text-gray-400">(vy)</span>
                                        </div>
                                        <div v-if="user.company" class="text-xs font-semibold text-gray-400 mt-1">{{ user.company }}</div>
                                        <div v-if="user.google_id" class="text-[10px] font-black text-blue-500 uppercase tracking-widest mt-1 flex items-center gap-1">
                                            <svg class="h-3 w-3" fill="currentColor" viewBox="0 0 24 24"><path d="M12.48 10.92v3.28h7.84c-.24 1.84-.9 3.03-1.63 3.96-1.07 1.07-2.52 2.23-5.26 2.23-4.38 0-7.75-3.53-7.75-7.91s3.37-7.91 7.75-7.91c2.31 0 4.1.84 5.37 2.05l2.42-2.42c-2.1-1.95-4.87-3.21-7.79-3.21-6.19 0-11.23 5.04-11.23 11.23s5.04 11.23 11.23 11.23c3.34 0 5.86-1.1 7.91-3.21 2.1-2.1 2.77-5.06 2.77-7.46 0-.71-.06-1.33-.16-1.95h-10.51z"/></svg>
                                            Google Auth
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-8 py-6">
                                <div class="text-sm font-bold text-gray-900">{{ user.email }}</div>
                                <div class="text-xs font-semibold text-gray-400 mt-1">{{ user.phone || 'Bez telefonu' }}</div>
                            </td>
                            <td class="px-8 py-6">
                                <span
                                    class="inline-flex items-center px-4 py-1.5 text-[10px] font-black uppercase tracking-wider rounded-full shadow-sm"
                                    :class="{
                                        'bg-brand-primary-from text-white': user.role === 'admin',
                                        'bg-brand-accent text-white': user.role === 'manager',
                                        'bg-gray-100 text-gray-400': !user.role
                                    }"
                                >
                                    {{ getRoleLabel(user.role) }}
                                </span>
                            </td>
                            <td class="px-8 py-6 text-right">
                                <div class="flex justify-end gap-2">
                                    <button
                                        @click="openEditModal(user)"
                                        class="p-2.5 bg-gray-50 text-gray-400 rounded-xl hover:text-brand-primary-from hover:bg-white hover:shadow-sm transition-all"
                                        title="Upravit"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                        </svg>
                                    </button>
                                    <button
                                        v-if="!isCurrentUser(user)"
                                        @click="deleteUser(user)"
                                        class="p-2.5 bg-gray-50 text-gray-400 rounded-xl hover:text-red-500 hover:bg-white hover:shadow-sm transition-all"
                                        title="Smazat"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                        </svg>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <tr v-if="users.data.length === 0">
                            <td colspan="4" class="px-8 py-16 text-center text-sm font-bold text-gray-400">
                                Žádní uživatelé neodpovídají filtru.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Pagination -->
        <div v-if="users.links.length > 3" class="mt-10 flex flex-col sm:flex-row items-center justify-between gap-4">
            <p class="text-sm font-bold text-gray-400">
                Zobrazeno {{ users.from }} až {{ users.to }} z {{ users.total }} uživatelů
            </p>
            <nav class="relative z-0 inline-flex gap-2">
                <Link
                    v-for="link in users.links"
                    :key="link.label"
                    :href="link.url"
                    v-html="link.label"
                    class="relative inline-flex items-center px-4 py-2 text-sm font-bold rounded-xl transition-all border-2"
                    :class="{
                        'brand-gradient text-white border-transparent shadow-sm': link.active,
                        'bg-white border-gray-100 text-gray-400 hover:border-brand-primary-from hover:text-brand-primary-from': !link.active,
                        'opacity-30 cursor-not-allowed': !link.url
                    }"
                />
            </nav>
        </div>

        <!-- Modal for Create/Edit -->
        <Transition
            enter-active-class="ease-out duration-300"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="ease-in duration-200"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="showModal" class="fixed inset-0 z-100 overflow-y-auto">
                <div class="flex min-h-full items-center justify-center p-4">
                    <div class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-opacity" @click="closeModal"></div>

                    <div class="relative transform overflow-hidden rounded-[2.5rem] bg-white shadow-2xl transition-all w-full max-w-xl border border-gray-100">
                        <form @submit.prevent="submit">
                            <div class="bg-white p-10 pb-6">
                                <div class="flex items-center justify-between mb-8">
                                    <h3 class="text-2xl font-black text-gray-900 font-heading tracking-tight">
                                        {{ editingUser ? 'Upravit uživatele' : 'Přidat nového uživatele' }}
                                    </h3>
                                    <button @click="closeModal" type="button" class="text-gray-400 hover:text-gray-900 transition-colors">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </button>
                                </div>

                                <div class="space-y-6">
                                    <div>
                                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Jméno</label>
                                        <input v-model="form.name" type="text" required class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all" placeholder="Jan Novák">
                                        <p v-if="form.errors.name" class="mt-2 ml-1 text-xs font-bold text-red-500">{{ form.errors.name }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">E-mail</label>
                                        <input v-model="form.email" type="email" required class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all" placeholder="jan@firma.cz">
                                        <p v-if="form.errors.email" class="mt-2 ml-1 text-xs font-bold text-red-500">{{ form.errors.email }}</p>
                                    </div>

                                    <div class="grid grid-cols-2 gap-6">
                                        <div>
                                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Telefon</label>
                                            <input v-model="form.phone" type="text" class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all">
                                            <p v-if="form.errors.phone" class="mt-2 ml-1 text-xs font-bold text-red-500">{{ form.errors.phone }}</p>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Firma</label>
                                            <input v-model="form.company" type="text" class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all">
                                            <p v-if="form.errors.company" class="mt-2 ml-1 text-xs font-bold text-red-500">{{ form.errors.company }}</p>
                                        </div>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Role</label>
                                        <select v-model="form.role" class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all appearance-none cursor-pointer">
                                            <option v-for="role in roles" :key="role.value" :value="role.value">{{ role.label }}</option>
                                            <option :value="null">Bez role (bez přístupu do CRM)</option>
                                        </select>
                                        <p v-if="form.errors.role" class="mt-2 ml-1 text-xs font-bold text-red-500">{{ form.errors.role }}</p>
                                    </div>

                                    <div>
                                        <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Heslo</label>
                                        <input v-model="form.password" type="password" autocomplete="new-password" class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all">
                                        <p class="mt-2 ml-1 text-xs font-semibold text-gray-400">
                                            {{ editingUser ? 'Vyplňte jen pro změnu hesla.' : 'Nepovinné – bez hesla se uživatel přihlásí přes Google stejným e-mailem.' }}
                                        </p>
                                        <p v-if="form.errors.password" class="mt-2 ml-1 text-xs font-bold text-red-500">{{ form.errors.password }}</p>
                                    </div>
                                </div>
                            </div>
                            <div class="px-10 py-8 bg-gray-50/50 flex gap-4">
                                <button type="submit" :disabled="form.processing" class="flex-1 brand-gradient py-4 rounded-2xl font-black text-white shadow-brand hover:shadow-brand-lg transition-all hover:-translate-y-1 font-heading uppercase tracking-widest text-[10px] disabled:opacity-50">
                                    {{ editingUser ? 'Uložit změny' : 'Vytvořit uživatele' }}
                                </button>
                                <button @click="closeModal" type="button" class="px-8 py-4 border-2 border-gray-100 rounded-2xl font-black text-gray-400 hover:text-gray-600 hover:border-gray-200 transition-all font-heading uppercase tracking-widest text-[10px]">
                                    Zrušit
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </Transition>

        <!-- Confirmation Modal -->
        <ConfirmModal
            :show="confirmDelete.show"
            title="Smazat uživatele"
            :message="confirmDelete.message"
            @close="confirmDelete.show = false"
            @confirm="executeDelete"
        />
    </Layout>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { Link, router, useForm, usePage } from '@inertiajs/vue3'
import Layout from '../../Components/Layout.vue'
import Breadcrumbs from '../../Components/Breadcrumbs.vue'
import ConfirmModal from '../../Components/ConfirmModal.vue'

const props = defineProps({
    users: Object,
    filters: Object,
    roles: Array,
})

const page = usePage()

const searchForm = reactive({
    search: props.filters.search || '',
    role: props.filters.role || '',
})

const search = () => {
    router.get('/users', searchForm, {
        preserveState: true,
        replace: true,
    })
}

const clearFilters = () => {
    searchForm.search = ''
    searchForm.role = ''
    search()
}

const getRoleLabel = (role) => {
    return props.roles.find(r => r.value === role)?.label || 'Bez role'
}

const isCurrentUser = (user) => user.id === page.props.auth.user?.id

const showModal = ref(false)
const editingUser = ref(null)

const form = useForm({
    name: '',
    email: '',
    phone: '',
    company: '',
    role: 'manager',
    password: '',
})

const openCreateModal = () => {
    editingUser.value = null
    form.defaults({ name: '', email: '', phone: '', company: '', role: 'manager', password: '' })
    form.reset()
    form.clearErrors()
    showModal.value = true
}

const openEditModal = (user) => {
    editingUser.value = user
    form.defaults({
        name: user.name,
        email: user.email,
        phone: user.phone || '',
        company: user.company || '',
        role: user.role,
        password: '',
    })
    form.reset()
    form.clearErrors()
    showModal.value = true
}

const closeModal = () => {
    showModal.value = false
}

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => closeModal(),
    }

    if (editingUser.value) {
        form.put(`/users/${editingUser.value.id}`, options)
    } else {
        form.post('/users', options)
    }
}

const confirmDelete = reactive({
    show: false,
    message: '',
    item: null,
})

const deleteUser = (user) => {
    confirmDelete.message = `Opravdu chcete smazat uživatele "${user.name}"? Jeho úkoly, výkazy a komentáře zůstanou zachované, jen bez přiřazeného uživatele.`
    confirmDelete.item = user
    confirmDelete.show = true
}

const executeDelete = () => {
    router.delete(`/users/${confirmDelete.item.id}`, {
        preserveScroll: true,
        onFinish: () => confirmDelete.show = false,
    })
}
</script>
