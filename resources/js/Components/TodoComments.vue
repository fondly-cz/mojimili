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
                    type="submit"
                    :disabled="form.processing"
                    class="brand-gradient px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest text-white disabled:opacity-50"
                >
                    Odeslat
                </button>
                <span v-if="form.progress" class="text-[10px] font-bold text-gray-400">{{ form.progress.percentage }} %</span>
            </div>
            <p v-if="firstError(form)" class="text-xs text-red-500 font-bold">{{ firstError(form) }}</p>
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
import { computed, ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import AttachmentThumbs from './AttachmentThumbs.vue'
import ConfirmModal from './ConfirmModal.vue'
import FilePicker from './FilePicker.vue'
import RichEditor from './RichEditor.vue'
import UserAvatar from './UserAvatar.vue'
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

const submit = () => {
    form.post(`/todos/${props.todo.id}/comments`, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset()
            form.clearErrors()
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
