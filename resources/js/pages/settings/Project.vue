<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import projectRoutes from '@/routes/settings/project'
import type { ProjectDetail } from '@/types'

const props = defineProps<{ project: ProjectDetail }>()

const confirmingDelete = ref(false)

// Every field is bound, never left to `default-value`: Nuxt UI reads that prop
// once at mount, and Vue then patches a form element's value against what the
// DOM holds, so every later render writes the frozen first value back over what
// was typed. Saving re-renders this page, which is exactly when it bites.
const name = ref(props.project.name)
const url = ref(props.project.url)
const instructions = ref(props.project.prompt_instructions ?? '')
const githubToken = ref('')
const autonomy = ref(props.project.autonomy_level)
const dailyLeadLimit = ref(props.project.daily_lead_limit)
const leadLimit = ref(props.project.lead_limit)

watch(() => props.project, (project) => {
    name.value = project.name
    url.value = project.url
    instructions.value = project.prompt_instructions ?? ''
    autonomy.value = project.autonomy_level
    dailyLeadLimit.value = project.daily_lead_limit
    leadLimit.value = project.lead_limit
})

const AUTONOMY = [
    {
        label: 'Supervised',
        value: 'supervised',
        help: 'Nothing is written to anybody until you approve the company AND start the campaign yourself. Nobody is added to a running sequence behind you.'
    },
    {
        label: 'Semi automatic',
        value: 'semi_auto',
        help: 'You approve companies; everything after that happens on its own. Approving one also goes looking for the people there, and they join the running sequence as they are found.'
    },
    {
        label: 'Autonomous',
        value: 'autonomous',
        help: 'No approval is asked for. Every company a search qualifies is written to, unless you have set it aside yourself.'
    }
]
</script>

<template>
    <SettingsLayout title="Project">
        <Head title="Project" />

        <div class="max-w-2xl space-y-4">
            <UCard>
                <template #header>
                    <h2 class="font-medium">
                        Project
                    </h2>
                    <p class="mt-1 text-sm text-muted">
                        Changing the address re-reads the site and rebuilds the
                        knowledge base.
                    </p>
                </template>

                <Form
                    v-slot="{ errors, processing, recentlySuccessful }"
                    v-bind="projectRoutes.update.form()"
                    class="space-y-4"
                    @success="githubToken = ''"
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

                    <UFormField
                        label="Website"
                        name="url"
                        :error="errors.url"
                    >
                        <UInput
                            v-model="url"
                            name="url"
                            required
                            class="w-full"
                        />
                    </UFormField>

                    <!-- Same form as the name and the address on purpose: a
                         second one posting to this route would re-check that the
                         website answers every time somebody edits the tone. -->
                    <UFormField
                        label="How the AI writes"
                        name="prompt_instructions"
                        :error="errors.prompt_instructions"
                        help="Followed by every sequence and every mail personalised from one. E.g. write in French, never use emoji, say vous rather than tu."
                    >
                        <UTextarea
                            v-model="instructions"
                            name="prompt_instructions"
                            :rows="5"
                            :maxlength="2000"
                            class="w-full"
                        />
                    </UFormField>

                    <!-- Never sent back from the server: blank always means
                         "unchanged" here, never "no token stored". -->
                    <UFormField
                        label="GitHub token"
                        name="github_token"
                        :error="errors.github_token"
                        help="Leave blank to keep the one stored. Only needed to read a private repository — a fine-grained personal access token scoped to just that repo is safest."
                    >
                        <UInput
                            v-model="githubToken"
                            name="github_token"
                            type="password"
                            placeholder="github_pat_…"
                            class="w-full"
                        />
                    </UFormField>

                    <!-- The one setting that decides how much happens without
                         being asked. Worth spelling out per option: "semi auto"
                         says nothing on its own about who gets written to. -->
                    <UFormField
                        label="How much it does on its own"
                        name="autonomy_level"
                        :error="errors.autonomy_level"
                        :help="AUTONOMY.find(level => level.value === autonomy)?.help"
                    >
                        <USelect
                            v-model="autonomy"
                            name="autonomy_level"
                            :items="AUTONOMY"
                            class="w-full"
                        />
                    </UFormField>

                    <!-- Continuous discovery's throttle: how far it may go
                         before it stops on its own, for today and forever. -->
                    <UFormField
                        label="New leads per day"
                        name="daily_lead_limit"
                        :error="errors.daily_lead_limit"
                        help="Discovery and contact-finding pause for the rest of the day once this many new leads have been found today. Leave empty for no daily cap. Counts every lead on the project, however it was found."
                    >
                        <UInput
                            v-model="dailyLeadLimit"
                            type="number"
                            name="daily_lead_limit"
                            min="1"
                            placeholder="No daily cap"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        label="New leads, ever"
                        name="lead_limit"
                        :error="errors.lead_limit"
                        help="Discovery and contact-finding stop for good once the project has this many leads in total. Leave empty for no lifetime cap."
                    >
                        <UInput
                            v-model="leadLimit"
                            type="number"
                            name="lead_limit"
                            min="1"
                            placeholder="No lifetime cap"
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
                        Delete this project
                    </h2>
                </template>

                <p class="text-sm text-muted">
                    Deleting <strong>{{ project.name }}</strong> also
                    deletes its leads, companies and campaigns. This cannot be
                    undone.
                </p>

                <template #footer>
                    <UButton
                        color="error"
                        variant="soft"
                        label="Delete project"
                        @click="confirmingDelete = true"
                    />
                </template>
            </UCard>
        </div>

        <UModal
            v-model:open="confirmingDelete"
            title="Delete project"
        >
            <template #body>
                <p class="text-sm text-muted">
                    <strong>{{ project.name }}</strong> and everything
                    found for it will be deleted.
                </p>
            </template>

            <template #footer>
                <div class="flex w-full justify-end gap-2">
                    <UButton
                        label="Cancel"
                        color="neutral"
                        variant="ghost"
                        @click="confirmingDelete = false"
                    />
                    <UButton
                        label="Delete"
                        color="error"
                        @click="router.delete(projectRoutes.destroy.url())"
                    />
                </div>
            </template>
        </UModal>
    </SettingsLayout>
</template>
