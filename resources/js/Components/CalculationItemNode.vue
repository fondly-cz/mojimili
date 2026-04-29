<template>
    <div class="relative group bg-white border-2 rounded-3xl p-6 mb-4 transition-all shadow-sm flex items-start gap-4 cursor-move"
         :class="[
             dropTargetId === item.unique_id ? 'border-brand-primary-from bg-brand-primary-from/5 scale-[1.02]' : 'border-gray-100 hover:border-brand-primary-from/30',
             draggedId === item.unique_id ? 'opacity-30' : ''
         ]"
         draggable="true"
         @dragstart.stop="handleDragStart($event, item.unique_id)"
         @dragover.prevent.stop="isValidDropTarget(item.unique_id) ? setDropTarget(item.unique_id) : null"
         @dragleave.prevent.stop="clearDropTarget(item.unique_id)"
         @drop.prevent.stop="handleDrop(item.unique_id)"
    >
        <button @click.stop="removeService(item.unique_id)" class="absolute -right-2 -top-2 h-7 w-7 bg-red-500 text-white rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity shadow-lg hover:scale-110 z-20">
            <span class="text-xs font-bold">×</span>
        </button>

        <div class="flex flex-col items-center gap-1 shrink-0">
            <button
                type="button"
                @click.stop="moveUp(item.unique_id)"
                :disabled="isFirstSibling"
                class="h-6 w-6 rounded-lg bg-gray-50 text-gray-400 hover:bg-brand-primary-from/10 hover:text-brand-primary-from transition-all flex items-center justify-center disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-gray-50 disabled:hover:text-gray-400"
                title="Posunout nahoru"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 15l7-7 7 7" />
                </svg>
            </button>
            <div class="h-10 w-10 bg-gray-50 rounded-xl flex items-center justify-center text-xl shadow-sm group-hover:bg-white transition-colors">
                {{ item.icon }}
            </div>
            <button
                type="button"
                @click.stop="moveDown(item.unique_id)"
                :disabled="isLastSibling"
                class="h-6 w-6 rounded-lg bg-gray-50 text-gray-400 hover:bg-brand-primary-from/10 hover:text-brand-primary-from transition-all flex items-center justify-center disabled:opacity-30 disabled:cursor-not-allowed disabled:hover:bg-gray-50 disabled:hover:text-gray-400"
                title="Posunout dolů"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 9l-7 7-7-7" />
                </svg>
            </button>
        </div>
        
        <div class="grow">
            <div class="flex items-center gap-2">
                <h4 class="text-sm font-black text-gray-900 font-heading leading-tight">{{ item.name }}</h4>
                <span class="px-2 py-0.5 text-[8px] font-black rounded-lg uppercase tracking-widest border" :class="getPeriodStyles(item.payment_period)">
                    {{ getPeriodLabel(item.payment_period) }}
                </span>
                <span v-if="children.length > 0" class="px-2 py-0.5 bg-gray-100 text-[8px] font-black rounded-lg text-gray-400 uppercase tracking-widest">{{ children.length }} podslužeb</span>
                <label class="flex items-center gap-1.5 cursor-pointer ml-2">
                    <input type="checkbox" v-model="item.is_required" class="w-3.5 h-3.5 rounded border-gray-300 text-brand-primary-from focus:ring-brand-primary-from transition-all">
                    <span class="text-[9px] font-bold text-gray-500 uppercase tracking-widest">Povinné</span>
                </label>
            </div>
            
            <div class="flex flex-wrap items-center gap-3 mt-2">
                <div class="relative">
                    <input 
                        :value="showVat ? Number((item.price * 1.21).toFixed(2)) : item.price" 
                        @input="e => item.price = showVat ? (parseFloat(e.target.value) || 0) / 1.21 : (parseFloat(e.target.value) || 0)"
                        type="number" 
                        step="0.01"
                        class="w-24 px-2 py-1 text-xs font-black brand-text-gradient bg-gray-50 border-none rounded-lg focus:ring-1 focus:ring-brand-primary-from transition-all" 
                        placeholder="Cena"
                    >
                    <div class="absolute right-2 top-1.5 text-[8px] font-black pointer-events-none opacity-30">Kč {{ showVat ? 's DPH' : '' }}</div>
                </div>
                <div class="relative">
                    <input v-model.number="item.days" type="number" class="w-16 px-2 py-1 text-xs font-black text-gray-500 bg-gray-50 border-none rounded-lg focus:ring-1 focus:ring-brand-primary-from transition-all" placeholder="Dny">
                    <div class="absolute right-1 top-1.5 text-[8px] font-black pointer-events-none opacity-30">dní</div>
                </div>
            </div>
            
            <div class="mt-3">
                <textarea v-model="item.description" rows="2" class="w-full px-3 py-2 text-xs text-gray-500 bg-gray-50 border-none rounded-xl focus:ring-1 focus:ring-brand-primary-from transition-all resize-none" placeholder="Popis služby (zobrazí se zákazníkovi)..."></textarea>
            </div>

            <!-- Children -> recursive mapping -->
            <div v-if="children.length > 0" class="mt-4 pt-4 border-t-2 border-dashed border-gray-50 pl-4 border-l-2">
                <CalculationItemNode
                    v-for="child in children"
                    :key="child.unique_id"
                    :item="child"
                    :all-items="allItems"
                    :dragged-id="draggedId"
                    :drop-target-id="dropTargetId"
                    :show-vat="showVat"
                    @drag-start="$emit('drag-start', $event)"
                    @set-drop-target="$emit('set-drop-target', $event)"
                    @clear-drop-target="$emit('clear-drop-target', $event)"
                    @drop-item="$emit('drop-item', $event)"
                    @remove-item="$emit('remove-item', $event)"
                    @move-up="$emit('move-up', $event)"
                    @move-down="$emit('move-down', $event)"
                />
            </div>
        </div>
        
        <div class="p-2 text-gray-200 cursor-move">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M4 8h16M4 16h16" />
            </svg>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    item: Object,
    allItems: Array,
    draggedId: String,
    dropTargetId: String,
    showVat: Boolean
})

