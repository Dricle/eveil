<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import organizationRoutes from '@/routes/settings/organization/general'
import type { Organization } from '@/types'

const props = defineProps<{ organization: Organization }>()

// Bound, never left to `default-value`: Nuxt UI reads that prop once at
// mount, and Vue then patches a form element's value against what the DOM
// holds, so every later render writes the frozen first value back over what
// was typed. Saving re-renders this page.
const name = ref(props.organization.name)

watch(() => props.organization, (organization) => {
    name.value = organization.name
})
</script>

<template>
    <SettingsLayout title="Organization">
        <Head title="Organization" />

        <div class="max-w-2xl space-y-4">
            <UCard>
                <template #header>
                    <h2 class="font-medium">
                        General
                    </h2>
                </template>

                <Form
                    v-slot="{ errors, processing, recentlySuccessful }"
                    v-bind="organizationRoutes.update.form()"
                    class="space-y-4"
                >
                    <UFormField
                        label="Name"
                        name="name"
                        :error="errors.name"
                    >
                        <UInput
                            v-model="name"
                            name="name"
                            required
                            class="w-full"
                        />
                    </UFormField>

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
            </UCard>
        </div>
    </SettingsLayout>
</template>
