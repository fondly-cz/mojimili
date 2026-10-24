<script setup>
import PrimaryButton from '@/Components/PrimaryButton.vue';
import { useForm, usePage } from '@inertiajs/vue3';

const user = usePage().props.auth.user;

const form = useForm({
    notify_in_app: user.notify_in_app ?? true,
    notify_by_mail: user.notify_by_mail ?? true,
});
</script>

<template>
    <section>
        <header>
            <h2 class="text-2xl font-black text-gray-900 font-heading">
                Notifikace
            </h2>

            <p class="mt-1 text-sm text-gray-500 font-medium">
                Kudy chcete dostávat upozornění o úkolech (přiřazení, komentáře,
                dokončení). Celý projekt můžete sledovat tlačítkem na jeho
                stránce.
            </p>
        </header>

        <form
            @submit.prevent="form.patch(route('profile.notifications.update'), { preserveScroll: true })"
            class="mt-6 space-y-4"
        >
            <label class="flex items-center gap-3">
                <input
                    v-model="form.notify_in_app"
                    type="checkbox"
                    class="h-5 w-5 rounded border-gray-300 text-brand-primary-from focus:ring-brand-primary-from"
                />
                <span class="text-sm font-bold text-gray-700">V aplikaci (zvonek)</span>
            </label>
            <label class="flex items-center gap-3">
                <input
                    v-model="form.notify_by_mail"
                    type="checkbox"
                    class="h-5 w-5 rounded border-gray-300 text-brand-primary-from focus:ring-brand-primary-from"
                />
                <span class="text-sm font-bold text-gray-700">E-mailem</span>
            </label>

            <PrimaryButton :disabled="form.processing">Uložit</PrimaryButton>
        </form>
    </section>
</template>
