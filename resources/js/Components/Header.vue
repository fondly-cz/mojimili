<script setup>
import { Link, usePage, router } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { debounce } from 'lodash';

const page = usePage();
const user = computed(() => page.props.auth.user);

const searchQuery = ref('');
const searchResults = ref({
    calculations: [],
    companies: [],
    services: []
});
const isSearching = ref(false);
const showResults = ref(false);

const performSearch = debounce(async (query) => {
    if (!query || query.length < 2) {
        searchResults.value = { calculations: [], companies: [], services: [] };
        showResults.value = false;
        return;
    }

    isSearching.value = true;
    try {
        const response = await fetch(`/api/search?q=${encodeURIComponent(query)}`);
        const data = await response.json();
        searchResults.value = data;
        showResults.value = true;
    } catch (e) {
        console.error('Search failed', e);
    } finally {
        isSearching.value = false;
    }
}, 300);

watch(searchQuery, (newVal) => {
    performSearch(newVal);
});

const closeSearch = () => {
    setTimeout(() => {
        showResults.value = false;
    }, 200);
};

const notifications = ref({ unread: 0, items: [] });
const showNotifications = ref(false);
let poller = null;

const loadNotifications = async () => {
    try {
        const response = await fetch('/notifications', { headers: { Accept: 'application/json' } });
        if (response.ok) notifications.value = await response.json();
    } catch (e) {
        console.error('Notifications failed', e);
    }
};

const xsrf = () => decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? '');

const markRead = async (url) => {
    await fetch(url, { method: 'POST', headers: { 'X-XSRF-TOKEN': xsrf(), Accept: 'application/json' } });
    await loadNotifications();
};

const openNotification = async (item) => {
    showNotifications.value = false;
    if (!item.read) await markRead(`/notifications/${item.id}/read`);
    router.visit(item.url);
};

onMounted(() => {
    loadNotifications();
    poller = setInterval(loadNotifications, 60000);
});
onBeforeUnmount(() => clearInterval(poller));

const formatCurrency = (value) => {
    return new Intl.NumberFormat('cs-CZ', { style: 'currency', currency: 'CZK' }).format(value);
};
</script>

