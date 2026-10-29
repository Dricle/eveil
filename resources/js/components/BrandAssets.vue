<script setup lang="ts">
import { Form, router } from '@inertiajs/vue3'
import { computed, ref } from 'vue'
import brandAssetRoutes from '@/routes/settings/brand-assets'
import type { ProjectDetail } from '@/types'

// The project's visual identity library: the images generated for it (an
// Instagram post's, an ad's) draw from these rather than inventing, and the
// videos film its screenshots.
const props = defineProps<{
    assets: ProjectDetail['brand_assets']
    slug: string
}>()

const toast = useToast()
const removing = ref<number | null>(null)
// One per section's form, sent through a hidden input: the same explicit
// shape the other forms here use rather than relying on the checkbox's own.
const rights = ref<Record<string, boolean>>({ logo: false, illustration: false, example: false, screenshot: false })

const SECTIONS = [
    {
        kind: 'logo' as const,
        title: 'Logo',
        description: 'Placed in the images as it is, never redrawn. Say otherwise in your AI instructions if you want it left out or placed a certain way. A new one replaces the old.',
        placeholder: 'E.g. the full logo, dark version'
    },
    {
        kind: 'illustration' as const,
        title: 'Official illustrations',
        description: 'Your own illustrations, mascots, product shots: reused in the images when they fit a post, instead of inventing one. Describe each so it is picked for the right post.',
        placeholder: 'E.g. the mascot waving, the dashboard on a laptop'
    },
    {
        kind: 'example' as const,
        title: 'Style examples',
        description: 'Visuals you already published: every image follows their palette and look, without copying them.',
        placeholder: 'E.g. our launch announcement'
    },
    {
        kind: 'screenshot' as const,
        title: 'Product screenshots',
        description: 'Screens of the product itself, filmed in the short videos that go out with a post: the camera zooms in, points and clicks. Full-size captures read best. Describe what each screen shows so the right one is picked.',
        placeholder: 'E.g. the dashboard with a week of data, the editor with a draft open'
    }
]

const byKind = computed(() => Object.fromEntries(SECTIONS.map(section => [section.kind, props.assets.filter(asset => asset.kind === section.kind)])))

function remove (id: number) {
    removing.value = id
    router.delete(brandAssetRoutes.destroy.url({ project: props.slug, asset: id }), {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'Image removed', color: 'neutral' }),
        onFinish: () => removing.value = null
    })
}
</script>

<template>
    <UCard>
        <template #header>
            <h2 class="font-medium">
                Visual identity
            </h2>
            <p class="mt-1 text-sm text-muted">
                The images Eveil generates for this project use these as their
                source of truth for how your brand looks. Only add images you hold
                the rights to: they are sent to the image model and end up in
                images published under your name.
            </p>
        </template>

        <div class="space-y-8">
            <section
                v-for="section in SECTIONS"
                :key="section.kind"
                class="space-y-3"
            >
                <div>
                    <h3 class="text-sm font-medium">
                        {{ section.title }}
                    </h3>
                    <p class="text-sm text-muted">
                        {{ section.description }}
                    </p>
                </div>

                <div
                    v-if="byKind[section.kind]?.length"
                    class="flex flex-wrap gap-3"
                >
                    <figure
                        v-for="asset in byKind[section.kind]"
                        :key="asset.id"
                        class="w-36 space-y-1"
                    >
                        <img
                            :src="asset.url"
                            :alt="asset.description || section.title"
                            class="h-24 w-36 rounded-md bg-elevated object-contain p-1"
                        >
                        <figcaption class="flex items-start gap-1 text-xs text-muted">
                            <span class="min-w-0 flex-1">{{ asset.description }}</span>
                            <UButton
                                color="error"
                                variant="ghost"
                                size="xs"
                                icon="i-lucide-trash-2"
                                aria-label="Remove this image"
                                :loading="removing === asset.id"
                                @click="remove(asset.id)"
                            />
                        </figcaption>
                    </figure>
                </div>

                <Form
                    v-slot="{ errors, processing }"
                    v-bind="brandAssetRoutes.store.form({ project: slug })"
                    reset-on-success
                    class="grid gap-3 rounded-lg p-3 ring ring-default sm:grid-cols-2"
                    :options="{ preserveScroll: true }"
                    @success="rights[section.kind] = false; toast.add({ title: 'Image added', color: 'success' })"
                >
                    <input
                        type="hidden"
                        name="kind"
                        :value="section.kind"
                    >

                    <UFormField
                        :label="section.kind === 'logo' && byKind.logo?.length ? 'Replace the logo' : 'Add an image'"
                        name="file"
                        description="PNG, JPEG or WebP, up to 5 MB."
                        :error="errors.file"
                    >
                        <input
                            type="file"
                            name="file"
                            accept="image/png,image/jpeg,image/webp"
                            class="text-sm"
                        >
                    </UFormField>

                    <UFormField
                        label="What it shows"
                        name="description"
                        :error="errors.description"
                    >
                        <UInput
                            name="description"
                            :placeholder="section.placeholder"
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        name="rights"
                        :error="errors.rights"
                        class="sm:col-span-2"
                    >
                        <UCheckbox
                            v-model="rights[section.kind]"
                            label="I hold the rights to this image"
                        />
                        <input
                            v-if="rights[section.kind]"
                            type="hidden"
                            name="rights"
                            value="1"
                        >
                    </UFormField>

                    <div class="sm:col-span-2">
                        <UButton
                            type="submit"
                            size="sm"
                            label="Add"
                            :loading="processing"
                        />
                    </div>
                </Form>
            </section>
        </div>
    </UCard>
</template>
