<script setup lang="ts">
import { Form, Head, router } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import AppSettingsLayout from '@/layouts/AppSettingsLayout.vue'
import { PLATFORM_LABEL } from '@/lib/social'
import socialPostExamples from '@/routes/app-settings/social-post-examples'
import type { SocialPlatform, SocialPostExampleRow } from '@/types'

defineOptions({ layout: [AppLayout, [AppSettingsLayout, { title: 'X & Bluesky post examples' }]] })

const props = defineProps<{
    examples: SocialPostExampleRow[]
    threshold: { min_likes: number }
}>()

const minLikes = ref(props.threshold.min_likes)

watch(() => props.threshold, (threshold) => {
    minLikes.value = threshold.min_likes
}, { immediate: true })

const PLATFORMS = [
    { label: 'Bluesky', value: 'bluesky' },
    { label: 'X', value: 'x' }
]

// One bank per network: the add form writes to, and the list shows, the
// one picked here.
const platform = ref<SocialPlatform>('bluesky')
const bank = computed(() => props.examples.filter(example => example.platform === platform.value))

function sourceLabel (source: string): string {
    return source === 'promoted' ? 'Promoted' : 'Manual'
}

const viewing = ref<SocialPostExampleRow | null>(null)
</script>

<template>
    <Head title="X & Bluesky post examples" />

    <div class="space-y-4">
        <UTabs
            v-model="platform"
            :items="PLATFORMS"
            :content="false"
        />

        <UCard>
            <template #header>
                <h2 class="font-medium">
                    Add a {{ PLATFORM_LABEL[platform] }} example
                </h2>
                <p class="mt-1 text-sm text-muted">
                    Fed back into every project's {{ PLATFORM_LABEL[platform] }} writer: a
                    random sample each time, as inspiration, never copied verbatim.
                    Instance-wide, shared across every tenant.
                </p>
            </template>

            <Form
                v-slot="{ errors, processing, recentlySuccessful }"
                v-bind="socialPostExamples.store.form()"
                class="space-y-4"
                reset-on-success
            >
                <input
                    type="hidden"
                    name="platform"
                    :value="platform"
                >

                <UFormField
                    label="Body"
                    name="body"
                    :error="errors.body"
                >
                    <UTextarea
                        name="body"
                        :rows="4"
                        autoresize
                        required
                        class="w-full"
                    />
                </UFormField>

                <div class="flex items-center gap-3">
                    <UButton
                        type="submit"
                        :loading="processing"
                        label="Add"
                    />
                    <span
                        v-if="recentlySuccessful"
                        class="text-sm text-muted"
                    >Added.</span>
                </div>
            </Form>
        </UCard>

        <UCard v-if="platform === 'bluesky'">
            <template #header>
                <h2 class="font-medium">
                    When a post earns its place here
                </h2>
                <p class="mt-1 text-sm text-muted">
                    Checked daily. A published Bluesky post joins the bank once its
                    real like count crosses this floor.
                </p>
            </template>

            <Form
                v-slot="{ errors, processing, recentlySuccessful }"
                v-bind="socialPostExamples.threshold.form()"
                class="space-y-4"
            >
                <UFormField
                    label="Minimum likes"
                    name="min_likes"
                    :error="errors.min_likes"
                    class="max-w-xs"
                >
                    <UInput
                        v-model="minLikes"
                        name="min_likes"
                        type="number"
                        min="1"
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

        <p
            v-else
            class="text-sm text-muted"
        >
            X posts never join this bank on their own: Eveil does not read their
            numbers, since X's API is paid. Add the ones that worked here by hand.
        </p>

        <UCard>
            <template #header>
                <h2 class="font-medium">
                    The {{ PLATFORM_LABEL[platform] }} bank ({{ bank.length }})
                </h2>
            </template>

            <p
                v-if="!bank.length"
                class="text-sm text-muted"
            >
                Nothing in it yet.
            </p>

            <div
                v-else
                class="space-y-2"
            >
                <div
                    v-for="example in bank"
                    :key="example.id"
                    class="flex cursor-pointer items-start justify-between gap-3 rounded-lg bg-elevated p-3 hover:bg-elevated/70"
                    @click="viewing = example"
                >
                    <div class="min-w-0">
                        <UBadge
                            :color="example.source === 'promoted' ? 'primary' : 'neutral'"
                            variant="subtle"
                            size="sm"
                            :label="sourceLabel(example.source)"
                        />
                        <p class="mt-1 line-clamp-2 text-sm text-dimmed">
                            {{ example.body }}
                        </p>
                    </div>

                    <UButton
                        type="button"
                        color="error"
                        variant="ghost"
                        icon="i-lucide-trash-2"
                        size="sm"
                        @click.stop="router.delete(socialPostExamples.destroy.url(example.id))"
                    />
                </div>
            </div>
        </UCard>
    </div>

    <UModal
        :open="viewing !== null"
        :title="viewing ? `${PLATFORM_LABEL[viewing.platform]} post example` : ''"
        :ui="{ content: 'max-w-lg' }"
        @update:open="(value: boolean) => { if (!value) viewing = null }"
    >
        <template #body>
            <div
                v-if="viewing"
                class="space-y-3"
            >
                <div class="flex items-center gap-2">
                    <UBadge
                        :color="viewing.source === 'promoted' ? 'primary' : 'neutral'"
                        variant="subtle"
                        size="sm"
                        :label="sourceLabel(viewing.source)"
                    />
                    <span
                        v-if="viewing.added_by"
                        class="text-sm text-dimmed"
                    >Added by {{ viewing.added_by }}</span>
                </div>
                <p class="whitespace-pre-line text-sm">
                    {{ viewing.body }}
                </p>
            </div>
        </template>

        <template #footer>
            <UButton
                color="neutral"
                variant="ghost"
                label="Close"
                @click="viewing = null"
            />
        </template>
    </UModal>
</template>
