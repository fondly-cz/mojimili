<script setup>
import Breadcrumbs from '@/Components/Breadcrumbs.vue';
import ConfirmModal from '@/Components/ConfirmModal.vue';
import Layout from '@/Components/Layout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

const props = defineProps({
    hasKeys: Boolean,
    generatedAt: String,
    mcpUrl: String,
});

const form = useForm({});
const confirmingRegenerate = ref(false);

const generatedAtLabel = computed(() => {
    if (!props.generatedAt) return null;
    return new Date(props.generatedAt).toLocaleString('cs-CZ', {
        dateStyle: 'long',
        timeStyle: 'short',
    });
});

const generate = () => {
    confirmingRegenerate.value = false;
    form.post(route('passport-keys.regenerate'), { preserveScroll: true });
};

const onGenerateClick = () => {
    // Když už klíče existují, přegenerování odpojí klienty – nech potvrdit.
    if (props.hasKeys) {
        confirmingRegenerate.value = true;
    } else {
        generate();
    }
};
</script>

<template>
    <Head title="MCP / API klíče" />

    <Layout>
        <Breadcrumbs
            :items="[
                { label: 'Nástěnka', href: '/' },
                { label: 'MCP / API klíče' },
            ]"
        />

        <div class="mb-10">
            <h1
                class="font-heading text-4xl font-extrabold tracking-tight text-gray-900"
            >
                MCP / API klíče
            </h1>
            <p class="mt-2 max-w-2xl font-medium text-gray-500">
                OAuth klíče (Laravel Passport), kterými se šifrují přístupové
                tokeny pro MCP server a API. Ukládají se do databáze, takže
                přežijí redeploy i výměnu serveru.
            </p>
        </div>

        <div class="max-w-4xl space-y-6">
            <!-- Stav klíčů -->
            <div
                class="shadow-brand-lg overflow-hidden rounded-[2.5rem] border border-gray-50 bg-white"
            >
                <div class="space-y-8 p-10">
                    <h2
                        class="font-heading border-b border-gray-50 pb-4 text-xl font-black tracking-widest text-gray-900 uppercase"
                    >
                        Stav
                    </h2>

                    <div v-if="hasKeys" class="flex items-start gap-4">
                        <div
                            class="mx-auto flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-green-50"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-6 w-6 text-green-500"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>
                        </div>
                        <div>
                            <p
                                class="font-heading text-lg font-black text-gray-900"
                            >
                                Klíče jsou nastavené
                            </p>
                            <p class="mt-1 text-sm font-medium text-gray-500">
                                MCP server je funkční. Vygenerováno:
                                <span class="font-bold text-gray-700">{{
                                    generatedAtLabel
                                }}</span>
                            </p>
                        </div>
                    </div>

                    <div v-else class="flex items-start gap-4">
                        <div
                            class="mx-auto flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl bg-amber-50"
                        >
                            <svg
                                xmlns="http://www.w3.org/2000/svg"
                                class="h-6 w-6 text-amber-500"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    stroke-width="2"
                                    d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"
                                />
                            </svg>
                        </div>
                        <div>
                            <p
                                class="font-heading text-lg font-black text-gray-900"
                            >
                                Klíče zatím nejsou vygenerované
                            </p>
                            <p class="mt-1 text-sm font-medium text-gray-500">
                                Dokud klíče nevygenerujete, MCP server ani API
                                nefungují (klient dostane chybu).
                            </p>
                        </div>
                    </div>

                    <div>
                        <button
                            type="button"
                            @click="onGenerateClick"
                            :disabled="form.processing"
                            class="font-heading inline-flex items-center gap-2 rounded-2xl px-8 py-4 text-xs font-black tracking-widest text-white uppercase shadow-lg transition-all hover:-translate-y-1 disabled:translate-y-0 disabled:opacity-50"
                            :class="
                                hasKeys
                                    ? 'bg-red-500 shadow-red-500/20 hover:bg-red-600'
                                    : 'brand-gradient shadow-brand'
                            "
                        >
                            <span v-if="form.processing">Generuji…</span>
                            <span v-else-if="hasKeys"
                                >Vygenerovat nové klíče</span
                            >
                            <span v-else>Vygenerovat klíče</span>
                        </button>
                        <p
                            v-if="hasKeys"
                            class="mt-3 ml-1 text-xs font-bold text-gray-400"
                        >
                            Pozor: přegenerování zneplatní vydané tokeny –
                            všichni připojení klienti se musí přihlásit znovu.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Připojení klienta -->
            <div
                class="shadow-brand-lg overflow-hidden rounded-[2.5rem] border border-gray-50 bg-white"
            >
                <div class="space-y-6 p-10">
                    <h2
                        class="font-heading border-b border-gray-50 pb-4 text-xl font-black tracking-widest text-gray-900 uppercase"
                    >
                        Připojení Claude
                    </h2>
                    <p class="text-sm font-medium text-gray-500">
                        V Claude → Settings → Connectors →
                        <span class="font-bold text-gray-700"
                            >Add custom connector</span
                        >
                        zadej tuto URL:
                    </p>
                    <div
                        class="rounded-2xl bg-gray-50 px-5 py-4 font-mono text-sm break-all text-gray-700 select-all"
                    >
                        {{ mcpUrl }}
                    </div>
                    <p class="text-xs font-medium text-gray-400">
                        Claude tě přesměruje na přihlášení do CRM a schvalovací
                        obrazovku. Po schválení má stejná práva jako tvůj účet.
                    </p>
                </div>
            </div>
        </div>

        <ConfirmModal
            :show="confirmingRegenerate"
            title="Vygenerovat nové klíče?"
            message="Stávající OAuth klíče se přepíšou. Všechny vydané tokeny okamžitě přestanou platit a připojení MCP klienti se musí přihlásit znovu."
            confirm-button="Vygenerovat nové"
            cancel-button="Zrušit"
            @close="confirmingRegenerate = false"
            @confirm="generate"
        />
    </Layout>
</template>
