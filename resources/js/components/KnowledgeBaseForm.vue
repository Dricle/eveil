<script setup lang="ts">
import { Form, usePage } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import knowledgeBaseRoutes from '@/routes/settings/knowledge-base'
import type { KnowledgeBase } from '@/types'

// The portrait of the product as an editable form, on the settings screen and
// in the onboarding's "Correct it" modal. Saving marks the portrait as edited
// by the user, so a later reading of the site never overwrites it.
const props = defineProps<{ knowledgeBase: KnowledgeBase | null }>()

const emit = defineEmits<{ saved: [] }>()

const page = usePage()

const TEXTS = [
    { name: 'what_it_does', label: 'What it does', help: 'What the product is, and the problem it removes.' },
    { name: 'who_it_is_for', label: 'Who it is for', help: 'The kind of company, and the kind of person who buys it.' },
    { name: 'value_proposition', label: 'Value proposition', help: 'The single strongest reason someone switches to it.' },
    { name: 'positioning', label: 'Positioning', help: 'How it frames itself against the alternatives, including doing nothing.' },
    { name: 'pricing_model', label: 'Pricing model', help: 'How it charges, as stated on the site.' }
] as const

const LISTS = [
    { name: 'key_features', label: 'Key features' },
    { name: 'competitors', label: 'Competitors' },
    { name: 'proof_points', label: 'Proof points' }
] as const

// Every box is bound, never left to `default-value`: Nuxt UI reads that prop
// once at mount, and Vue then patches a form element's value against what the
// DOM holds, so every later render writes the frozen first value back over what
// was typed. The settings screen polls every three seconds while a crawl is
// out, so without this it would wipe what somebody is typing.
const draft = ref<Record<string, string>>({})

watch(() => props.knowledgeBase, (knowledgeBase) => {
    draft.value = {
        ...Object.fromEntries(TEXTS.map(field => [field.name, knowledgeBase?.[field.name] ?? ''])),
        // The list fields go over the wire one item per line; the server splits them.
        ...Object.fromEntries(LISTS.map(field => [field.name, (knowledgeBase?.[field.name] ?? []).join('\n')]))
    }
}, { immediate: true, deep: true })
</script>

<template>
    <Form
        v-slot="{ errors, processing, recentlySuccessful }"
        v-bind="knowledgeBaseRoutes.update.form({ project: page.props.currentProject!.slug })"
        :options="{ preserveScroll: true }"
        class="space-y-4"
        @success="emit('saved')"
    >
        <UFormField
            v-for="field in TEXTS"
            :key="field.name"
            :label="field.label"
            :name="field.name"
            :help="field.help"
            :error="errors[field.name]"
        >
            <UTextarea
                v-model="draft[field.name]"
                :name="field.name"
                :rows="3"
                autoresize
                class="w-full"
            />
        </UFormField>

        <UFormField
            v-for="field in LISTS"
            :key="field.name"
            :label="field.label"
            :name="field.name"
            help="One per line."
            :error="errors[field.name]"
        >
            <UTextarea
                v-model="draft[field.name]"
                :name="field.name"
                :rows="3"
                autoresize
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
            <slot name="actions" />
        </div>
    </Form>
</template>
