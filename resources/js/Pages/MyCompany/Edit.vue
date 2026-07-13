<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import Layout from '@/Components/Layout.vue'
import Breadcrumbs from '@/Components/Breadcrumbs.vue'
import axios from 'axios'
import { ref } from 'vue'

const props = defineProps({
    myCompany: Object,
})

const form = useForm({
    name: props.myCompany?.name || '',
    ico: props.myCompany?.ico || '',
    dic: props.myCompany?.dic || '',
    address: props.myCompany?.address || '',
    city: props.myCompany?.city || '',
    postal_code: props.myCompany?.postal_code || '',
    country: props.myCompany?.country || '',
    email: props.myCompany?.email || '',
    phone: props.myCompany?.phone || '',
    website: props.myCompany?.website || '',
    bank_account: props.myCompany?.bank_account || '',
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
        form.country = data.country || form.country || 'Česká republika'
    } catch (error) {
        aresError.value = error.response?.data?.error || 'Nepodařilo se načíst data z ARES.'
    } finally {
        loadingAres.value = false
    }
}

const submit = () => {
    form.patch(route('my-company.update'))
}
</script>

<template>
    <Head title="Moje firma" />

    <Layout>
        <Breadcrumbs :items="[{ label: 'Nástěnka', href: '/' }]" />
        <div class="mb-10">
            <h1 class="text-4xl font-extrabold text-gray-900 font-heading tracking-tight">Moje firma</h1>
            <p class="text-gray-500 mt-2 font-medium">Informace o vaší firmě pro použití v kalkulacích a fakturách.</p>
        </div>

        <form @submit.prevent="submit" class="space-y-6 max-w-4xl">
            <div class="bg-white rounded-[2.5rem] shadow-brand-lg border border-gray-50 overflow-hidden">
                <div class="p-10 space-y-8">
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
                        </div>

                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">DIČ</label>
                            <input
                                v-model="form.dic"
                                type="text"
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                                placeholder="CZ..."
                            >
                        </div>

                        <div class="md:col-span-2">
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Název firmy</label>
                            <input
                                v-model="form.name"
                                type="text"
                                class="block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                                placeholder="Např. Moje Firma s.r.o."
                            >
                        </div>
                    </div>
                </div>

                <div class="p-10 bg-gray-50/50 space-y-8 border-t border-gray-50">
                    <h2 class="text-xl font-black text-gray-900 font-heading uppercase tracking-widest border-b border-gray-100 pb-4">Adresa a Kontakt</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="md:col-span-2">
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Ulice a č.p.</label>
                            <input
                                v-model="form.address"
                                type="text"
                                class="block w-full px-5 py-3.5 bg-white border-gray-100 rounded-2xl text-sm font-semibold text-gray-700 focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                            >
                        </div>

                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Město</label>
                            <input
                                v-model="form.city"
                                type="text"
                                class="block w-full px-5 py-3.5 bg-white border-gray-100 rounded-2xl text-sm font-semibold text-gray-700 focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                            >
                        </div>

                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">PSČ</label>
                            <input
                                v-model="form.postal_code"
                                type="text"
                                class="block w-full px-5 py-3.5 bg-white border-gray-100 rounded-2xl text-sm font-semibold text-gray-700 focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                            >
                        </div>

                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Země</label>
                            <input
                                v-model="form.country"
                                type="text"
                                class="block w-full px-5 py-3.5 bg-white border-gray-100 rounded-2xl text-sm font-semibold text-gray-700 focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                            >
                        </div>

                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Bankovní účet</label>
                            <input
                                v-model="form.bank_account"
                                type="text"
                                class="block w-full px-5 py-3.5 bg-white border-gray-100 rounded-2xl text-sm font-semibold text-gray-700 focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                                placeholder="CZ00 0000 0000 0000 0000 0000"
                            >
                        </div>

                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">E-mail</label>
                            <input
                                v-model="form.email"
                                type="email"
                                class="block w-full px-5 py-3.5 bg-white border-gray-100 rounded-2xl text-sm font-semibold text-gray-700 focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                            >
                        </div>

                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Telefon</label>
                            <input
                                v-model="form.phone"
                                type="text"
                                class="block w-full px-5 py-3.5 bg-white border-gray-100 rounded-2xl text-sm font-semibold text-gray-700 focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                            >
                        </div>

                        <div>
                            <label class="block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2">Web</label>
                            <input
                                v-model="form.website"
                                type="text"
                                class="block w-full px-5 py-3.5 bg-white border-gray-100 rounded-2xl text-sm font-semibold text-gray-700 focus:ring-brand-primary-from focus:border-brand-primary-from transition-all"
                                placeholder="www.mojefirma.cz"
                            >
                        </div>
                    </div>
                </div>

                <div class="p-10 bg-white border-t border-gray-50 flex justify-end gap-4">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-10 py-4 brand-gradient text-white rounded-3xl font-black uppercase tracking-widest text-sm shadow-brand hover:shadow-brand-lg transition-all hover:-translate-y-0.5 disabled:opacity-50"
                    >
                        Uložit nastavení
                    </button>
                </div>
            </div>
        </form>
    </Layout>
</template>
