<script setup>
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import Layout from '@/Components/Layout.vue';
import { Head, useForm, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, ref } from 'vue';

const props = defineProps({
    smtp: Object,
    effective: Object,
});

const page = usePage();

const form = useForm({
    enabled: props.smtp?.enabled ?? true,
    host: props.smtp?.host ?? '',
    port: props.smtp?.port ?? 587,
    encryption: props.smtp?.encryption ?? 'tls',
    username: props.smtp?.username ?? '',
    password: '',
    from_address: props.smtp?.from_address ?? '',
    from_name: props.smtp?.from_name ?? '',
});

const confirmingDelete = ref(false);
const deleteForm = useForm({});

const submit = () => {
    form.put(route('smtp-settings.update'), {
        preserveScroll: true,
        onSuccess: () => form.reset('password'),
    });
};

const remove = () => {
    confirmingDelete.value = false;
    deleteForm.delete(route('smtp-settings.destroy'), { preserveScroll: true });
};

const sourceLabel = computed(() =>
    props.effective.source === 'admin'
        ? 'Administrace (přebíjí .env)'
        : 'Soubor .env',
);

const testEmail = ref(page.props.auth?.user?.email ?? '');
const testResult = ref(null);
const testLoading = ref(false);

const sendTest = async () => {
    if (!testEmail.value) return;

    testLoading.value = true;
    testResult.value = null;

    try {
        const { data } = await axios.post(route('smtp-settings.test'), {
            email: testEmail.value,
        });
        testResult.value = data;
    } catch (error) {
        testResult.value = {
            success: false,
            message:
                error.response?.data?.message ||
                'Odeslání testovacího e-mailu selhalo.',
        };
    } finally {
        testLoading.value = false;
    }
};

const inputClass =
    'block w-full px-5 py-3.5 bg-gray-50 border-gray-50 rounded-2xl text-sm font-semibold text-gray-700 focus:bg-white focus:ring-brand-primary-from focus:border-brand-primary-from transition-all';
const labelClass =
    'block text-xs font-black text-gray-400 uppercase tracking-widest ml-1 mb-2';
const cardClass =
    'bg-white rounded-[2.5rem] shadow-brand-lg border border-gray-50 overflow-hidden';
const headingClass =
    'text-xl font-black text-gray-900 font-heading uppercase tracking-widest border-b border-gray-50 pb-4';
</script>

