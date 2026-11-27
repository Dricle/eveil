<script setup lang="ts">
import { Form, Head, router, usePage, usePoll } from '@inertiajs/vue3'
import { computed, reactive, ref, watch } from 'vue'
import LaunchItemRow from '@/components/LaunchItemRow.vue'
import type { LaunchItem } from '@/components/LaunchItemRow.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import launchRoutes from '@/routes/launch'

defineOptions({ layout: AppLayout })

const props = defineProps<{
    items: LaunchItem[]
    kinds: { value: string, label: string }[]
    tags: string[]
    tagsChosen: boolean
    tagOptions: { value: string }[]
}>()

const page = usePage()
const toast = useToast()
const slug = computed(() => page.props.currentProject!.slug)

// Copy is written on a worker: reread while any is.
const working = computed(() => props.items.some(item => item.drafting))
const { start, stop } = usePoll(4000, { only: ['items'] }, { autoStart: false })
watch(working, value => value ? start() : stop(), { immediate: true })

const TABS = [
    { label: 'To do', value: 'todo' },
    { label: 'Posted', value: 'posted' },
    { label: 'Dismissed', value: 'dismissed' }
] as const

const activeTab = ref<'todo' | 'posted' | 'dismissed'>('todo')
const count = (state: string) => props.items.filter(item => item.state === state).length
const tabs = computed(() => TABS.map(tab => ({ ...tab, label: `${tab.label} (${count(tab.value)})` })))

// The long tail of directories runs to hundreds: each group shows a page at a time.
const PAGE = 25
const shown = reactive<Record<string, number>>({})

const groups = computed(() => props.kinds
    .map(kind => ({ ...kind, items: props.items.filter(item => item.venue.kind === kind.value && item.state === activeTab.value) }))
    .filter(group => group.items.length > 0))

const TAG_LABELS: Record<string, string> = {
    software: 'Software',
    ai: 'AI',
    dev: 'For developers',
    open_source: 'Open source',
    nocode: 'No-code',
    b2b: 'B2B',
    bitcoin: 'Bitcoin'
}

const tagItems = computed(() => props.tagOptions.map(tag => ({ value: tag.value, label: TAG_LABELS[tag.value] ?? tag.value })))
const selectedTags = ref<string[]>([])
watch(() => props.tags, value => selectedTags.value = [...value], { immediate: true })

function saveTags (tags: string[]) {
    router.put(launchRoutes.tags.update.url({ project: slug.value }), { tags }, { preserveScroll: true })
}

const addOpen = ref(false)
const venue = reactive({ name: '', url: '', kind: 'directory', is_paid: false, price_note: '', cooldown_days: null as number | null, rules: '' })
</script>

<template>
    <Head title="Launch" />

    <div class="space-y-4 p-6">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <h2 class="font-medium">
                    Launch
                </h2>
                <p class="text-sm text-muted">
                    Every place your product can be posted: launch platforms, communities,
                    review sites and directories. Most have no API, so you post yourself:
                    Eveil writes the copy for each one and keeps track of where you posted.
                </p>
            </div>

            <div class="flex items-center gap-2">
                <UButton
                    label="Add a venue"
                    icon="i-lucide-plus"
                    color="neutral"
                    variant="subtle"
                    @click="addOpen = true"
                />
            </div>
        </div>

        <div class="space-y-2">
            <p class="text-sm text-muted">
                Your product is
                <span v-if="!tagsChosen">(our guess from your website, tick what fits)</span>
            </p>
            <UCheckboxGroup
                v-model="selectedTags"
                :items="tagItems"
                orientation="horizontal"
                @update:model-value="saveTags"
            />
        </div>

        <div
            v-if="items.length === 0"
            class="rounded-lg border border-dashed border-default p-10 text-center"
        >
            <p class="font-medium">
                No venues
            </p>
            <p class="mt-1 text-sm text-muted">
                Tick what your product is above: the venues that suit it appear here,
                with the subreddits your target profiles found.
            </p>
        </div>

        <template v-else>
            <UTabs
                v-model="activeTab"
                :items="tabs"
                :content="false"
            />

            <p
                v-if="groups.length === 0"
                class="text-sm text-muted"
            >
                Nothing here.
            </p>

            <section
                v-for="group in groups"
                :key="group.value"
            >
                <h3 class="text-sm font-medium text-muted">
                    {{ group.label }} ({{ group.items.length }})
                </h3>

                <div class="divide-y divide-default">
                    <LaunchItemRow
                        v-for="item in group.items.slice(0, shown[group.value] ?? PAGE)"
                        :key="item.id"
                        :item="item"
                    />
                </div>

                <UButton
                    v-if="group.items.length > (shown[group.value] ?? PAGE)"
                    :label="`Show ${Math.min(PAGE, group.items.length - (shown[group.value] ?? PAGE))} more`"
                    color="neutral"
                    variant="link"
                    size="sm"
                    @click="shown[group.value] = (shown[group.value] ?? PAGE) + PAGE"
                />
            </section>
        </template>

        <UModal
            v-model:open="addOpen"
            title="Add a venue"
            description="A place the list does not have. It joins your checklist like any other."
        >
            <template #body>
                <Form
                    v-slot="{ errors, processing }"
                    v-bind="launchRoutes.venues.store.form({ project: slug })"
                    class="space-y-4"
                    @success="addOpen = false; toast.add({ title: 'Venue added', color: 'success' })"
                >
                    <UFormField
                        label="Address"
                        :error="errors.url"
                        required
                    >
                        <UInput
                            v-model="venue.url"
                            name="url"
                            type="url"
                            placeholder="https://"
                            class="w-full"
                        />
                    </UFormField>
                    <UFormField
                        label="Name"
                        :error="errors.name"
                        required
                    >
                        <UInput
                            v-model="venue.name"
                            name="name"
                            class="w-full"
                        />
                    </UFormField>
                    <UFormField
                        label="Kind"
                        :error="errors.kind"
                    >
                        <USelect
                            v-model="venue.kind"
                            name="kind"
                            :items="kinds"
                            class="w-full"
                        />
                    </UFormField>
                    <div class="flex gap-4">
                        <UFormField label="Paid">
                            <USwitch v-model="venue.is_paid" />
                            <input
                                type="hidden"
                                name="is_paid"
                                :value="venue.is_paid ? 1 : 0"
                            >
                        </UFormField>
                        <UFormField
                            label="Price"
                            :error="errors.price_note"
                            class="flex-1"
                        >
                            <UInput
                                v-model="venue.price_note"
                                name="price_note"
                                placeholder="Free, $49 to skip the queue..."
                                class="w-full"
                            />
                        </UFormField>
                    </div>
                    <UFormField
                        label="Can post again after (days)"
                        help="Leave empty if you post there once."
                        :error="errors.cooldown_days"
                    >
                        <UInputNumber
                            v-model="venue.cooldown_days"
                            name="cooldown_days"
                            :min="1"
                            class="w-40"
                        />
                    </UFormField>
                    <UFormField
                        label="Rules"
                        help="What the venue expects: length limits, fields, tone. The copywriter follows them."
                        :error="errors.rules"
                    >
                        <UTextarea
                            v-model="venue.rules"
                            name="rules"
                            :rows="3"
                            class="w-full"
                        />
                    </UFormField>
                    <div class="flex justify-end">
                        <UButton
                            type="submit"
                            label="Add"
                            :loading="processing"
                        />
                    </div>
                </Form>
            </template>
        </UModal>
    </div>
</template>
