<template>
    <section class="mb-8 bg-white rounded-[2rem] border border-gray-50 p-8 shadow-sm">
        <div class="mb-4 flex items-center justify-between gap-4">
            <h2 class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Lidé v projektu ({{ members.length }})</h2>
            <button
                v-if="canManage"
                type="button"
                @click="openAdd"
                class="rounded-full border-2 border-gray-100 bg-white px-4 py-1.5 text-[10px] font-black uppercase tracking-widest text-gray-500 hover:border-brand-primary-from hover:text-brand-primary-from transition-all"
            >
                + Přidat osobu
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <p v-if="!members.length && !invitations.length" class="text-sm font-bold text-gray-300">K projektu zatím není nikdo přiřazený. Vidí ho jen administrátoři a úkoly nejde nikomu přidělit.</p>

            <span
                v-for="member in members"
                :key="member.id"
                class="inline-flex items-center gap-2 rounded-full border border-gray-100 bg-gray-50 py-1 pl-1 pr-3 text-xs font-bold text-gray-700"
            >
                <UserAvatar :user="member" class="h-6 w-6 text-[8px]" />
                {{ member.name }}
                <span v-if="!canManage" class="px-1 text-[9px] font-black uppercase tracking-widest text-gray-300">{{ accessLabel(member) }}</span>
                <button
                    v-if="canManage"
                    type="button"
                    @click="openEdit(member)"
                    class="rounded-full bg-white px-2 py-0.5 text-[9px] font-black uppercase tracking-widest text-gray-400 hover:text-brand-primary-from transition-colors"
                    :title="`Nastavit, které seznamy úkolů vidí ${member.name}`"
                >
                    {{ accessLabel(member) }}
                </button>
                <button
                    v-if="canManage"
                    type="button"
                    @click="remove(member)"
                    class="text-gray-300 hover:text-red-500 transition-colors"
                    :title="`Odebrat ${member.name} z projektu`"
                    :aria-label="`Odebrat ${member.name} z projektu`"
                >
                    ×
                </button>
            </span>

            <span
                v-for="invitation in invitations"
                :key="`invitation-${invitation.id}`"
                class="inline-flex items-center gap-2 rounded-full border border-dashed border-gray-200 bg-white py-1.5 pl-4 pr-3 text-xs font-bold text-gray-500"
                title="Pozvaný e-mailem, v CRM se objeví po registraci"
            >
                {{ invitation.email }}
                <span class="text-[9px] font-black uppercase tracking-widest text-gray-300">pozvánka</span>
                <button
                    v-if="canManage"
                    type="button"
                    @click="cancelInvitation(invitation)"
                    class="text-gray-300 hover:text-red-500 transition-colors"
                    :aria-label="`Zrušit pozvánku pro ${invitation.email}`"
                >
                    ×
                </button>
            </span>
        </div>

        <Modal :show="dialog.show" :title="dialog.member ? `Přístup: ${dialog.member.name}` : 'Přidat osobu do projektu'" @close="dialog.show = false">
            <template #content>
                <div class="space-y-6 text-left">
                    <template v-if="!dialog.member">
                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Z lidí, které už znáte</label>
                            <input
                                v-model="search"
                                type="text"
                                placeholder="Hledat podle jména nebo e-mailu"
                                class="block w-full px-5 py-3 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                            >
                            <ul class="mt-3 max-h-48 space-y-1 overflow-y-auto">
                                <li v-if="!filteredPeople.length" class="px-2 py-3 text-xs font-bold text-gray-300">
                                    {{ knownPeople.length ? 'Nikdo takový nenalezen.' : 'Zatím tu nikdo není – zadejte e-mail.' }}
                                </li>
                                <li v-for="person in filteredPeople" :key="person.id">
                                    <button
                                        type="button"
                                        @click="pick(person)"
                                        class="flex w-full items-center gap-3 rounded-2xl border-2 px-3 py-2 text-left transition-all"
                                        :class="form.user_id === person.id ? 'border-brand-primary-from bg-gray-50' : 'border-transparent hover:bg-gray-50'"
                                    >
                                        <UserAvatar :user="person" class="h-8 w-8 text-[9px]" />
                                        <span class="min-w-0">
                                            <span class="block truncate text-sm font-black text-gray-800">{{ person.name }}</span>
                                            <span class="block truncate text-xs font-semibold text-gray-400">{{ person.email }}</span>
                                        </span>
                                    </button>
                                </li>
                            </ul>
                        </div>

                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Nebo zadejte e-mail</label>
                            <input
                                v-model="form.email"
                                @input="form.user_id = null"
                                type="email"
                                placeholder="jmeno@firma.cz"
                                class="block w-full px-5 py-3 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                            >
                            <p class="mt-2 ml-1 text-xs font-semibold text-gray-400">Kdo ještě nemá účet, do projektu se připojí po registraci s tímto e-mailem.</p>
                            <p v-if="form.errors.email || form.errors.user_id" class="mt-2 ml-1 text-xs font-bold text-red-500">{{ form.errors.email || form.errors.user_id }}</p>
                        </div>
                    </template>

                    <div class="rounded-2xl bg-gray-50 p-5">
                        <label class="flex cursor-pointer items-center justify-between gap-4">
                            <span>
                                <span class="block text-sm font-black text-gray-800">Vidí všechny seznamy úkolů</span>
                                <span class="block text-xs font-semibold text-gray-400">Včetně těch, které v projektu přibudou později.</span>
                            </span>
                            <button
                                type="button"
                                role="switch"
                                :aria-checked="form.all_todolists"
                                @click="form.all_todolists = !form.all_todolists"
                                class="relative h-7 w-12 shrink-0 rounded-full transition-colors"
                                :class="form.all_todolists ? 'bg-brand-primary-from' : 'bg-gray-300'"
                            >
                                <span
                                    class="absolute left-0.5 top-0.5 h-6 w-6 rounded-full bg-white shadow transition-transform"
                                    :class="form.all_todolists ? 'translate-x-5' : ''"
                                ></span>
                            </button>
                        </label>

                        <div v-if="!form.all_todolists" class="mt-4 border-t border-gray-100 pt-4">
                            <p v-if="!todolists.length" class="text-xs font-bold text-gray-400">Projekt zatím nemá žádný seznam úkolů.</p>
                            <template v-else>
                                <p class="mb-2 text-xs font-black uppercase tracking-widest text-gray-400">Vidí jen tyto seznamy</p>
                                <label
                                    v-for="list in todolists"
                                    :key="list.id"
                                    class="flex cursor-pointer items-center gap-3 py-1.5 text-sm font-bold text-gray-700"
                                >
                                    <input
                                        type="checkbox"
                                        :value="list.id"
                                        v-model="form.todolist_ids"
                                        class="h-5 w-5 rounded-lg border-gray-300 text-brand-primary-from focus:ring-brand-primary-from"
                                    >
                                    {{ list.name }}
                                </label>
                                <p v-if="!form.todolist_ids.length" class="mt-2 text-xs font-semibold text-gray-400">Nevybráno nic – osoba neuvidí žádný seznam.</p>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
            <template #footer>
                <button
                    @click="submit"
                    :disabled="form.processing || (!dialog.member && !form.user_id && !form.email)"
                    class="brand-gradient px-8 py-3 rounded-full font-bold text-white shadow-brand transition-all font-heading disabled:opacity-50"
                >
                    {{ dialog.member ? 'Uložit' : 'Přidat' }}
                </button>
                <button @click="dialog.show = false" class="px-6 py-3 rounded-full text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-gray-600 transition-colors">
                    Zrušit
                </button>
            </template>
        </Modal>
    </section>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import Modal from './Modal.vue'
