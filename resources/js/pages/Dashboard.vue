<script setup lang="ts">
import { Form, Head, Link, usePage } from '@inertiajs/vue3';
import { logout, security } from '@/routes';

const page = usePage();
</script>

<template>
    <div class="mx-auto max-w-4xl space-y-6 p-6">
        <Head title="Dashboard" />

        <header class="flex items-center justify-between">
            <h1 class="text-lg font-semibold">
                {{ page.props.auth.user.name }}
            </h1>

            <div class="flex items-center gap-4">
                <Link
                    :href="security.url()"
                    class="text-sm text-neutral-500 underline"
                    >Security</Link
                >

                <Form v-bind="logout.form()">
                    <UButton type="submit" variant="ghost" label="Log out" />
                </Form>
            </div>
        </header>
    </div>
</template>
