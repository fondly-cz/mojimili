<template>
    <div class="rich-editor-container">
        <div ref="editorRef" class="rich-editor"></div>

        <!-- People to mention, offered while typing "@name". -->
        <Teleport to="body">
            <ul
                v-if="mention.open"
                class="fixed z-[70] w-64 overflow-hidden rounded-2xl border border-gray-100 bg-white py-1 shadow-xl"
                :style="{ top: `${mention.top}px`, left: `${mention.left}px` }"
            >
                <li v-if="matches.length === 0" class="px-4 py-2 text-xs font-bold text-gray-400">
                    K projektu není nikdo přiřazený. Lidi přidáte v detailu projektu.
                </li>
                <li
                    v-for="(user, index) in matches"
                    :key="user.id"
                    class="flex cursor-pointer items-center gap-2 px-3 py-2 text-xs font-bold text-gray-700"
                    :class="{ 'bg-brand-primary-from/10': index === mention.index }"
                    @mousedown.prevent="pickMention(user)"
                    @mouseenter="mention.index = index"
                >
                    <UserAvatar :user="user" class="h-6 w-6 text-[8px]" />
                    <span class="truncate">{{ user.name }}</span>
                </li>
            </ul>
        </Teleport>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch, onUnmounted } from 'vue'
import Editor from '@toast-ui/editor'
import '@toast-ui/editor/dist/toastui-editor.css'
import UserAvatar from './UserAvatar.vue'

const props = defineProps({
    modelValue: {
        type: String,
        default: ''
    },
    placeholder: {
        type: String,
        default: 'Zadejte popis...'
    },
    height: {
        type: String,
        default: '300px'
    },
    // People offered after typing "@"; without the prop the editor has no mentions.
    mentionUsers: {
        type: Array,
        default: null
    }
})

const emit = defineEmits(['update:modelValue'])

const editorRef = ref(null)
let editor = null

onMounted(() => {
    editor = new Editor({
        el: editorRef.value,
        height: props.height,
        initialEditType: 'wysiwyg',
        previewStyle: 'vertical',
        initialValue: props.modelValue || '',
        placeholder: props.placeholder,
        hideModeSwitch: true, // Keep it simple
        toolbarItems: [
            ['heading', 'bold', 'italic', 'strike'],
            ['hr', 'quote'],
            ['ul', 'ol', 'task', 'indent', 'outdent'],
            ['table', 'link'],
        ],
        events: {
            change: () => {
                const html = editor.getHTML()
                if (html === '<p><br></p>') {
                    emit('update:modelValue', '')
                } else {
                    emit('update:modelValue', html)
                }
                refreshMention()
            },
            caretChange: () => refreshMention(),
            blur: () => closeMention()
        }
    })

    // Capture phase, so the keys steer the list before the editor handles them.
    editorRef.value.addEventListener('keydown', onMentionKeydown, true)
    window.addEventListener('scroll', closeMention, true)
})

// --- Mentions ---
// The editor only inserts "@Name" as text; the server turns it into a mention (App\Support\Mentions).
const mention = reactive({ open: false, query: '', index: 0, top: 0, left: 0 })
// Where the list was closed with Escape, so it stays closed until another "@" is typed.
let dismissedAt = null

const fold = (text) => text.normalize('NFD').replace(/\p{M}/gu, '').toLowerCase().trim()

const matches = computed(() => {
    const query = fold(mention.query)

    return (props.mentionUsers || [])
        .filter(user => {
            const name = fold(user.name)

            return name.startsWith(query) || name.split(/\s+/).some(word => word.startsWith(query))
        })
        .slice(0, 8)
})

const closeMention = () => {
    mention.open = false
}

// The "@query" right before the caret, if the caret is inside one.
const mentionAtCaret = () => {
    const [from, to] = editor.getSelection()
    if (from !== to) return null

    const before = editor.getSelectedText(Math.max(0, to - 80), to).split('\n').pop()
    const found = before.match(/(?:^|[\s(])@((?:[^\s@]+(?:[ \u00a0][^\s@]*){0,3})?)$/)

    return found ? { query: found[1], start: to - found[1].length - 1, end: to } : null
}

const refreshMention = () => {
    if (!props.mentionUsers || !editor) return

    const found = mentionAtCaret()
    if (!found || found.start === dismissedAt) return closeMention()

    dismissedAt = null
    mention.query = found.query
    mention.index = Math.min(mention.index, Math.max(matches.value.length - 1, 0))

    const typedWholeName = matches.value.some(user => fold(user.name) === fold(found.query))
    // With nobody assigned, a bare "@" still explains why the list is empty.
    const explainEmpty = props.mentionUsers.length === 0 && found.query === ''
    if (typedWholeName || (matches.value.length === 0 && !explainEmpty)) return closeMention()

    const selection = window.getSelection()
    if (!selection.rangeCount) return closeMention()
    const range = selection.getRangeAt(0)
    const rect = range.getClientRects()[0] || range.getBoundingClientRect()
    // An empty line reports no caret box; fall back to the editor itself.
    const anchor = rect.height ? rect : editorRef.value.getBoundingClientRect()

    mention.top = anchor.bottom + 6
    mention.left = Math.max(8, Math.min(anchor.left, window.innerWidth - 264))
    mention.open = true
}

const pickMention = (user) => {
    const found = mentionAtCaret()
    closeMention()
    if (!found) return

    editor.replaceSelection(`@${user.name} `, found.start, found.end)
}

const onMentionKeydown = (event) => {
    if (!mention.open) return

    const count = matches.value.length
    const handlers = {
        ArrowDown: () => { mention.index = (mention.index + 1) % count },
        ArrowUp: () => { mention.index = (mention.index - 1 + count) % count },
        Enter: () => pickMention(matches.value[mention.index]),
        Tab: () => pickMention(matches.value[mention.index]),
        Escape: () => {
            dismissedAt = mentionAtCaret()?.start ?? null
            closeMention()
        },
    }

    if (!handlers[event.key] || (count === 0 && event.key !== 'Escape')) return

    event.preventDefault()
    event.stopPropagation()
    handlers[event.key]()
}

watch(() => props.modelValue, (newValue) => {
    if (editor && newValue !== editor.getHTML()) {
        editor.setHTML(newValue || '')
    }
})

onUnmounted(() => {
    editorRef.value?.removeEventListener('keydown', onMentionKeydown, true)
    window.removeEventListener('scroll', closeMention, true)
    if (editor) {
        editor.destroy()
    }
})
</script>

<style>
/* Custom styling to match our premium UI */
.toastui-editor-defaultUI {
    border-radius: 1.5rem !important;
    border: 2px solid #f3f4f6 !important;
    overflow: hidden !important;
    background: #f9fafb !important;
}

.toastui-editor-toolbar {
    background-color: #f9fafb !important;
    border-bottom: 2px solid #f3f4f6 !important;
    padding: 8px 16px !important;
}

.toastui-editor-main {
    background-color: #fff !important;
}

.toastui-editor-ww-container {
    padding: 8px !important;
}

.toastui-editor-contents {
    font-family: inherit !important;
    font-size: 0.875rem !important;
}

.rich-editor-container {
    width: 100%;
}
</style>
