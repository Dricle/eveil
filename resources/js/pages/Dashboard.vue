<script setup lang="ts">
import { Head, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'

const page = usePage()
</script>

<template>
    <AppLayout>
        <Head title="Dashboard" />

        <div class="space-y-4 p-4">
            <h2 class="font-medium">
                {{ page.props.currentProject?.name }}
            </h2>

            <p class="text-sm text-muted">
                Nothing here yet — leads, campaigns and suggestions for this
                project land on this page.
            </p>
        </div>
    </AppLayout>
</template>
