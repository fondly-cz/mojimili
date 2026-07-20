<template>
    <Modal :show="show" title="Seznam úkolů z kalkulace" max-width="2xl" @close="$emit('close')">
        <template #content>
            <!-- Step 1: which calculation (only when opened from a project) -->
            <div v-if="!calculation" class="mb-8">
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Kalkulace <span class="text-red-500">*</span></label>
                <select
                    v-model="selectedCalculationId"
                    class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all appearance-none cursor-pointer"
                >
                    <option value="">Vyberte kalkulaci…</option>
                    <option v-for="calc in calculations" :key="calc.id" :value="calc.id">
                        {{ calc.customer_company || calc.customer_name }}
                        ({{ calc.status === 'confirmed' ? 'odsouhlasená' : 'rozpracovaná' }})
                    </option>
                </select>
                <p v-if="loadingItems" class="mt-3 text-xs font-bold text-gray-400 ml-1">Načítám položky…</p>
            </div>

            <!-- Step 2: which project (only when opened from a calculation) -->
            <div v-if="!projectId" class="mb-8">
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Projekt <span class="text-red-500">*</span></label>
                <select
                    v-model="form.project_id"
                    class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all appearance-none cursor-pointer"
                >
                    <option value="">Založit nový projekt…</option>
                    <option v-for="project in projects" :key="project.id" :value="project.id">{{ project.name }}</option>
                </select>

                <div v-if="!form.project_id" class="mt-4">
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Název nového projektu <span class="text-red-500">*</span></label>
                    <input
                        v-model="form.project_name"
                        type="text"
                        class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                        :placeholder="defaultProjectName"
                    >
                    <p v-if="form.errors.project_name" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.project_name }}</p>
                </div>
                <p v-if="form.errors.project_id" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.project_id }}</p>
            </div>

            <!-- Step 3: which items -->
            <div v-if="items.length > 0">
                <div class="flex items-center justify-between mb-3">
                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1">
                        Položky ({{ form.item_ids.length }} z {{ items.length }})
                    </label>
                    <div class="flex gap-3">
                        <button type="button" @click="selectAccepted" class="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-brand-primary-from transition-colors">
                            Jen odsouhlasené
                        </button>
                        <button type="button" @click="selectAll" class="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-brand-primary-from transition-colors">
                            Vše
                        </button>
                        <button type="button" @click="form.item_ids = []" class="text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-brand-primary-from transition-colors">
                            Nic
                        </button>
                    </div>
                </div>

                <div class="max-h-72 overflow-y-auto rounded-2xl border-2 border-gray-50 p-2 space-y-1">
                    <label
                        v-for="item in items"
                        :key="item.id"
                        class="flex items-start gap-3 rounded-xl px-3 py-2.5 hover:bg-gray-50 transition-colors cursor-pointer"
                        :style="{ paddingLeft: `${0.75 + depthOf(item) * 1.25}rem` }"
                    >
                        <input
                            type="checkbox"
                            v-model="form.item_ids"
                            :value="item.id"
                            class="mt-0.5 rounded border-gray-300 text-brand-primary-from focus:ring-brand-primary-from"
                        >
                        <span class="grow min-w-0">
                            <span class="block text-sm font-bold text-gray-900 leading-tight">{{ item.name }}</span>
                            <span class="mt-0.5 flex flex-wrap items-center gap-2">
                                <span v-if="item.days > 0" class="text-[9px] font-black uppercase tracking-widest text-gray-400">{{ item.days }} dní</span>
                                <span v-if="item.is_accepted" class="px-2 py-0.5 bg-green-50 text-[8px] font-black rounded-lg text-green-600 uppercase tracking-widest border border-green-100">
                                    odsouhlaseno
                                </span>
                            </span>
                        </span>
                    </label>
                </div>

                <p class="mt-3 text-[11px] font-semibold text-gray-400 ml-1">
                    Vyberete-li podpoložku, nadřazené položky se do seznamu přidají automaticky, aby zůstalo zachované zanoření.
                </p>
                <p v-if="form.errors.item_ids" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.item_ids }}</p>
            </div>

            <p v-else-if="activeCalculationId && !loadingItems" class="text-sm font-bold text-gray-300 text-center py-8">
                Tato kalkulace nemá žádné položky.
            </p>

            <!-- Optional list name -->
            <div v-if="items.length > 0" class="mt-8">
                <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Název seznamu</label>
                <input
                    v-model="form.name"
                    type="text"
                    class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                    :placeholder="defaultListName"
                >
            </div>
        </template>

        <template #footer>
            <button
                @click="submit"
                :disabled="!canSubmit || form.processing"
                class="brand-gradient px-8 py-3 rounded-full font-bold text-white shadow-brand transition-all font-heading disabled:opacity-40"
            >
                Vytvořit seznam úkolů
            </button>
            <button @click="$emit('close')" class="px-6 py-3 rounded-full text-[10px] font-black uppercase tracking-widest text-gray-400 hover:text-gray-600 transition-colors">
                Zrušit
            </button>
        </template>
    </Modal>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import { useForm } from '@inertiajs/vue3'
