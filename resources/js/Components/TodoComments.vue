<template>
    <div class="space-y-4">
        <p v-if="comments.length === 0" class="text-sm font-bold text-gray-300">Zatím žádné komentáře.</p>

        <div v-for="comment in comments" :key="comment.id" class="rounded-2xl bg-white border border-gray-100 p-5">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <div class="text-[10px] font-black uppercase tracking-widest text-gray-400">
                    <span class="text-gray-700">{{ comment.user?.name || comment.author_name || 'Neznámý uživatel' }}</span>
                    · {{ formatDateTime(comment.created_at) }}
                    <span v-if="comment.updated_at !== comment.created_at" class="normal-case tracking-normal font-semibold">(upraveno)</span>
                </div>
                <div v-if="canManage(comment) && editingId !== comment.id" class="space-x-2">
                    <button type="button" @click="startEdit(comment)" class="text-[9px] font-black uppercase tracking-widest text-gray-300 hover:text-brand-primary-from transition-colors">Upravit</button>
                    <button type="button" @click="removing = comment" class="text-[9px] font-black uppercase tracking-widest text-gray-300 hover:text-red-500 transition-colors">Smazat</button>
                </div>
            </div>

            <template v-if="editingId === comment.id">
                <div class="mt-3">
                    <RichEditor v-model="editForm.body" height="200px" placeholder="Text komentáře..." />
                </div>
                <ul v-if="comment.attachments.length" class="mt-3 flex flex-wrap gap-2">
                    <li
                        v-for="attachment in comment.attachments"
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
                    <button type="button" @click="saveEdit(comment)" :disabled="editForm.processing" class="brand-gradient px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest text-white disabled:opacity-50">Uložit</button>
                    <button type="button" @click="cancelEdit" class="text-[9px] font-black uppercase tracking-widest text-gray-400 hover:text-gray-600">Zrušit</button>
                </div>
                <p v-if="firstError(editForm)" class="mt-2 text-xs text-red-500 font-bold">{{ firstError(editForm) }}</p>
            </template>

            <template v-else>
                <!-- Sanitized on the server (RichText) before it is stored. -->
                <div v-if="comment.body" class="toastui-editor-contents mt-2" v-html="comment.body"></div>

                <ul v-if="comment.attachments.length" class="mt-3 flex flex-wrap gap-2">
                    <li v-for="attachment in comment.attachments" :key="attachment.id">
                        <a :href="attachment.url" target="_blank" rel="noopener" class="group/file block" :title="attachment.caption || attachment.original_name">
                            <img
                                v-if="attachment.is_image"
                                :src="attachment.url"
                                :alt="attachment.caption || attachment.original_name"
                                class="h-24 w-24 object-cover rounded-xl border border-gray-100 group-hover/file:border-brand-primary-from transition-colors"
                            >
                            <span v-else class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-lg border border-gray-100 bg-gray-50 text-[10px] font-bold text-gray-600 group-hover/file:border-brand-primary-from transition-colors">
                                📎 {{ attachment.original_name }}
                                <span class="text-gray-400 font-semibold">{{ formatSize(attachment.size) }}</span>
                            </span>
                            <span v-if="attachment.caption" class="mt-1 block max-w-24 truncate text-[9px] font-bold text-gray-400">{{ attachment.caption }}</span>
                        </a>
                    </li>
                </ul>
            </template>
        </div>

        <form @submit.prevent="submit" class="space-y-3">
            <RichEditor :key="editorKey" v-model="form.body" height="200px" placeholder="Napište komentář..." />
            <FilePicker v-model="form.files" />
            <div class="flex flex-wrap items-center gap-3">
                <button
                    type="submit"
                    :disabled="form.processing"
                    class="brand-gradient px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest text-white disabled:opacity-50"
                >
                    Přidat komentář
                </button>
                <span v-if="form.progress" class="text-[10px] font-bold text-gray-400">{{ form.progress.percentage }} %</span>
            </div>
            <p v-if="firstError(form)" class="text-xs text-red-500 font-bold">{{ firstError(form) }}</p>
        </form>

        <ConfirmModal
            :show="removing !== null"
            title="Smazat komentář"
            message="Opravdu chcete smazat tento komentář včetně jeho příloh?"
            @close="removing = null"
            @confirm="remove"
        />
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { router, useForm, usePage } from '@inertiajs/vue3'
import ConfirmModal from './ConfirmModal.vue'
import FilePicker from './FilePicker.vue'
import RichEditor from './RichEditor.vue'
import { formatSize } from '../utils/files'

const props = defineProps({
    todo: Object,
})

const page = usePage()

const comments = computed(() => props.todo.comments || [])

const currentUser = computed(() => page.props.auth?.user)
// Mirrors TodoComment::isManageableBy(): admins manage any comment, others only their own.
const canManage = (comment) => currentUser.value
    && (currentUser.value.role === 'admin' || comment.user_id === currentUser.value.id)

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

const editingId = ref(null)
const editForm = useForm({ body: '', files: [], remove_attachment_ids: [] })

const startEdit = (comment) => {
    editingId.value = comment.id
    editForm.body = comment.body || ''
    editForm.files = []
    editForm.remove_attachment_ids = []
    editForm.clearErrors()
}

const cancelEdit = () => {
    editingId.value = null
    editForm.reset()
    editForm.clearErrors()
}

const toggleRemoval = (id) => {
    const ids = editForm.remove_attachment_ids
    editForm.remove_attachment_ids = ids.includes(id) ? ids.filter(i => i !== id) : [...ids, id]
}

const saveEdit = (comment) => {
    // Files can't travel in a real PATCH request, so it is spoofed over POST.
    editForm
        .transform(data => ({ ...data, _method: 'patch' }))
        .post(`/todo-comments/${comment.id}`, {
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
