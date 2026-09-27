<template>
    <div class="mt-3">
        <button
            type="button"
            @click="open = !open"
            class="text-[10px] font-black uppercase tracking-widest transition-colors"
            :class="open ? 'text-brand-primary-from' : 'text-gray-300 hover:text-brand-primary-from'"
        >
            <span v-if="comments.length === 0">+ Příspěvek</span>
            <span v-else>Příspěvky ({{ comments.length }})</span>
        </button>

        <div v-if="open" class="mt-3 rounded-2xl border border-gray-100 bg-gray-50/50 p-4 space-y-4">
            <div v-for="comment in comments" :key="comment.id" class="rounded-2xl bg-white border border-gray-100 p-4">
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
                        <RichEditor v-model="editForm.body" height="200px" placeholder="Text příspěvku..." />
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
                    <div class="mt-3 flex flex-wrap items-center gap-3">
                        <input type="file" multiple @change="e => editForm.files = [...e.target.files]" class="text-[10px] font-semibold text-gray-500">
                        <button type="button" @click="saveEdit(comment)" :disabled="editForm.processing" class="brand-gradient px-3 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest text-white disabled:opacity-50">Uložit</button>
                        <button type="button" @click="cancelEdit" class="text-[9px] font-black uppercase tracking-widest text-gray-400 hover:text-gray-600">Zrušit</button>
                    </div>
                    <p v-if="firstError(editForm)" class="mt-2 text-xs text-red-500 font-bold">{{ firstError(editForm) }}</p>
                </template>

                <template v-else>
                    <!-- Sanitized on the server (TodoComment::sanitize) before it is stored. -->
                    <div v-if="comment.body" class="comment-body mt-2 text-sm text-gray-700" v-html="comment.body"></div>

                    <ul v-if="comment.attachments.length" class="mt-3 flex flex-wrap gap-2">
                        <li v-for="attachment in comment.attachments" :key="attachment.id">
                            <a :href="attachment.url" target="_blank" rel="noopener" class="group/file block">
                                <img
                                    v-if="attachment.is_image"
                                    :src="attachment.url"
                                    :alt="attachment.original_name"
                                    class="h-24 w-24 object-cover rounded-xl border border-gray-100 group-hover/file:border-brand-primary-from transition-colors"
                                >
                                <span v-else class="inline-flex items-center gap-2 px-2.5 py-1.5 rounded-lg border border-gray-100 bg-gray-50 text-[10px] font-bold text-gray-600 group-hover/file:border-brand-primary-from transition-colors">
                                    📎 {{ attachment.original_name }}
                                    <span class="text-gray-400 font-semibold">{{ formatSize(attachment.size) }}</span>
                                </span>
                            </a>
                        </li>
                    </ul>
                </template>
            </div>

            <form @submit.prevent="submit" class="space-y-3">
                <RichEditor :key="editorKey" v-model="form.body" height="200px" placeholder="Napište příspěvek..." />
                <div class="flex flex-wrap items-center gap-3">
                    <input ref="fileInput" type="file" multiple @change="e => form.files = [...e.target.files]" class="text-[10px] font-semibold text-gray-500">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="brand-gradient px-4 py-2 rounded-xl text-[10px] font-black uppercase tracking-widest text-white disabled:opacity-50"
                    >
                        Přidat příspěvek
                    </button>
                    <span v-if="form.progress" class="text-[10px] font-bold text-gray-400">{{ form.progress.percentage }} %</span>
                </div>
                <p v-if="firstError(form)" class="text-xs text-red-500 font-bold">{{ firstError(form) }}</p>
            </form>
        </div>

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
import ConfirmModal from './ConfirmModal.vue'
import RichEditor from './RichEditor.vue'

const props = defineProps({
    todo: Object,
})

const page = usePage()

const open = ref(false)
const comments = computed(() => props.todo.comments || [])

const currentUser = computed(() => page.props.auth?.user)
// Mirrors TodoComment::isManageableBy(): admins manage any post, others only their own.
const canManage = (comment) => currentUser.value
    && (currentUser.value.role === 'admin' || comment.user_id === currentUser.value.id)

const formatDateTime = (value) => new Date(value).toLocaleString('cs-CZ', {
    day: 'numeric', month: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit',
})

const formatSize = (bytes) => {
    if (bytes >= 1024 * 1024) return `${(bytes / 1024 / 1024).toFixed(1).replace('.', ',')} MB`
    return `${Math.max(1, Math.round(bytes / 1024))} kB`
}

// Laravel reports array uploads as "files.0" etc.; show whichever error comes first.
const firstError = (f) => Object.values(f.errors)[0]

const fileInput = ref(null)
// Remounting the editor is the reliable way to clear it after a post.
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
            if (fileInput.value) fileInput.value.value = ''
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

<style scoped>
.comment-body :deep(p) { margin: 0.25rem 0; }
.comment-body :deep(ul) { list-style: disc; padding-left: 1.25rem; }
.comment-body :deep(ol) { list-style: decimal; padding-left: 1.25rem; }
.comment-body :deep(a) { color: #2563eb; text-decoration: underline; }
.comment-body :deep(blockquote) { border-left: 3px solid #e5e7eb; padding-left: 0.75rem; color: #6b7280; }
.comment-body :deep(h1), .comment-body :deep(h2), .comment-body :deep(h3) { font-weight: 800; margin: 0.5rem 0 0.25rem; }
.comment-body :deep(table) { border-collapse: collapse; }
.comment-body :deep(td), .comment-body :deep(th) { border: 1px solid #e5e7eb; padding: 0.25rem 0.5rem; }
</style>