import axios from 'axios'
import Modal from './Modal.vue'

const props = defineProps({
    show: Boolean,
    // Opened from a calculation: the calculation is fixed, the project is chosen.
    calculation: Object,
    projects: {
        type: Array,
        default: () => [],
    },
    // Opened from a project: the project is fixed, the calculation is chosen.
    projectId: Number,
    calculations: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['close'])

const selectedCalculationId = ref('')
const items = ref([])
const loadingItems = ref(false)

const form = useForm({
    project_id: '',
    project_name: '',
    name: '',
    item_ids: [],
})

const activeCalculationId = computed(() => props.calculation?.id || selectedCalculationId.value || null)

const defaultProjectName = computed(
    () => props.calculation?.customer_company || props.calculation?.customer_name || 'Nový projekt',
)

const defaultListName = computed(() => {
    if (props.calculation) {
        return props.calculation.customer_company || props.calculation.customer_name
    }
    const calc = props.calculations.find(c => c.id === Number(selectedCalculationId.value))
    return calc ? (calc.customer_company || calc.customer_name) : 'Podle kalkulace'
})

const canSubmit = computed(() => {
    if (!activeCalculationId.value) return false
    if (form.item_ids.length === 0) return false
    if (!props.projectId && !form.project_id && !form.project_name.trim()) return false
    return true
})

const depthOf = (item) => {
    let depth = 0
    let current = item
    while (current?.parent_id) {
        current = items.value.find(i => i.id === current.parent_id)
        if (!current) break
        depth++
    }
    return depth
}

const selectAll = () => {
    form.item_ids = items.value.map(i => i.id)
}

const selectAccepted = () => {
    form.item_ids = items.value.filter(i => i.is_accepted).map(i => i.id)
}

// Items come from the prop when opened on a calculation page, otherwise they are
// fetched once a calculation is picked.
const loadItems = async () => {
    if (props.calculation) {
        items.value = props.calculation.items || []
        selectAccepted()
        if (form.item_ids.length === 0) selectAll()
        return
    }

    if (!selectedCalculationId.value) {
        items.value = []
        form.item_ids = []
        return
    }

    loadingItems.value = true
    try {
        const { data } = await axios.get(`/api/calculations/${selectedCalculationId.value}/items`)
        items.value = data
        selectAccepted()
        if (form.item_ids.length === 0) selectAll()
    } finally {
        loadingItems.value = false
    }
}

watch(() => props.show, (visible) => {
    if (!visible) return
    form.reset()
    form.clearErrors()
    selectedCalculationId.value = ''
    items.value = []
    loadItems()
})

watch(selectedCalculationId, loadItems)

const submit = () => {
    form
        .transform(data => ({
            ...data,
            project_id: props.projectId || data.project_id || null,
            project_name: props.projectId ? null : data.project_name,
        }))
        .post(`/calculations/${activeCalculationId.value}/todolist`, {
            preserveScroll: true,
            onSuccess: () => emit('close'),
        })
}
</script>
