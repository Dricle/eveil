<script setup lang="ts">
import { Form, Head, usePage } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import { PLATFORM_LABEL } from '@/lib/social'
import emailInstructionsRoutes from '@/routes/settings/ai-instructions/emails'
import postInstructionsRoutes from '@/routes/settings/ai-instructions/posts'
import type { SocialPlatform } from '@/types'

defineOptions({ layout: [AppLayout, [SettingsLayout, { title: 'AI instructions' }]] })

const props = defineProps<{
    promptInstructions: string | null
    postInstructions: Record<SocialPlatform, string | null>
}>()

const page = usePage()

const PLATFORMS: SocialPlatform[] = ['linkedin', 'x', 'bluesky']

const EXAMPLES: Record<SocialPlatform, string> = {
    linkedin: 'E.g. more casual, first person, short punchy lines.',
    x: 'E.g. playful, no hashtags, always end with a question.',
    bluesky: 'E.g. conversational, no hashtags, one emoji at most.'
}

// Local drafts synced from the props, not `default-value`: Nuxt UI's textarea
// reads that once and every re-render (this page's own redirect after
// saving) would silently stomp whatever the user just typed.
const emailInstructions = ref(props.promptInstructions ?? '')
watch(() => props.promptInstructions, value => emailInstructions.value = value ?? '', { immediate: true })

const postDrafts = ref<Record<SocialPlatform, string>>({ linkedin: '', x: '', bluesky: '' })
watch(() => props.postInstructions, (value) => {
    postDrafts.value = { linkedin: value.linkedin ?? '', x: value.x ?? '', bluesky: value.bluesky ?? '' }
}, { immediate: true, deep: true })
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
                v-bind="emailInstructionsRoutes.update.form({ project: page.props.currentProject!.slug })"
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

        <UCard
            v-for="platform in PLATFORMS"
            :key="platform"
        >
            <template #header>
                <h2 class="font-medium">
                    How {{ PLATFORM_LABEL[platform] }} posts are written
                </h2>
                <p class="mt-1 text-sm text-muted">
                    Independent of the emails box and of the other networks: a public
                    post is a different kind of writing, with its own audience.
                    {{ EXAMPLES[platform] }}
                </p>
            </template>

            <Form
                v-slot="{ errors, processing, recentlySuccessful }"
                v-bind="postInstructionsRoutes.update.form({ project: page.props.currentProject!.slug, platform })"
                class="space-y-4"
            >
                <UFormField
                    name="instructions"
                    :error="errors.instructions"
                >
                    <UTextarea
                        v-model="postDrafts[platform]"
                        name="instructions"
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