<template>
    <header class="h-24 flex items-center justify-between px-10 bg-white/80 backdrop-blur-md border-b border-gray-100 sticky top-0 z-50">
        <!-- Search bar -->
        <div class="hidden lg:flex relative w-96 z-50">
            <div class="flex items-center gap-4 bg-gray-50 px-6 py-3 rounded-2xl w-full border border-gray-50 focus-within:border-brand-primary-from focus-within:bg-white transition-all shadow-sm">
                <svg v-if="!isSearching" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
                <div v-else class="h-5 w-5 border-2 border-brand-primary-from border-t-transparent rounded-full animate-spin"></div>
                <input 
                    v-model="searchQuery"
                    @focus="searchQuery.length >= 2 ? showResults = true : null"
                    @blur="closeSearch"
                    type="text" 
                    placeholder="Hledat klienty, kalkulace, služby..." 
                    class="bg-transparent border-none focus:ring-0 text-sm font-semibold text-gray-600 placeholder:text-gray-300 p-0 w-full"
                />
            </div>
            
            <!-- Dropdown results -->
            <div 
                v-if="showResults && (searchResults.calculations.length > 0 || searchResults.companies.length > 0 || searchResults.services.length > 0)" 
                class="absolute top-full left-0 right-0 mt-2 bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden max-h-[80vh] overflow-y-auto"
            >
                <!-- Companies -->
                <div v-if="searchResults.companies.length > 0" class="p-4 border-b border-gray-50">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3 px-2">Firmy</div>
                    <div class="space-y-1">
                        <Link 
                            v-for="company in searchResults.companies" 
                            :key="company.id" 
                            :href="`/companies/${company.id}`"
                            class="flex items-center gap-3 p-2 hover:bg-gray-50 rounded-xl transition-colors group"
                        >
                            <div class="h-8 w-8 bg-brand-primary-from/10 text-brand-primary-from rounded-lg flex items-center justify-center font-bold text-sm">
                                {{ company.name.charAt(0) }}
                            </div>
                            <div class="overflow-hidden">
                                <div class="text-sm font-bold text-gray-900 truncate">{{ company.name }}</div>
                                <div class="text-xs text-gray-400 truncate">{{ company.email }}</div>
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- Calculations -->
                <div v-if="searchResults.calculations.length > 0" class="p-4 border-b border-gray-50">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3 px-2">Kalkulace</div>
                    <div class="space-y-1">
                        <Link 
                            v-for="calc in searchResults.calculations" 
                            :key="calc.id" 
                            :href="`/calculations/${calc.id}`"
                            class="flex items-center justify-between p-2 hover:bg-gray-50 rounded-xl transition-colors group"
                        >
                            <div class="overflow-hidden">
                                <div class="text-sm font-bold text-gray-900 truncate">{{ calc.customer_name }}</div>
                                <div class="text-xs text-gray-400 truncate">{{ calc.customer_company }}</div>
                            </div>
                            <div class="text-xs font-black brand-text-gradient ml-2">
                                {{ formatCurrency(calc.total_price) }}
                            </div>
                        </Link>
                    </div>
                </div>

                <!-- Services -->
                <div v-if="searchResults.services.length > 0" class="p-4">
                    <div class="text-[10px] font-black uppercase tracking-widest text-gray-400 mb-3 px-2">Služby</div>
                    <div class="space-y-1">
                        <Link 
                            v-for="service in searchResults.services" 
                            :key="service.id" 
                            href="/services"
                            class="flex flex-col p-2 hover:bg-gray-50 rounded-xl transition-colors group"
                        >
                            <div class="flex items-center justify-between w-full">
                                <div class="text-sm font-bold text-gray-900 truncate">{{ service.name }}</div>
                                <div class="text-xs font-bold text-gray-500">{{ formatCurrency(service.price) }}</div>
                            </div>
                        </Link>
                    </div>
                </div>
            </div>
            
            <div 
                v-else-if="showResults && searchQuery.length >= 2" 
                class="absolute top-full left-0 right-0 mt-2 bg-white rounded-2xl shadow-xl border border-gray-100 p-8 text-center"
            >
                <div class="text-gray-400 text-sm font-bold">Nebyly nalezeny žádné výsledky</div>
            </div>
        </div>

        <!-- Right section -->
        <div class="flex items-center gap-6">
            <!-- Notifications -->
            <div class="relative">
                <button
                    type="button"
                    @click="showNotifications = !showNotifications; if (showNotifications) loadNotifications()"
                    class="relative p-3 bg-gray-50 text-gray-400 rounded-2xl hover:bg-brand-primary-from hover:text-white transition-all shadow-sm group"
                    aria-label="Oznámení"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>
                    <span
                        v-if="notifications.unread"
                        class="absolute -top-1 -right-1 min-w-[1.25rem] h-5 px-1 flex items-center justify-center bg-brand-primary-to text-white text-[10px] font-black rounded-full border-2 border-white"
                    >{{ notifications.unread > 9 ? '9+' : notifications.unread }}</span>
                </button>

                <div v-if="showNotifications" class="fixed inset-0 z-40" @click="showNotifications = false"></div>
                <div
                    v-if="showNotifications"
                    class="absolute right-0 top-full mt-3 w-96 max-w-[90vw] bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden z-50"
                >
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-50">
                        <span class="text-[10px] font-black uppercase tracking-widest text-gray-400">Oznámení</span>
                        <button
                            v-if="notifications.unread"
                            type="button"
                            @click="markRead('/notifications/read-all')"
                            class="text-[10px] font-black uppercase tracking-widest text-brand-primary-from hover:opacity-80"
                        >Označit vše jako přečtené</button>
                    </div>
                    <div class="max-h-96 overflow-y-auto">
                        <button
                            v-for="item in notifications.items"
                            :key="item.id"
                            type="button"
                            @click="openNotification(item)"
                            class="block w-full text-left px-5 py-3 hover:bg-gray-50 transition-colors border-b border-gray-50 last:border-0"
                            :class="{ 'bg-brand-primary-from/5': !item.read }"
                        >
                            <div class="text-xs font-bold text-gray-700">{{ item.message }}</div>
                            <div class="text-sm font-black text-gray-900 truncate">{{ item.todo_name }}</div>
                            <div class="text-[10px] font-bold text-gray-400 mt-1">{{ new Date(item.created_at).toLocaleString('cs-CZ') }}</div>
                        </button>
                        <div v-if="!notifications.items.length" class="px-5 py-8 text-center text-sm font-bold text-gray-400">Žádná oznámení</div>
                    </div>
                </div>
            </div>

            <!-- User profile -->
            <div class="flex items-center gap-4 pl-6 border-l border-gray-100">
                <Link :href="route('profile.edit')" class="flex items-center gap-4 hover:opacity-80 transition-opacity">
                    <div class="flex flex-col items-end">
                        <span class="text-sm font-black text-gray-900 leading-none font-heading">{{ user?.name }}</span>
                        <span class="text-[10px] font-bold uppercase tracking-widest text-gray-400 mt-1.5 leading-none">
                            {{ user?.role === 'admin' ? 'Administrátor' : 'Manažer' }}
                        </span>
                    </div>
                    
                    <div class="relative group">
                        <div class="h-12 w-12 brand-gradient rounded-2xl flex items-center justify-center text-white font-black shadow-brand group-hover:scale-105 transition-transform cursor-pointer border-2 border-white overflow-hidden">
                            <img v-if="user?.avatar" :src="user.avatar" class="h-full w-full object-cover" />
                            <span v-else>{{ user?.name?.charAt(0) }}</span>
                        </div>
                    </div>
                </Link>

                <Link 
                    :href="route('logout')" 
                    method="post" 
                    as="button"
                    class="p-3 bg-red-50 text-red-400 rounded-2xl hover:bg-red-500 hover:text-white transition-all shadow-sm"
                    title="Odhlásit se"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                </Link>
            </div>
        </div>
    </header>
</template>
