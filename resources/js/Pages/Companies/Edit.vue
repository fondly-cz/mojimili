<template>
    <Layout>
        <Breadcrumbs
            :items="[
                { label: 'Nástěnka', href: '/' },
                { label: 'Firmy', href: '/companies' },
                { label: company.name, href: `/companies/${company.id}` },
            ]"
        />
        <div class="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <h1 class="text-4xl font-extrabold text-gray-900 font-heading tracking-tight">Upravit firmu</h1>
                <p class="text-gray-500 mt-2 font-medium">Aktualizujte informace o klientovi nebo partnerovi v CRM.</p>
            </div>
            <div class="flex gap-3">
                <Link
                    href="/companies"
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
                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">IČO</label>
                            <div class="relative">
                                <input
                                    v-model="form.ico"
                                    type="text"
                                    class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all pr-28"
                                    placeholder="Nastavte IČO"
                                    @keyup.enter.prevent="loadAres"
                                >
                                <button type="button" @click="loadAres" :disabled="loadingAres" class="absolute right-2 top-2 bottom-2 bg-white shadow-sm border border-gray-100 rounded-xl px-4 text-xs font-black text-brand-primary-from uppercase tracking-widest hover:border-brand-primary-from transition-all disabled:opacity-50">
                                    <span v-if="loadingAres">...</span>
                                    <span v-else>ARES</span>
                                </button>
                            </div>
                            <p v-if="aresError" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ aresError }}</p>
                            <p v-if="form.errors.ico" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.ico }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">DIČ</label>
                            <input
                                v-model="form.dic"
                                type="text"
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                                placeholder="CZ..."
                            >
                            <p v-if="form.errors.dic" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.dic }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Název firmy <span class="text-red-500">*</span></label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                                placeholder="Např. MojiMili s.r.o."
                            >
                            <p v-if="form.errors.name" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">E-mail</label>
                            <input
                                v-model="form.email"
                                type="email"
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                                placeholder="info@firma.cz"
                            >
                            <p v-if="form.errors.email" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.email }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Telefon</label>
                            <input
                                v-model="form.phone"
                                type="text"
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                                placeholder="+420 000 000 000"
                            >
                            <p v-if="form.errors.phone" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.phone }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Webové stránky</label>
                            <input
                                v-model="form.website"
                                type="url"
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                                placeholder="https://www.firma.cz"
                            >
                            <p v-if="form.errors.website" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.website }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Odvětví</label>
                            <input
                                v-model="form.industry"
                                type="text"
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                                placeholder="E-commerce, IT, apod."
                            >
                            <p v-if="form.errors.industry" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.industry }}</p>
                        </div>
                    </div>

                    <h2 class="text-xl font-black text-gray-900 font-heading uppercase tracking-widest border-b border-gray-50 pb-4 pt-4">Adresa</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Ulice a číslo</label>
                            <input
                                v-model="form.address"
                                type="text"
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                                placeholder="Václavské náměstí 1"
                            >
                            <p v-if="form.errors.address" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.address }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Město</label>
                            <input
                                v-model="form.city"
                                type="text"
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                                placeholder="Praha"
                            >
                            <p v-if="form.errors.city" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.city }}</p>
                        </div>
                        
                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Kraj</label>
                            <input
                                v-model="form.state"
                                type="text"
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                                placeholder="Hlavní město Praha"
                            >
                            <p v-if="form.errors.state" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.state }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">PSČ</label>
                            <input
                                v-model="form.postal_code"
                                type="text"
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                                placeholder="110 00"
                            >
                            <p v-if="form.errors.postal_code" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.postal_code }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Země</label>
                            <input
                                v-model="form.country"
                                type="text"
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                                placeholder="Česká republika"
                            >
                            <p v-if="form.errors.country" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.country }}</p>
                        </div>
                    </div>

                    <h2 class="text-xl font-black text-gray-900 font-heading uppercase tracking-widest border-b border-gray-50 pb-4 pt-4">Další informace</h2>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Počet zaměstnanců</label>
                            <input
                                v-model="form.employee_count"
                                type="number"
                                min="0"
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                            >
                            <p v-if="form.errors.employee_count" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.employee_count }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Roční obrat (Kč)</label>
                            <input
                                v-model="form.annual_revenue"
                                type="number"
                                min="0"
                                step="1000"
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                            >
                            <p v-if="form.errors.annual_revenue" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.annual_revenue }}</p>
                        </div>

                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Stav <span class="text-red-500">*</span></label>
                            <select
                                v-model="form.status"
                                required
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all appearance-none cursor-pointer"
                            >
                                <option value="active">Aktivní</option>
                                <option value="inactive">Neaktivní</option>
                                <option value="prospect">Potenciální</option>
                            </select>
                            <p v-if="form.errors.status" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.status }}</p>
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Poznámky</label>
                            <textarea
                                v-model="form.notes"
                                rows="3"
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                                placeholder="Jakékoliv interní poznámky k této firmě..."
                            ></textarea>
                            <p v-if="form.errors.notes" class="mt-2 text-xs text-red-500 font-bold ml-1">{{ form.errors.notes }}</p>
                        </div>
                    </div>
                </div>

                <div class="px-10 py-8 bg-gray-50/50 flex flex-col sm:flex-row gap-4 border-t border-gray-50/50">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="flex-1 brand-gradient py-4 rounded-2xl font-black text-white shadow-brand hover:shadow-brand-lg transition-all hover:-translate-y-1 font-heading uppercase tracking-widest text-sm disabled:opacity-50 disabled:translate-y-0"
                    >
                        <span v-if="form.processing">Ukládání...</span>
                        <span v-else>🚀 Uložit změny</span>
                    </button>
                    <Link
                        href="/companies"
                        class="px-8 py-4 border-2 border-gray-100 rounded-2xl font-black text-gray-400 hover:text-gray-600 hover:border-gray-200 hover:bg-white transition-all font-heading uppercase tracking-widest text-sm flex items-center justify-center"
                    >
                        Zrušit
                    </Link>
                </div>
            </form>
        </div>
    </Layout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, useForm } from '@inertiajs/vue3'
