<script setup lang="ts">
import { Head } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
</script>

<template>
    <AppLayout>
        <template #header>
            <h1 class="font-medium">
                Dashboard
            </h1>
        </template>

        <Head title="Dashboard" />

        <div class="p-4">
            <p class="text-sm text-muted">
                Nothing here yet — projects land next.
            </p>
        </div>
    </AppLayout>
</template>
