<template>
    <div class="space-y-4">
        <p v-if="posts.length === 0" class="text-sm font-bold text-gray-300">Zatím žádné příspěvky.</p>

        <div
            v-for="post in posts"
            :key="post.key"
            class="flex items-start gap-3"
            :class="{ 'justify-end': isMine(post) }"
        >
            <UserAvatar v-if="!isMine(post)" :user="post.user" :name="authorName(post)" class="mt-1 h-9 w-9 text-[10px]" />

            <div
                class="min-w-0 rounded-2xl border p-5"
                :class="[
                    isMine(post) ? 'border-brand-primary-from/20 border-r-4 border-r-brand-primary-from bg-brand-primary-from/5' : 'grow border-gray-100 bg-white',
                    isMine(post) && (editingKey === post.key ? 'w-full' : 'max-w-[85%]'),
                ]"
            >
                <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                    <div class="text-xs font-semibold text-gray-400">
                        <span class="mr-1 text-sm font-black text-gray-800">{{ authorName(post) }}</span>
                        Přidáno {{ formatDateTime(post.created_at) }}
                        <span v-if="post.updated_at !== post.created_at">(upraveno)</span>
                    </div>
                    <div v-if="canManage(post) && editingKey !== post.key" class="space-x-2">
                        <button type="button" @click="startEdit(post)" class="text-[9px] font-black uppercase tracking-widest text-gray-300 hover:text-brand-primary-from transition-colors">Upravit</button>
                        <button v-if="!post.isDescription" type="button" @click="removing = post" class="text-[9px] font-black uppercase tracking-widest text-gray-300 hover:text-red-500 transition-colors">Smazat</button>
                    </div>
                </div>

                <template v-if="editingKey === post.key">
                    <div class="mt-3">
                        <RichEditor v-model="editForm.body" :mention-users="mentionUsers" height="200px" placeholder="Text příspěvku..." />
                    </div>
                    <ul v-if="post.attachments.length" class="mt-3 flex flex-wrap gap-2">
                        <li
                            v-for="attachment in post.attachments"
                            :key="attachment.id"
                            class="flex items-center gap-2 px-2.5 py-1 rounded-lg border text-[10px] font-bold"
                            :class="editForm.remove_attachment_ids.includes(attachment.id) ? 'border-red-100 bg-red-50 text-red-400 line-through' : 'border-gray-100 bg-gray-50 text-gray-500'"
                        >
                            {{ attachment.original_name }}
                            <button type="button" @click="toggleRemoval(attachment.id)" class="text-[9px] font-black uppercase tracking-widest hover:text-red-500">
                                {{ editForm.remove_attachment_ids.includes(attachment.id) ? 'Vrátit' : 'Odebrat' }}
                            </button>
                        </li>
                    </ul>
                    <FilePicker v-model="editForm.files" class="mt-3" />
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <button type="button" @click="saveEdit(post)" :disabled="editForm.processing" class="brand-gradient px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest text-white disabled:opacity-50">Uložit</button>
                        <button type="button" @click="cancelEdit" class="text-[9px] font-black uppercase tracking-widest text-gray-400 hover:text-gray-600">Zrušit</button>
                    </div>
                    <p v-if="firstError(editForm)" class="mt-2 text-xs text-red-500 font-bold">{{ firstError(editForm) }}</p>
                </template>

                <template v-else>
                    <!-- Sanitized on the server (RichText) before it is stored. -->
                    <div v-if="post.body" class="toastui-editor-contents rich-images mt-2" v-html="post.body" @click="onImageClick"></div>

                    <AttachmentThumbs :attachments="post.attachments" class="mt-3" @open="attachment => $emit('open-attachment', attachment)" />
                </template>
            </div>
        </div>

        <form @submit.prevent="submit" class="space-y-3">
            <RichEditor :key="editorKey" v-model="form.body" :mention-users="mentionUsers" height="200px" placeholder="Napište příspěvek... (@ zmíní člověka z projektu)" />
            <FilePicker v-model="form.files" />
            <div class="flex flex-wrap items-center gap-3">
                <button
                    v-if="!isCustomer"
                    type="button"
                    @click="moreOpen = !moreOpen"
                    class="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-widest transition-colors"
                    :class="moreOpen || extrasActive ? 'text-brand-primary-from' : 'text-gray-400 hover:text-brand-primary-from'"
                    :aria-expanded="moreOpen"
                >
                    Více
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 transition-transform" :class="{ 'rotate-90': moreOpen }" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                    </svg>
                </button>
                <span v-if="form.progress" class="text-[10px] font-bold text-gray-400">{{ form.progress.percentage }} %</span>
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="ml-auto brand-gradient px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest text-white disabled:opacity-50"
                >
                    Odeslat
                </button>
            </div>

            <div v-if="moreOpen && !isCustomer" class="space-y-4 rounded-2xl border border-gray-100 bg-gray-50/50 p-4">
                <div class="space-y-2">
                    <label class="flex cursor-pointer items-center gap-3">
                        <input v-model="extras.changeAssignee" type="checkbox" class="peer sr-only">
                        <span class="relative h-5 w-9 shrink-0 rounded-full bg-gray-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:bg-brand-primary-from peer-checked:after:translate-x-4 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-primary-from"></span>
                        <span class="text-xs font-bold text-gray-700">Změnit řešitele</span>
                    </label>
                    <ChoicesSelect
                        v-if="extras.changeAssignee"
                        v-model="extras.assignee"
                        :options="assigneeOptions"
                    />
                </div>

                <div class="space-y-2">
                    <label class="flex cursor-pointer items-center gap-3">
                        <input v-model="extras.reportTime" type="checkbox" class="peer sr-only">
                        <span class="relative h-5 w-9 shrink-0 rounded-full bg-gray-200 transition-colors after:absolute after:left-0.5 after:top-0.5 after:h-4 after:w-4 after:rounded-full after:bg-white after:shadow after:transition-transform peer-checked:bg-brand-primary-from peer-checked:after:translate-x-4 peer-focus-visible:ring-2 peer-focus-visible:ring-brand-primary-from"></span>
                        <span class="text-xs font-bold text-gray-700">Vykázat čas</span>
                    </label>
                    <div v-if="extras.reportTime">
                        <input
                            v-model="extras.duration"
                            type="text"
                            placeholder="1h 30m"
                            class="block w-full max-w-[12rem] rounded-xl border-gray-100 bg-white px-3 py-2 text-xs font-semibold text-gray-700 focus:border-brand-primary-from focus:ring-brand-primary-from"
                        >
                        <p class="mt-1 text-[10px] font-bold text-gray-400">{{ DURATION_HINT }} Vykáže se dnes na vás.</p>
                    </div>
                </div>
            </div>

            <p v-if="extras.error || firstError(form)" class="text-xs text-red-500 font-bold">{{ extras.error || firstError(form) }}</p>
        </form>

        <ConfirmModal
            :show="removing !== null"
            title="Smazat příspěvek"
            message="Opravdu chcete smazat tento příspěvek včetně jeho příloh?"
            @close="removing = null"
            @confirm="remove"
        />
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AttachmentThumbs from './AttachmentThumbs.vue'
import ChoicesSelect from './ChoicesSelect.vue'
import ConfirmModal from './ConfirmModal.vue'
import FilePicker from './FilePicker.vue'
import RichEditor from './RichEditor.vue'
import UserAvatar from './UserAvatar.vue'
import { DURATION_HINT, parseDuration } from '../utils/billing'
import { onInlineImageClick } from '../utils/files'

