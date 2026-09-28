<template>
    <Layout>
        <Breadcrumbs
            :items="[
                { label: 'Nástěnka', href: '/' },
                { label: 'Projekty', href: '/projects' },
                { label: project.name, href: `/projects/${project.id}` },
            ]"
        />
        <div class="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <h1 class="text-4xl font-extrabold text-gray-900 font-heading tracking-tight">Upravit projekt</h1>
                <p class="text-gray-500 mt-2 font-medium">{{ project.name }}</p>
            </div>
            <div class="flex gap-3">
                <Link
                    :href="`/projects/${project.id}`"
                    class="inline-flex items-center gap-2 bg-white border-2 border-gray-100 px-8 py-4 rounded-full font-bold text-gray-400 hover:text-gray-600 hover:border-gray-200 transition-all font-heading uppercase tracking-widest text-[10px]"
                >
                    Zpět na projekt
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

                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Hodinová sazba (Kč bez DPH)</label>
                            <input
                                v-model="form.hourly_rate"
                                type="number"
                                min="0"
                                step="0.01"
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                                placeholder="Např. 1000"
                            >
                            <p class="mt-2 text-xs text-gray-400 font-semibold ml-1">Výchozí sazba pro nové výkazy. Každý výkaz může mít vlastní.</p>
                            <p v-if="form.errors.hourly_rate" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.hourly_rate }}</p>
                        </div>

                        <ProjectPlanningFields :form="form" />

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

                <div class="px-10 pb-10 space-y-6 relative z-10">
                    <div class="border-b border-gray-50 pb-4">
                        <h2 class="text-xl font-black text-gray-900 font-heading uppercase tracking-widest">Sazby lidí v projektu</h2>
                        <p class="mt-2 text-xs text-gray-400 font-semibold">Má přednost před výchozí sazbou projektu. Prázdné pole = použije se sazba projektu.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div v-for="(rate, index) in form.user_rates" :key="rate.user_id" class="flex flex-wrap items-center gap-4">
                            <label :for="`rate-${rate.user_id}`" class="flex-1 text-sm font-bold text-gray-700 truncate">{{ userName(rate.user_id) }}</label>
                            <input
                                :id="`rate-${rate.user_id}`"
                                v-model="rate.hourly_rate"
                                type="number"
                                min="0"
                                step="0.01"
                                :placeholder="form.hourly_rate || '—'"
                                class="block w-40 px-4 py-2.5 bg-gray-50 border-gray-50 rounded-xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                            >
                            <p v-if="form.errors[`user_rates.${index}.hourly_rate`]" class="w-full text-xs text-red-500 font-bold">{{ form.errors[`user_rates.${index}.hourly_rate`] }}</p>
                        </div>
                    </div>
                </div>

                <div class="px-10 py-6 bg-gray-50/50 border-t border-gray-50 flex justify-end gap-3">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="inline-flex items-center gap-2 brand-gradient px-8 py-4 rounded-full font-bold text-white shadow-brand hover:shadow-brand-lg transition-all hover:-translate-y-1 font-heading disabled:opacity-50 disabled:hover:translate-y-0"
                    >
                        Uložit změny
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
import ProjectPlanningFields from '../../Components/ProjectPlanningFields.vue'

const props = defineProps({
    project: Object,
    companies: Array,
    users: Array,
})

const userRate = (userId) => props.project.user_rates?.find(u => u.id === userId)?.pivot.hourly_rate ?? ''
const userName = (userId) => props.users.find(u => u.id === userId)?.name

const form = useForm({
    name: props.project.name,
    description: props.project.description || '',
    company_id: props.project.company_id || '',
    status: props.project.status,
    hourly_rate: props.project.hourly_rate ?? '',
    due_date: props.project.due_date ?? '',
    budget: props.project.budget ?? '',
    budget_minutes: props.project.budget_minutes ?? '',
    user_rates: props.users.map(user => ({ user_id: user.id, hourly_rate: userRate(user.id) })),
})

const submit = () => {
    form.put(`/projects/${props.project.id}`)
}
</script>
