<script setup>
import Layout from '@/Components/Layout.vue';
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import { Head } from '@inertiajs/vue3';

defineProps({
    mustVerifyEmail: {
        type: Boolean,
    },
    status: {
        type: String,
    },
});
</script>

<template>
    <Head title="Profil" />

    <Layout>
        <Breadcrumbs :items="[{ label: 'Nástěnka', href: '/' }]" />
        <div class="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-6">
            <div>
                <h1 class="text-4xl font-extrabold text-gray-900 font-heading tracking-tight">Nastavení profilu</h1>
                <p class="text-gray-500 mt-2 font-medium">Správa vašich osobních údajů, hesla a nastavení.</p>
            </div>
        </div>

        <div class="space-y-6">
            <!-- Profile Information -->
            <div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-50 overflow-hidden relative">
                <div class="absolute -right-20 -top-20 h-64 w-64 brand-gradient opacity-[0.03] rounded-full blur-3xl pointer-events-none"></div>
                <div class="p-10 relative z-10 w-full max-w-2xl">
                    <UpdateProfileInformationForm
                        :must-verify-email="mustVerifyEmail"
                        :status="status"
                    />
                </div>
            </div>

            <!-- Update Password -->
            <div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-50 overflow-hidden relative">
                <div class="absolute -right-20 -top-20 h-64 w-64 brand-gradient opacity-[0.03] rounded-full blur-3xl pointer-events-none"></div>
                <div class="p-10 relative z-10 w-full max-w-2xl">
                    <UpdatePasswordForm />
                </div>
            </div>

            <!-- Delete Account -->
            <div class="bg-white rounded-[2.5rem] shadow-sm border border-gray-50 overflow-hidden relative">
                <div class="absolute -right-20 -top-20 h-64 w-64 bg-red-500 opacity-[0.03] rounded-full blur-3xl pointer-events-none"></div>
                <div class="p-10 relative z-10 w-full max-w-2xl">
                    <DeleteUserForm />
                </div>
            </div>
        </div>
    </Layout>
</template>