const emit = defineEmits(['drag-start', 'set-drop-target', 'clear-drop-target', 'drop-item', 'remove-item', 'move-up', 'move-down'])

const children = computed(() => {
    return props.allItems.filter(i => i.parent_id === props.item.unique_id)
})

const siblings = computed(() => {
    return props.allItems.filter(i => i.parent_id === props.item.parent_id)
})

const isFirstSibling = computed(() => {
    return siblings.value[0]?.unique_id === props.item.unique_id
})

const isLastSibling = computed(() => {
    return siblings.value[siblings.value.length - 1]?.unique_id === props.item.unique_id
})

const moveUp = (id) => {
    emit('move-up', id)
}

const moveDown = (id) => {
    emit('move-down', id)
}

const handleDragStart = (e, id) => {
    emit('drag-start', { event: e, id })
}

const isValidDropTarget = (targetId) => {
    if (!props.draggedId) return false
    if (props.draggedId === targetId) return false
    
    // Prevent dragging a parent into its own descendants (cycle detection)
    let current = props.allItems.find(i => i.unique_id === targetId)
    while (current && current.parent_id) {
        if (current.parent_id === props.draggedId) {
            return false // target is descendant of dragged item
        }
        current = props.allItems.find(i => i.unique_id === current.parent_id)
    }
    
    return true
}

const setDropTarget = (id) => {
    emit('set-drop-target', id)
}

const clearDropTarget = (id) => {
    emit('clear-drop-target', id)
}

const handleDrop = (id) => {
    emit('drop-item', id)
}

const removeService = (id) => {
    emit('remove-item', id)
}

const getPeriodLabel = (period) => {
    const labels = {
        once: 'Jednorázově',
        monthly: 'Měsíčně',
        yearly: 'Ročně'
    }
    return labels[period] || period
}

const getPeriodStyles = (period) => {
    const styles = {
        once: 'bg-gray-50 text-gray-500 border-gray-100',
        monthly: 'bg-blue-50 text-blue-600 border-blue-100',
        yearly: 'bg-purple-50 text-purple-600 border-purple-100'
    }
    return styles[period] || 'bg-gray-50 text-gray-500 border-gray-100'
}
</script>
