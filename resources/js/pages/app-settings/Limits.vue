<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3'
import AppSettingsLayout from '@/layouts/AppSettingsLayout.vue'
import limitRoutes from '@/routes/app-settings/limits'

defineProps<{
    limits: Record<string, number | boolean>
}>()

// Everything tunable from a screen, and the whole of it. What is not here is
// deployment: service URLs, HTTP timeouts, the user agent. That stays in the
// environment file.
const GROUPS: { title: string, hint: string, fields: { name: string, label: string }[] }[] = [
    {
        title: 'One discovery run',
        hint: 'A run stops on whichever limit it reaches first and keeps what it already found.',
        fields: [
            { name: 'discovery_max_queries', label: 'Search queries' },
            { name: 'discovery_max_companies', label: 'Companies found' },
            { name: 'discovery_max_qualified', label: 'Companies scored' },
            { name: 'discovery_max_pages', label: 'Pages fetched' }
        ]
    },
    {
        title: 'Reading a site',
        hint: 'The delay is per host: it is what keeps a crawl from hammering somebody else\'s server.',
        fields: [
            { name: 'crawl_max_pages', label: 'Pages per analysis' },
            { name: 'crawl_delay_ms', label: 'Politeness delay (ms)' },
            { name: 'crawl_cache_ttl_days', label: 'Page cache (days)' },
            { name: 'contacts_max_pages', label: 'Pages per contact search' }
        ]
    },
    {
        title: 'Email verification',
        hint: 'Port 25 is blocked on most hosting, so the probe usually times out into "unknown" rather than proving anything.',
        fields: [{ name: 'verification_timeout', label: 'SMTP probe timeout (s)' }]
    },
    {
        title: 'Sources',
        hint: 'A directory page is worth dozens of companies, so it has its own budget, so a bad "next" link costs a few fetches instead of the run.',
        fields: [
            { name: 'searxng_per_query', label: 'Search results per query' },
            { name: 'overpass_per_probe', label: 'Map results per probe' },
            { name: 'directory_max_pages', label: 'Pages per directory' },
            { name: 'directory_max_entities', label: 'Companies per directory' },
            { name: 'host_registry_ttl_days', label: 'Host verdict expiry (days)' },
            { name: 'host_registry_batch', label: 'Hosts judged per batch' }
        ]
    }
]
</script>

<template>
    <AppSettingsLayout title="Limits">
        <Head title="Limits" />

        <Form
            v-slot="{ errors, processing, recentlySuccessful }"
            v-bind="limitRoutes.update.form()"
            class="max-w-3xl space-y-4"
        >
            <UCard
                v-for="group in GROUPS"
                :key="group.title"
            >
                <template #header>
                    <h2 class="font-medium">
                        {{ group.title }}
                    </h2>
                    <p class="mt-1 text-sm text-muted">
                        {{ group.hint }}
                    </p>
                </template>

                <div class="grid gap-4 sm:grid-cols-2">
                    <UFormField
                        v-for="field in group.fields"
                        :key="field.name"
                        :label="field.label"
                        :name="field.name"
                        :error="errors[field.name]"
                    >
                        <UInput
                            :name="field.name"
                            type="number"
                            :default-value="Number(limits[field.name])"
                            required
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        v-if="group.title === 'Email verification'"
                        label="SMTP probe"
                        name="verification_probe"
                        :error="errors.verification_probe"
                    >
                        <UCheckbox
                            name="verification_probe"
                            value="1"
                            :default-value="Boolean(limits.verification_probe)"
                            label="Probe the mail server before trusting an address"
                        />
                    </UFormField>
                </div>
            </UCard>

            <div class="flex items-center gap-3">
                <UButton
                    type="submit"
                    :loading="processing"
                    label="Save"
                />
                <span
                    v-if="recentlySuccessful"
                    class="text-sm text-muted"
                >Saved.</span>
            </div>
        </Form>
    </AppSettingsLayout>
</template>
