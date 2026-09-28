<template>
    <section class="mb-8 bg-white rounded-[2rem] border border-gray-50 p-8 shadow-sm">
        <div class="flex items-center justify-between gap-4 mb-4">
            <h2 class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 font-heading">Dokumenty ({{ documents.length }})</h2>
            <button
                v-if="!editing"
                type="button"
                @click="start(null)"
                class="text-[10px] font-black uppercase tracking-widest text-gray-300 hover:text-brand-primary-from transition-colors"
            >
                + Dokument
            </button>
        </div>

        <form v-if="editing" @submit.prevent="submit" class="space-y-4">
            <input
                v-model="form.name"
                type="text"
                placeholder="Název dokumentu"
                class="block w-full px-4 py-2.5 bg-gray-50 border-gray-100 rounded-xl text-sm font-semibold text-gray-700 focus:ring-brand-primary-from focus:border-brand-primary-from"
            >
            <p v-if="form.errors.name" class="text-xs text-red-500 font-bold">{{ form.errors.name }}</p>
            <RichEditor v-model="form.content" height="320px" placeholder="Obsah dokumentu..." />
            <div class="flex items-center gap-3">
                <button type="submit" :disabled="form.processing" class="brand-gradient px-5 py-2.5 rounded-xl text-[10px] font-black uppercase tracking-widest text-white disabled:opacity-50">
                    Uložit dokument
                </button>
                <button type="button" @click="editing = false" class="text-[9px] font-black uppercase tracking-widest text-gray-400 hover:text-gray-600">Zrušit</button>
            </div>
        </form>

        <p v-else-if="!documents.length" class="text-sm font-bold text-gray-300">Projekt zatím nemá žádné dokumenty.</p>

        <ul v-else class="divide-y divide-gray-50">
            <li v-for="document in documents" :key="document.id" class="py-3">
                <div class="flex items-center justify-between gap-4">
                    <button type="button" @click="toggle(document.id)" class="flex items-center gap-2 text-left text-sm font-black text-gray-800 hover:text-brand-primary-from transition-colors">
                        <span v-if="document.color" class="h-2.5 w-2.5 shrink-0 rounded-full" :style="{ backgroundColor: document.color }"></span>
                        {{ document.name }}
                    </button>
                    <div class="flex shrink-0 items-center gap-3">
                        <button type="button" @click="start(document)" class="text-[10px] font-black uppercase tracking-widest text-gray-300 hover:text-brand-primary-from">Upravit</button>
                        <button type="button" @click="remove(document)" class="text-[10px] font-black uppercase tracking-widest text-gray-300 hover:text-red-500">Smazat</button>
                    </div>
                </div>
                <!-- Sanitized on the server (RichText) before it is stored. -->
                <div v-if="openId === document.id" class="toastui-editor-contents mt-3" v-html="document.content || '<p>Prázdný dokument.</p>'"></div>
            </li>
        </ul>

        <ConfirmModal
            :show="!!deleting"
            title="Smazat dokument"
            :message="`Opravdu chcete smazat dokument „${deleting?.name}“?`"
            @close="deleting = null"
            @confirm="destroy"
        />
    </section>
</template>

<script setup>
import { ref } from 'vue'
import { router, useForm } from '@inertiajs/vue3'
import '@toast-ui/editor/dist/toastui-editor.css'
import ConfirmModal from './ConfirmModal.vue'
import RichEditor from './RichEditor.vue'

const props = defineProps({
    projectId: Number,
    documents: Array,
})

const openId = ref(null)
const toggle = (id) => { openId.value = openId.value === id ? null : id }

const editing = ref(false)
const editedId = ref(null)
const form = useForm({ name: '', content: '' })

const start = (document) => {
    editedId.value = document?.id ?? null
    form.clearErrors()
    form.name = document?.name ?? ''
    form.content = document?.content ?? ''
    editing.value = true
}

const submit = () => {
    const options = { preserveScroll: true, onSuccess: () => { editing.value = false } }

    if (editedId.value) {
        form.patch(`/project-documents/${editedId.value}`, options)
    } else {
        form.post(`/projects/${props.projectId}/documents`, options)
    }
}

const deleting = ref(null)
const remove = (document) => { deleting.value = document }
const destroy = () => router.delete(`/project-documents/${deleting.value.id}`, {
    preserveScroll: true,
    onSuccess: () => { deleting.value = null },
})
</script>