const props = defineProps({
    todo: Object,
    // People assigned to the project; only they can be mentioned.
    mentionUsers: Array,
})

// The todo page owns one gallery for the description and all comments.
const emit = defineEmits(['open-attachment'])

const onImageClick = onInlineImageClick(url => emit('open-attachment', { url }))

const page = usePage()

// The todo's description opens the thread as its first post, written by whoever created the todo.
const posts = computed(() => {
    const { todo } = props
    const attachments = todo.attachments || []
    const description = todo.description || attachments.length
        ? [{
            key: 'description',
            isDescription: true,
            body: todo.description,
            attachments,
            user: todo.creator,
            user_id: todo.created_by_user_id,
            created_at: todo.created_at,
            updated_at: todo.created_at,
        }]
        : []

    return [...description, ...(todo.comments || []).map(comment => ({ ...comment, key: comment.id }))]
})

const currentUser = computed(() => page.props.auth?.user)
// Mirrors TodoComment::isManageableBy(): admins manage any comment, others only their own.
// The description stays editable by everyone who can open the todo.
const canManage = (post) => post.isDescription || (currentUser.value
    && (currentUser.value.role === 'admin' || post.user_id === currentUser.value.id))

const isMine = (post) => !!currentUser.value && post.user_id === currentUser.value.id