<template>
    <Head title="E-maily a SMTP" />

    <Layout>
        <Breadcrumbs
            :items="[
                { label: 'Nástěnka', href: '/' },
                { label: 'E-maily a SMTP' },
            ]"
        />

        <div class="mb-10">
            <h1
                class="font-heading text-4xl font-extrabold tracking-tight text-gray-900"
            >
                E-maily a SMTP
            </h1>
            <p class="mt-2 max-w-2xl font-medium text-gray-500">
                Vlastní SMTP server pro odesílání e-mailů z aplikace. Pokud je
                zapnutý, má přednost před nastavením
                <span class="font-mono">MAIL_*</span> v souboru
                <span class="font-mono">.env</span>.
            </p>
        </div>

        <div class="max-w-4xl space-y-6">
            <!-- Aktuálně použité nastavení -->
            <div :class="cardClass">
                <div class="space-y-6 p-10">
                    <h2 :class="headingClass">Právě používáno</h2>
                    <dl class="space-y-2 text-sm">
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Zdroj</dt>
                            <dd class="font-bold text-gray-900">
                                {{ sourceLabel }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Ovladač</dt>
                            <dd class="font-mono">{{ effective.mailer }}</dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Server</dt>
                            <dd class="font-mono break-all">
                                {{ effective.host ?? '—' }}
                                <span v-if="effective.port"
                                    >:{{ effective.port }}</span
                                >
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Uživatel</dt>
                            <dd class="font-mono break-all">
                                {{ effective.username ?? '—' }}
                            </dd>
                        </div>
                        <div class="flex justify-between gap-4">
                            <dt class="text-gray-500">Odesílatel</dt>
                            <dd class="font-mono break-all">
                                {{ effective.fromName }}
                                &lt;{{ effective.fromAddress }}&gt;
                            </dd>
                        </div>
                    </dl>
                </div>
            </div>

            <!-- Formulář SMTP -->
            <form @submit.prevent="submit" :class="cardClass">
                <div class="space-y-8 p-10">
                    <h2 :class="headingClass">SMTP server</h2>

                    <label class="flex items-center gap-3">
                        <input
                            v-model="form.enabled"
                            type="checkbox"
                            class="h-5 w-5 rounded border-gray-300 text-brand-primary-from focus:ring-brand-primary-from"
                        />
                        <span class="text-sm font-bold text-gray-700"
                            >Používat tento SMTP server místo .env</span
                        >
                    </label>

                    <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
                        <div>
                            <label :class="labelClass">Server (host)</label>
                            <input
                                v-model="form.host"
                                type="text"
                                :class="inputClass"
                                placeholder="smtp.example.com"
                            />
                            <p
                                v-if="form.errors.host"
                                class="mt-1 ml-1 text-xs font-bold text-red-500"
                            >
                                {{ form.errors.host }}
                            </p>
                        </div>
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label :class="labelClass">Port</label>
                                <input
                                    v-model="form.port"
                                    type="number"
                                    :class="inputClass"
                                />
                                <p
                                    v-if="form.errors.port"
                                    class="mt-1 ml-1 text-xs font-bold text-red-500"
                                >
                                    {{ form.errors.port }}
                                </p>
                            </div>
                            <div>
                                <label :class="labelClass">Šifrování</label>
                                <select
                                    v-model="form.encryption"
                                    :class="inputClass"
                                >
                                    <option value="tls">STARTTLS (587)</option>
                                    <option value="ssl">SSL/TLS (465)</option>
                                    <option :value="null">Žádné</option>
                                </select>
                            </div>
                        </div>
                        <div>
                            <label :class="labelClass">Uživatel</label>
                            <input
                                v-model="form.username"
                                type="text"
                                autocomplete="off"
                                :class="inputClass"
                            />
                            <p
                                v-if="form.errors.username"
                                class="mt-1 ml-1 text-xs font-bold text-red-500"
                            >
                                {{ form.errors.username }}
                            </p>
                        </div>
                        <div>
                            <label :class="labelClass">Heslo</label>
                            <input
                                v-model="form.password"
                                type="password"
                                autocomplete="new-password"
                                :class="inputClass"
                                :placeholder="
                                    smtp?.passwordConfigured
                                        ? '•••••••• (beze změny)'
                                        : ''
                                "
                            />
                            <p
                                v-if="form.errors.password"
                                class="mt-1 ml-1 text-xs font-bold text-red-500"
                            >
                                {{ form.errors.password }}
                            </p>
                        </div>
                        <div>
                            <label :class="labelClass">E-mail odesílatele</label>
                            <input
                                v-model="form.from_address"
                                type="email"
                                :class="inputClass"
                                placeholder="info@firma.cz"
                            />
                            <p
                                v-if="form.errors.from_address"
                                class="mt-1 ml-1 text-xs font-bold text-red-500"
                            >
                                {{ form.errors.from_address }}
                            </p>
                        </div>
                        <div>
                            <label :class="labelClass">Jméno odesílatele</label>
                            <input
                                v-model="form.from_name"
                                type="text"
                                :class="inputClass"
                            />
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center gap-4">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="brand-gradient shadow-brand font-heading inline-flex items-center rounded-2xl px-8 py-4 text-xs font-black tracking-widest text-white uppercase shadow-lg transition-all hover:-translate-y-1 disabled:translate-y-0 disabled:opacity-50"
                        >
                            {{ form.processing ? 'Ukládám…' : 'Uložit' }}
                        </button>
                        <button
                            v-if="smtp"
                            type="button"
                            @click="confirmingDelete = true"
                            class="text-xs font-black tracking-widest text-red-500 uppercase hover:text-red-600"
                        >
                            Smazat a vrátit se k .env
                        </button>
                    </div>
                </div>
            </form>

            <!-- Test odeslání -->
            <form @submit.prevent="sendTest" :class="cardClass">
                <div class="space-y-6 p-10">
                    <h2 :class="headingClass">Test odeslání</h2>
                    <p class="text-sm font-medium text-gray-500">
                        Otestuje se nastavení, které je uložené (viz „Právě
                        používáno“). Odešlou se dva e-maily: jeden okamžitě a
                        druhý přes frontu. Pokud dorazí jen ten první, neběží
                        queue worker a ostatní e-maily z aplikace se neodešlou.
                    </p>
                    <div class="flex flex-wrap gap-3">
                        <input
                            v-model="testEmail"
                            type="email"
                            placeholder="jmeno@firma.cz"
                            :class="[inputClass, 'max-w-xs']"
                        />
                        <button
                            type="submit"
                            :disabled="testLoading || !testEmail"
                            class="font-heading rounded-2xl bg-gray-900 px-8 py-4 text-xs font-black tracking-widest text-white uppercase transition-all hover:-translate-y-1 disabled:translate-y-0 disabled:opacity-50"
                        >
                            {{
                                testLoading
                                    ? 'Odesílám…'
                                    : 'Odeslat testovací e-mail'
                            }}
                        </button>
                    </div>
                    <p
                        v-if="testResult"
                        class="text-sm font-bold"
                        :class="
                            testResult.success
                                ? 'text-green-600'
                                : 'text-red-500'
                        "
                    >
                        {{ testResult.message }}
                    </p>
                </div>
            </form>
        </div>

        <ConfirmModal
            :show="confirmingDelete"
            title="Smazat vlastní SMTP?"
            message="Uložené SMTP nastavení se smaže a aplikace začne znovu používat MAIL_* z .env."
            confirm-button="Smazat"
            cancel-button="Zrušit"
            @close="confirmingDelete = false"
            @confirm="remove"
        />
    </Layout>
</template>