import UserAvatar from './UserAvatar.vue'

const props = defineProps({
    projectId: Number,
    members: Array,
    knownPeople: { type: Array, default: () => [] },
    invitations: { type: Array, default: () => [] },
    todolists: { type: Array, default: () => [] },
})

// Administrators and managers decide who is in a project and what they see.
const canManage = ['admin', 'manager'].includes(usePage().props.auth.user?.role)

const dialog = reactive({ show: false, member: null })
const search = ref('')
const form = useForm({ user_id: null, email: '', all_todolists: false, todolist_ids: [] })

const filteredPeople = computed(() => {
    const term = search.value.trim().toLowerCase()

    return props.knownPeople.filter(person =>
        !term || person.name.toLowerCase().includes(term) || person.email.toLowerCase().includes(term)
    )
})

const accessLabel = (member) => {
    if (member.all_todolists) return 'všechny seznamy'
    const count = member.todolist_ids.length
    if (count === 0) return 'žádný seznam'
    return count === 1 ? '1 seznam' : `${count} ${count < 5 ? 'seznamy' : 'seznamů'}`
}

// Nobody sees a todolist unless it is handed to them, so a new person starts with none.
const openAdd = () => {
    form.reset()
    form.clearErrors()
    search.value = ''
    dialog.member = null
    dialog.show = true
}

const openEdit = (member) => {
    form.clearErrors()
    form.user_id = null
    form.email = ''
    form.all_todolists = member.all_todolists
    form.todolist_ids = [...member.todolist_ids]
    dialog.member = member
    dialog.show = true
}

const pick = (person) => {
    form.user_id = form.user_id === person.id ? null : person.id
    form.email = ''
}

const submit = () => {
    const options = {
        preserveScroll: true,
        onSuccess: () => dialog.show = false,
    }

    if (dialog.member) {
        form.transform(({ all_todolists, todolist_ids }) => ({ all_todolists, todolist_ids }))
            .patch(`/projects/${props.projectId}/members/${dialog.member.id}`, options)
    } else {
        form.transform(data => ({
            ...data,
            user_id: data.user_id || undefined,
            email: data.user_id ? undefined : data.email,
        })).post(`/projects/${props.projectId}/members`, options)
    }
}

const remove = (member) => {
    router.delete(`/projects/${props.projectId}/members/${member.id}`, { preserveScroll: true })
}

const cancelInvitation = (invitation) => {
    router.delete(`/projects/${props.projectId}/invitations/${invitation.id}`, { preserveScroll: true })
}
</script>