const authorName = (post) => post.user?.name || post.author_name || 'Neznámý uživatel'

const formatDateTime = (value) => new Date(value).toLocaleString('cs-CZ', {
    day: 'numeric', month: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit',
})

// Laravel reports array uploads as "files.0" etc.; show whichever error comes first.
const firstError = (f) => Object.values(f.errors)[0]

// Remounting the editor is the reliable way to clear it after a comment is sent.
const editorKey = ref(0)

const form = useForm({ body: '', files: [] })

const isCustomer = computed(() => currentUser.value?.role === 'customer')

// "Více": optional extras sent together with the post – hand the todo over and/or report time.
const moreOpen = ref(false)
const extras = reactive({ changeAssignee: false, assignee: props.todo.assigned_user_id || '', reportTime: false, duration: '', error: '' })
const extrasActive = computed(() => extras.changeAssignee || extras.reportTime)

const assigneeOptions = computed(() => [
    { value: '', label: 'Nepřiřazeno' },
    ...(props.mentionUsers || []).map(user => ({ value: user.id, label: user.name, avatar: user.avatar ?? null })),
])

const resetExtras = () => {
    extras.changeAssignee = false
    extras.assignee = props.todo.assigned_user_id || ''
    extras.reportTime = false
    extras.duration = ''
    extras.error = ''
}

const submit = () => {
    extras.error = ''
    const extraData = {}

    if (extras.reportTime) {
        const minutes = parseDuration(extras.duration)

        if (!minutes || minutes > 1440) {
            extras.error = DURATION_HINT
            return
        }

        extraData.report_minutes = minutes
    }

    if (extras.changeAssignee) {
        extraData.assigned_user_id = extras.assignee || ''
    }

    form.transform(data => ({ ...data, ...extraData })).post(`/todos/${props.todo.id}/comments`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset()
            form.clearErrors()
            resetExtras()
            moreOpen.value = false
            editorKey.value++
        },
    })
}

const editingKey = ref(null)
const editForm = useForm({ body: '', files: [], remove_attachment_ids: [] })

const startEdit = (post) => {
    editingKey.value = post.key
    editForm.body = post.body || ''
    editForm.files = []
    editForm.remove_attachment_ids = []
    editForm.clearErrors()
}

const cancelEdit = () => {
    editingKey.value = null
    editForm.reset()
    editForm.clearErrors()
}

const toggleRemoval = (id) => {
    const ids = editForm.remove_attachment_ids
    editForm.remove_attachment_ids = ids.includes(id) ? ids.filter(i => i !== id) : [...ids, id]
}

const saveEdit = (post) => {
    // Files can't travel in a real PATCH request, so it is spoofed over POST.
    editForm
        .transform(({ body, ...data }) => ({
            ...data,
            ...(post.isDescription ? { description: body || null } : { body }),
            _method: 'patch',
        }))
        .post(post.isDescription ? `/todos/${props.todo.id}` : `/todo-comments/${post.id}`, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: cancelEdit,
        })
}

const removing = ref(null)

const remove = () => {
    router.delete(`/todo-comments/${removing.value.id}`, {
        preserveScroll: true,
        onFinish: () => removing.value = null,
    })
}
</script>
