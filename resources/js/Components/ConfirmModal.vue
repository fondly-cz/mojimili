<template>
    <Transition
        enter-active-class="ease-out duration-300"
        enter-from-class="opacity-0"
        enter-to-class="opacity-100"
        leave-active-class="ease-in duration-200"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
    >
        <div v-if="show" class="fixed inset-0 z-100 overflow-y-auto">
            <div class="flex min-h-full items-center justify-center p-4 text-center sm:p-0">
                <!-- Backdrop -->
                <div 
                    class="fixed inset-0 bg-gray-900/60 backdrop-blur-md transition-opacity" 
                    @click="close"
                ></div>

                <!-- Modal Content -->
                <Transition
                    enter-active-class="ease-out duration-300"
                    enter-from-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                    enter-to-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-active-class="ease-in duration-200"
                    leave-from-class="opacity-100 translate-y-0 sm:scale-100"
                    leave-to-class="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                >
                    <div 
                        v-if="show"
                        class="relative transform overflow-hidden rounded-[2.5rem] bg-white text-left shadow-2xl transition-all sm:my-8 sm:w-full sm:max-w-lg border border-gray-100"
                    >
                        <div class="bg-white px-8 pt-10 pb-8 sm:p-10 sm:pb-8">
                            <div class="sm:flex sm:items-start">
                                <div class="mx-auto flex h-16 w-16 shrink-0 items-center justify-center rounded-2xl bg-red-50 sm:mx-0">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </div>
                                <div class="mt-6 text-center sm:mt-0 sm:ml-6 sm:text-left">
                                    <h3 class="text-2xl font-black text-gray-900 font-heading tracking-tight leading-none" id="modal-title">
                                        {{ title }}
                                    </h3>
                                    <div class="mt-4">
                                        <p class="text-gray-500 font-medium leading-relaxed">
                                            {{ message }}
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="bg-gray-50/50 px-8 py-8 sm:flex sm:flex-row-reverse sm:px-10 gap-4 mt-2">
                            <button 
                                type="button" 
                                @click="confirm"
                                class="inline-flex w-full justify-center rounded-2xl bg-red-500 px-8 py-4 text-xs font-black uppercase tracking-widest text-white shadow-lg shadow-red-500/20 hover:bg-red-600 transition-all hover:-translate-y-1 sm:w-auto font-heading"
                            >
                                {{ confirmButton }}
                            </button>
                            <button 
                                v-if="cancelButton"
                                type="button" 
                                @click="close"
                                class="mt-3 inline-flex w-full justify-center rounded-2xl border-2 border-gray-100 bg-white px-8 py-4 text-xs font-black uppercase tracking-widest text-gray-400 hover:text-gray-600 hover:border-gray-200 transition-all sm:mt-0 sm:w-auto font-heading"
                            >
                                {{ cancelButton }}
                            </button>
                        </div>
                    </div>
                </Transition>
            </div>
        </div>
    </Transition>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue'

const props = defineProps({
    show: Boolean,
    title: {
        type: String,
        default: 'Opravdu?'
    },
    message: {
        type: String,
        default: 'Tuto akci nelze vzít zpět.'
    },
    confirmButton: {
        type: String,
        default: 'Smazat'
    },
    cancelButton: {
        type: String,
        default: 'Zrušit'
    }
})

const emit = defineEmits(['close', 'confirm'])

const close = () => emit('close')
const confirm = () => emit('confirm')

const onKeyDown = (e) => {
    if (e.key === 'Escape' && props.show) {
        close()
    }
}

onMounted(() => window.addEventListener('keydown', onKeyDown))
onUnmounted(() => window.removeEventListener('keydown', onKeyDown))
</script>
