<template>
    <Layout>
        <Breadcrumbs
            :items="[
                { label: 'Nástěnka', href: '/' },
                { label: 'Projekty', href: '/projects' },
            ]"
        />
        <div class="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <h1 class="text-4xl font-extrabold text-gray-900 font-heading tracking-tight">Nový projekt</h1>
                <p class="text-gray-500 mt-2 font-medium">Založte projekt, do kterého pak přidáte seznamy úkolů.</p>
            </div>
            <div class="flex gap-3">
                <Link
                    href="/projects"
                    class="inline-flex items-center gap-2 bg-white border-2 border-gray-100 px-8 py-4 rounded-full font-bold text-gray-400 hover:text-gray-600 hover:border-gray-200 transition-all font-heading uppercase tracking-widest text-[10px]"
                >
                    Zpět na výpis
                </Link>
            </div>
        </div>

        <div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-50 overflow-hidden relative max-w-5xl">
            <div class="absolute -right-20 -top-20 h-64 w-64 brand-gradient opacity-[0.03] rounded-full blur-3xl pointer-events-none"></div>

            <form @submit.prevent="submit">
                <div class="p-10 space-y-8 relative z-10">
                    <h2 class="text-xl font-black text-gray-900 font-heading uppercase tracking-widest border-b border-gray-50 pb-4">Základní údaje</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Název projektu <span class="text-red-500">*</span></label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                                placeholder="Např. Redesign webu"
                            >
                            <p v-if="form.errors.name" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Firma</label>
                            <select
                                v-model="form.company_id"
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all appearance-none cursor-pointer"
                            >
                                <option value="">Bez firmy</option>
                                <option v-for="company in companies" :key="company.id" :value="company.id">{{ company.name }}</option>
                            </select>
                            <p v-if="form.errors.company_id" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.company_id }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Stav</label>
                            <select
                                v-model="form.status"
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all appearance-none cursor-pointer"
                            >
                                <option value="active">Aktivní</option>
                                <option value="on_hold">Pozastavený</option>
                                <option value="done">Dokončený</option>
                                <option value="archived">Archivovaný</option>
                            </select>
                            <p v-if="form.errors.status" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.status }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Popis</label>
                            <textarea
                                v-model="form.description"
                                rows="4"
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all resize-none"
                                placeholder="Interní poznámka k projektu..."
                            ></textarea>
                            <p v-if="form.errors.description" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.description }}</p>
                        </div>
                    </div>
                </div>

                <div class="px-10 py-6 bg-gray-50/50 border-t border-gray-50 flex justify-end gap-3">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 brand-gradient px-8 py-4 rounded-full font-bold text-white shadow-brand hover:shadow-brand-lg transition-all hover:-translate-y-1 font-heading disabled:opacity-50 disabled:hover:translate-y-0"
                    >
                        Vytvořit projekt
                    </button>
                </div>
            </form>
        </div>
    </Layout>
</template>

<script setup>
import { Link, useForm } from '@inertiajs/vue3'
import Layout from '../../Components/Layout.vue'
import Breadcrumbs from '../../Components/Breadcrumbs.vue'

defineProps({
    companies: Array,
})

const form = useForm({
    name: '',
    description: '',
    company_id: '',
    status: 'active',
})

const submit = () => {
    form.post('/projects')
}
</script>
