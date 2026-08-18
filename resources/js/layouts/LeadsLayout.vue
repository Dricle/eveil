<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3'
import type { NavigationMenuItem } from '@nuxt/ui'
import { computed, ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import companies from '@/routes/companies'
import contacts from '@/routes/contacts'

const page = usePage()

// The companies found, and the people at them. One is the other's parent, and
// you move between them constantly.
const items = computed<NavigationMenuItem[]>(() =>
    [
        { label: 'Companies', icon: 'i-lucide-building-2', to: companies.index.url() },
        { label: 'Contacts', icon: 'i-lucide-users', to: contacts.index.url() }
    ].map(item => ({ ...item, active: page.url.startsWith(item.to) }))
)

// A button on Leads, never a section of its own: importing is one of the ways
// leads arrive, not a place you go.
const importing = ref(false)
</script>

<template>
    <AppLayout>
        <div class="space-y-4 p-4">
            <!-- Section navigation lives in the section, not in the app bar:
                 that bar belongs to the app. -->
            <div class="flex flex-wrap items-center gap-3">
                <UNavigationMenu
                    :items="items"
                    class="flex-1"
                />

                <UButton
                    icon="i-lucide-upload"
                    color="neutral"
                    variant="subtle"
                    label="Import CSV"
                    @click="importing = true"
                />
            </div>

            <slot />
        </div>

        <UModal
            v-model:open="importing"
            title="Import contacts"
            description="CSV or xlsx, one row per person. An email address or a LinkedIn URL is enough — rows carrying neither are reported back with their line number."
        >
            <template #body>
                <Form
                    id="import-contacts"
                    v-slot="{ errors, processing }"
                    v-bind="contacts.import.form()"
                    class="space-y-4"
                    @success="importing = false"
                >
                    <UFormField
                        label="CSV file"
                        name="file"
                        :error="errors.file"
                    >
                        <UInput
                            name="file"
                            type="file"
                            accept=".csv,.txt,.xlsx,text/csv"
                            required
                            class="w-full"
                        />
                    </UFormField>

                    <p class="text-sm text-muted">
                        Columns: email, first_name, last_name, title,
                        linkedin_url, company_name, company_domain.
                        <ULink :href="contacts.template.url()">Download the template</ULink>.
                    </p>

                    <UButton
                        type="submit"
                        :loading="processing"
                        label="Import"
                    />
                </Form>
            </template>
        </UModal>
    </AppLayout>
</template>
