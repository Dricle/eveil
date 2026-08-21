<script setup lang="ts">
import { Form } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import knowledgeBaseRoutes from '@/routes/settings/knowledge-base'
import type { OpenQuestion } from '@/types'

// What the site never said, asked of the one person who knows. Answering is
// worth more than any other minute the user spends here, and it is still never
// required: the form is meant to be leavable half filled.
const props = defineProps<{
    questions: OpenQuestion[]
    title?: string
}>()

// Held here and bound with `v-model`, never left to `default-value`.
//
// Nuxt UI reads `defaultValue` ONCE, at mount, and Vue patches a form
// element's `value` against what the DOM currently holds rather than against
// the previous render. So every re-render writes that frozen first value back
// over whatever was typed: answer three questions, save, and the boxes come
// back empty while the counter above them says three of three. The data was
// never the problem; the display was overwriting itself.
const draft = ref<Record<string, string>>({})

watch(() => props.questions, (questions) => {
    draft.value = Object.fromEntries(questions.map(question => [question.key, question.answer ?? '']))
}, { immediate: true, deep: true })

const answered = computed(() => Object.values(draft.value).filter(answer => answer.trim() !== '').length)
</script>

<template>
    <div
        v-if="questions.length"
        class="space-y-3 rounded-lg p-4 ring ring-default"
    >
        <div>
            <p class="font-medium">
                {{ title ?? 'What your site does not say' }}
            </p>
            <p class="text-sm text-muted">
                Answers go into every mail written from here. Leave any of them
                blank and nothing breaks: they are asked because the pages did not
                answer them, not because the app is stuck.
            </p>
        </div>

        <Form
            v-slot="{ processing, recentlySuccessful }"
            v-bind="knowledgeBaseRoutes.answers.form()"
            :options="{ preserveScroll: true }"
            class="space-y-4"
        >
            <UFormField
                v-for="question in questions"
                :key="question.key"
                :label="question.question"
                :name="`answers[${question.key}]`"
            >
                <UTextarea
                    v-model="draft[question.key]"
                    :name="`answers[${question.key}]`"
                    :rows="2"
                    autoresize
                    class="w-full"
                />
            </UFormField>

            <div class="flex flex-wrap items-center gap-3">
                <UButton
                    type="submit"
                    :loading="processing"
                    label="Save answers"
                />
                <span
                    v-if="recentlySuccessful"
                    class="text-sm text-muted"
                >Saved.</span>
                <span
                    v-else
                    class="text-sm text-dimmed"
                >{{ answered }} of {{ questions.length }} answered</span>
            </div>
        </Form>
    </div>
</template>