import Layout from '../../Components/Layout.vue'
import Breadcrumbs from '../../Components/Breadcrumbs.vue'
import axios from 'axios'

const props = defineProps({
    company: Object,
})

const form = useForm({
    name: props.company.name || '',
    ico: props.company.ico || '',
    dic: props.company.dic || '',
    email: props.company.email || '',
    phone: props.company.phone || '',
    website: props.company.website || '',
    address: props.company.address || '',
    city: props.company.city || '',
    state: props.company.state || '',
    postal_code: props.company.postal_code || '',
    country: props.company.country || '',
    industry: props.company.industry || '',
    employee_count: props.company.employee_count || '',
    annual_revenue: props.company.annual_revenue || '',
    notes: props.company.notes || '',
    status: props.company.status || 'active',
})

const loadingAres = ref(false)
const aresError = ref('')

const loadAres = async () => {
    if (!form.ico) return
    
    loadingAres.value = true
    aresError.value = ''
    
    try {
        const response = await axios.get('/api/ares', { params: { ico: form.ico.replace(/\s+/g, '') } })
        const data = response.data
        
        form.name = data.name || form.name
        form.dic = data.dic || form.dic
        form.address = data.address || form.address
        form.city = data.city || form.city
        form.postal_code = data.postal_code || form.postal_code
        // Only set country to CZ if not set
        form.country = data.country || form.country || 'Česká republika'
        form.state = data.state || form.state
    } catch (error) {
        aresError.value = error.response?.data?.error || 'Nepodařilo se načíst data z ARES.'
    } finally {
        loadingAres.value = false
    }
}

const submit = () => {
    form.put(`/companies/${props.company.id}`)
}
</script>