<template>
    <Layout>
        <Breadcrumbs
            :items="[
                { label: 'Nástěnka', href: '/' },
                { label: 'Firmy', href: '/companies' },
            ]"
        />
        <div class="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div class="flex items-center gap-6">
                <div class="h-20 w-20 brand-gradient rounded-[2rem] flex items-center justify-center text-white text-3xl font-black shadow-brand border-4 border-white">
                    {{ company.name.charAt(0) }}
                </div>
                <div>
                    <h1 class="text-4xl font-extrabold text-gray-900 font-heading tracking-tight leading-tight">{{ company.name }}</h1>
                    <div class="flex items-center gap-4 mt-2">
                        <span 
                            class="inline-flex items-center px-4 py-1.5 text-[10px] font-black uppercase tracking-wider rounded-full shadow-sm"
                            :class="{
                                'bg-green-500 text-white shadow-green-100': company.status === 'active',
                                'bg-gray-400 text-white shadow-gray-100': company.status === 'inactive',
                                'bg-brand-accent text-white shadow-blue-100': company.status === 'prospect'
                            }"
                        >
                            {{ company.status === 'active' ? 'Aktivní' : (company.status === 'inactive' ? 'Neaktivní' : 'Potenciální') }}
                        </span>
                        <span v-if="company.industry" class="text-xs font-bold text-gray-400 flex items-center gap-2">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                            </svg>
                            {{ company.industry }}
                        </span>
                    </div>
                </div>
            </div>
            <div class="flex items-center gap-4">
                <Link
                    :href="`/companies/${company.id}/edit`"
                    class="inline-flex items-center gap-2 brand-gradient px-8 py-4 rounded-full font-bold text-white shadow-brand hover:shadow-brand-lg transition-all hover:-translate-y-1 font-heading"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                    </svg>
                    Upravit
                </Link>
                <Link
                    href="/companies"
                    class="px-8 py-4 bg-white border-2 border-gray-100 rounded-full font-bold text-gray-400 hover:text-brand-primary-from hover:border-brand-primary-from transition-all"
                >
                    Zpět
                </Link>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-10">
            <!-- Main Info -->
            <div class="lg:col-span-8 space-y-10">
                <div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-50 overflow-hidden">
                    <div class="p-10 border-b border-gray-50">
                        <h2 class="text-2xl font-black text-gray-900 font-heading">Informace o firmě</h2>
                    </div>
                    <div class="p-10">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-10">
                            <div>
                                <label class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 block mb-4">Kontaktní údaje</label>
                                <div class="space-y-6">
                                    <div v-if="company.email" class="flex items-center gap-4 group">
                                        <div class="h-10 w-10 bg-gray-50 rounded-xl flex items-center justify-center text-brand-primary-from group-hover:bg-brand-primary-from group-hover:text-white transition-all">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                                            </svg>
                                        </div>
                                        <a :href="`mailto:${company.email}`" class="text-sm font-bold text-gray-700 hover:text-brand-primary-from transition-colors">{{ company.email }}</a>
                                    </div>
                                    <div v-if="company.phone" class="flex items-center gap-4 group">
                                        <div class="h-10 w-10 bg-gray-50 rounded-xl flex items-center justify-center text-brand-primary-to group-hover:bg-brand-primary-to group-hover:text-white transition-all">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" />
                                            </svg>
                                        </div>
                                        <a :href="`tel:${company.phone}`" class="text-sm font-bold text-gray-700 hover:text-brand-primary-to transition-colors">{{ company.phone }}</a>
                                    </div>
                                    <div v-if="company.website" class="flex items-center gap-4 group">
                                        <div class="h-10 w-10 bg-gray-50 rounded-xl flex items-center justify-center text-brand-accent group-hover:bg-brand-accent group-hover:text-white transition-all">
                                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9m-9 9a9 9 0 019-9" />
                                            </svg>
                                        </div>
                                        <a :href="company.website" target="_blank" class="text-sm font-bold text-gray-700 hover:text-brand-accent transition-colors">{{ company.website }}</a>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="text-[10px] font-black uppercase tracking-[0.2em] text-gray-400 block mb-4">Adresa</label>
                                <div v-if="hasAddress" class="flex gap-4">
                                    <div class="h-10 w-10 bg-gray-50 rounded-xl flex items-center justify-center text-gray-400">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                                        </svg>
                                    </div>
                                    <div class="text-sm font-bold text-gray-700 leading-relaxed">
                                        <div v-if="company.address">{{ company.address }}</div>
                                        <div>
                                            {{ company.city }}<span v-if="company.postal_code">, {{ company.postal_code }}</span>
                                        </div>
                                        <div v-if="company.country" class="text-xs text-gray-400 uppercase tracking-widest mt-1">{{ company.country }}</div>
                                    </div>
                                </div>
                                <div v-else class="text-sm font-bold text-gray-300 italic">Adresa nebyla vyplněna</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div v-if="company.notes" class="bg-white rounded-[2.5rem] shadow-sm border border-gray-50 p-10">
                    <h2 class="text-2xl font-black text-gray-900 font-heading mb-6">Poznámky</h2>
                    <div class="text-gray-600 leading-loose whitespace-pre-wrap">{{ company.notes }}</div>
                </div>

                <!-- Employees -->
                <div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-50 overflow-hidden">
                    <div class="p-10 border-b border-gray-50 flex justify-between items-center">
                        <h2 class="text-2xl font-black text-gray-900 font-heading">Zaměstnanci</h2>
                        <button @click="isAddingEmployee = !isAddingEmployee" class="text-sm font-bold text-brand-primary-from hover:text-brand-primary-to transition-colors">
                            {{ isAddingEmployee ? 'Zrušit' : '+ Přidat zaměstnance' }}
                        </button>
                    </div>

                    <div v-if="isAddingEmployee" class="p-10 bg-gray-50 border-b border-gray-50">
                        <form @submit.prevent="submitEmployee" class="space-y-4">
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-1">Jméno *</label>
                                    <input v-model="employeeForm.name" type="text" class="w-full px-4 py-3 bg-white border-none rounded-xl text-sm font-bold text-gray-700 shadow-sm focus:ring-2 focus:ring-brand-primary-from" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-1">E-mail *</label>
                                    <input v-model="employeeForm.email" type="email" class="w-full px-4 py-3 bg-white border-none rounded-xl text-sm font-bold text-gray-700 shadow-sm focus:ring-2 focus:ring-brand-primary-from" required>
                                </div>
                                <div>
                                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-1">Telefon</label>
                                    <input v-model="employeeForm.phone" type="text" class="w-full px-4 py-3 bg-white border-none rounded-xl text-sm font-bold text-gray-700 shadow-sm focus:ring-2 focus:ring-brand-primary-from">
                                </div>
                                <div>
                                    <label class="block text-xs font-black text-gray-400 uppercase tracking-widest mb-1">Pozice</label>
                                    <input v-model="employeeForm.position" type="text" class="w-full px-4 py-3 bg-white border-none rounded-xl text-sm font-bold text-gray-700 shadow-sm focus:ring-2 focus:ring-brand-primary-from">
                                </div>
                            </div>
                            <div class="flex justify-end mt-4">
                                <button type="submit" :disabled="employeeForm.processing" class="px-6 py-3 bg-brand-primary-from text-white rounded-xl font-bold hover:bg-brand-primary-to transition-colors shadow-sm disabled:opacity-50">
                                    Uložit zaměstnance
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="p-0">
                        <div v-if="company.employees && company.employees.length > 0" class="divide-y divide-gray-50">
                            <div v-for="employee in company.employees" :key="employee.id" class="p-6 md:px-10 flex items-center justify-between hover:bg-gray-50 transition-colors group">
                                <div class="flex items-center gap-4">
                                    <div class="h-10 w-10 bg-brand-primary-from/10 rounded-full flex items-center justify-center text-brand-primary-from font-bold">
                                        {{ employee.name.charAt(0) }}
                                    </div>
                                    <div>
                                        <div class="font-bold text-gray-900 flex items-center gap-2">
                                            {{ employee.name }}
                                            <span v-if="employee.user_id" class="px-2 py-0.5 bg-green-100 text-green-700 text-[9px] font-black uppercase tracking-widest rounded-full" title="Tento zaměstnanec má aktivní účet v systému">Aktivní účet</span>
                                        </div>
                                        <div class="text-xs text-gray-500 mt-1 flex gap-3">
                                            <span v-if="employee.position" class="font-medium text-gray-700">{{ employee.position }}</span>
                                            <a v-if="employee.email" :href="`mailto:${employee.email}`" class="hover:text-brand-primary-from transition-colors">{{ employee.email }}</a>
                                            <span v-if="employee.email && employee.phone"> • </span>
                                            <a v-if="employee.phone" :href="`tel:${employee.phone}`" class="hover:text-brand-primary-from transition-colors">{{ employee.phone }}</a>
                                        </div>
                                    </div>
                                </div>
                                <button @click="deleteEmployee(employee)" class="h-8 w-8 bg-red-50 text-red-500 rounded-full flex items-center justify-center opacity-0 group-hover:opacity-100 transition-all hover:bg-red-500 hover:text-white" title="Odebrat zaměstnance">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg>
                                </button>
                            </div>
                        </div>
                        <div v-else class="p-10 text-center text-gray-400 text-sm font-bold">
                            K této firmě zatím nejsou přiřazeni žádní zaměstnanci.
                        </div>
                    </div>
                </div>
            </div>

            <!-- Sidebar Info -->
            <div class="lg:col-span-4 space-y-8">
                <div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-50 p-10">
                    <h3 class="text-lg font-black text-gray-900 font-heading mb-8">Detaily</h3>
                    <div class="space-y-8">
                        <div v-if="company.employee_count">
                            <span class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] block mb-2">Počet zaměstnanců</span>
                            <span class="text-xl font-bold text-gray-900">{{ company.employee_count }}</span>
                        </div>
                        <div v-if="company.annual_revenue">
                            <span class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] block mb-2">Roční obrat</span>
                            <span class="text-xl font-bold brand-text-gradient font-heading">{{ formatCurrency(company.annual_revenue) }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] block mb-2">Vytvořeno</span>
                            <span class="text-sm font-bold text-gray-700">{{ formatDate(company.created_at) }}</span>
                        </div>
                        <div>
                            <span class="text-[10px] font-black text-gray-400 uppercase tracking-[0.2em] block mb-2">Poslední aktualizace</span>
                            <span class="text-sm font-bold text-gray-700">{{ formatDate(company.updated_at) }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Layout>
</template>

<script setup>
import { computed, ref } from 'vue'
import { Link, useForm, router } from '@inertiajs/vue3'
import Layout from '../../Components/Layout.vue'
import Breadcrumbs from '../../Components/Breadcrumbs.vue'

const props = defineProps({
    company: Object,
})

const hasAddress = computed(() => {
    return props.company.address || props.company.city || props.company.state || 
           props.company.postal_code || props.company.country
})

const formatCurrency = (amount) => {
    return new Intl.NumberFormat('en-US').format(amount)
}

const formatDate = (dateString) => {
    return new Date(dateString).toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    })
}

const isAddingEmployee = ref(false)

const employeeForm = useForm({
    name: '',
    email: '',
    phone: '',
    position: '',
})

const submitEmployee = () => {
    employeeForm.post(`/companies/${props.company.id}/employees`, {
        preserveScroll: true,
        onSuccess: () => {
            employeeForm.reset()
            isAddingEmployee.value = false
        }
    })
}

const deleteEmployee = (employee) => {
    if (confirm(`Opravdu chcete odebrat zaměstnance ${employee.name}?`)) {
        router.delete(`/companies/${props.company.id}/employees/${employee.id}`, {
            preserveScroll: true
        })
    }
}
</script>