<script setup>
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { ref, watch } from 'vue';

const props = defineProps({ assets: Object, filters: Object });
const search = ref(props.filters.search || '');
const type = ref(props.filters.type || '');
let timer;
watch([search, type], () => {
    clearTimeout(timer);
    timer = setTimeout(() => router.get(route('assets.index'), { search: search.value, type: type.value }, { preserveState: true, replace: true }), 250);
});
</script>

<template>
    <Head title="Asset inventory" />
    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between gap-4">
                <div><h1 class="text-xl font-semibold text-slate-900">Asset inventory</h1><p class="mt-1 text-sm text-slate-500">Every device your utility depends on, in one place.</p></div>
                <div class="flex gap-2"><Link :href="route('assets.import.create')" class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium">Import CSV</Link><Link :href="route('assets.create')" class="rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white">Add asset</Link></div>
            </div>
        </template>
        <div class="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-5 grid gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-[1fr_220px]">
                <input v-model="search" aria-label="Search assets" placeholder="Search by name, vendor, or model" class="rounded-lg border-slate-300 text-sm" />
                <select v-model="type" aria-label="Filter by asset type" class="rounded-lg border-slate-300 text-sm"><option value="">All asset types</option><option v-for="item in ['plc','rtu','hmi','historian','switch','other']" :key="item" :value="item">{{ item.toUpperCase() }}</option></select>
            </div>
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead class="bg-slate-50 text-left text-xs uppercase tracking-wide text-slate-500"><tr><th class="px-5 py-3">Asset</th><th class="px-5 py-3">Site / area</th><th class="px-5 py-3">Type</th><th class="px-5 py-3">Monitoring</th><th class="px-5 py-3">Criticality</th></tr></thead>
                    <tbody class="divide-y divide-slate-100 text-sm">
                        <tr v-for="asset in assets.data" :key="asset.id" class="hover:bg-slate-50"><td class="px-5 py-4"><Link :href="route('assets.show', asset.id)" class="font-semibold text-teal-800">{{ asset.name }}</Link><div class="text-slate-500">{{ [asset.vendor, asset.model].filter(Boolean).join(' · ') || 'Details not recorded' }}</div></td><td class="px-5 py-4 text-slate-700">{{ asset.site.name }}<div class="text-slate-500">{{ asset.process_area?.name || 'No process area' }}</div></td><td class="px-5 py-4 uppercase text-slate-700">{{ asset.asset_type }}</td><td class="px-5 py-4 capitalize text-slate-700">{{ asset.data_source_type.replace('_', ' ') }}</td><td class="px-5 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold capitalize">{{ asset.criticality }}</span></td></tr>
                        <tr v-if="!assets.data.length"><td colspan="5" class="px-5 py-12 text-center text-slate-500">No assets match these filters.</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
