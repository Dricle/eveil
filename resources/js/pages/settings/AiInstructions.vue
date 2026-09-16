<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import emailInstructionsRoutes from '@/routes/settings/ai-instructions/emails'
import linkedinInstructionsRoutes from '@/routes/settings/ai-instructions/linkedin'

defineOptions({ layout: [AppLayout, [SettingsLayout, { title: 'AI instructions' }]] })

const props = defineProps<{
    promptInstructions: string | null
    linkedinPromptInstructions: string | null
}>()

// Local drafts synced from the props, not `default-value`: Nuxt UI's textarea
// reads that once and every re-render (this page's own redirect after
// saving) would silently stomp whatever the user just typed.
const emailInstructions = ref(props.promptInstructions ?? '')
watch(() => props.promptInstructions, value => emailInstructions.value = value ?? '', { immediate: true })

const linkedinInstructions = ref(props.linkedinPromptInstructions ?? '')
watch(() => props.linkedinPromptInstructions, value => linkedinInstructions.value = value ?? '', { immediate: true })
</script>

<template>
    <Head title="AI instructions" />

    <div class="space-y-4">
        <UCard>
            <template #header>
                <h2 class="font-medium">
                    How Emails are written
                </h2>
                <p class="mt-1 text-sm text-muted">
                    Followed by every sequence and every mail personalised from one.
                    E.g. write in French, never use emoji, say vous rather than tu.
                </p>
            </template>

            <Form
                v-slot="{ errors, processing, recentlySuccessful }"
                v-bind="emailInstructionsRoutes.update.form()"
                class="space-y-4"
            >
                <UFormField
                    name="prompt_instructions"
                    :error="errors.prompt_instructions"
                >
                    <UTextarea
                        v-model="emailInstructions"
                        name="prompt_instructions"
                        :rows="5"
                        :maxlength="2000"
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

        <UCard>
            <template #header>
                <h2 class="font-medium">
                    How LinkedIn posts are written
                </h2>
                <p class="mt-1 text-sm text-muted">
                    Independent of the emails box above: a public feed post is a
                    different kind of writing, with its own audience, so it does not
                    default to the email tone. E.g. more casual, first person, short
                    punchy lines.
                </p>
            </template>

            <Form
                v-slot="{ errors, processing, recentlySuccessful }"
                v-bind="linkedinInstructionsRoutes.update.form()"
                class="space-y-4"
            >
                <UFormField
                    name="linkedin_prompt_instructions"
                    :error="errors.linkedin_prompt_instructions"
                >
                    <UTextarea
                        v-model="linkedinInstructions"
                        name="linkedin_prompt_instructions"
                        :rows="5"
                        :maxlength="2000"
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
</template>
