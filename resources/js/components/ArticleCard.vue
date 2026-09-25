<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3'
import type { EditorToolbarItem } from '@nuxt/ui'
import DOMPurify from 'dompurify'
import { marked } from 'marked'
import { computed, ref } from 'vue'
import { openEvieChat } from '@/composables/useChatPanel'
import articleRoutes from '@/routes/seo/articles'
import type { Article } from '@/types'

// One article with every action on it. Shared by `seo/Index.vue` and the
// dashboard's to-review modal. `expanded` starts the body open, which the
// modal wants and the list does not.
const props = defineProps<{
    article: Article
    expanded?: boolean
}>()

// Emitted when the user hands the article to Evie, so a modal around this
// card can get out of the chat panel's way.
const emit = defineEmits<{ rework: [] }>()

const page = usePage()
const toast = useToast()

const SOURCE = {
    feature: 'Feature not covered yet',
    competitor: 'Competitor comparison',
    reddit_thread: 'Asked on Reddit',
    client_won: 'Client win',
    news: 'Industry news',
    agent_choice: 'Picked by Eveil',
    manual: 'Asked via Evie'
}

const STATUS = {
    draft: { color: 'neutral' as const, label: 'Draft' },
    published: { color: 'success' as const, label: 'Published' },
    rejected: { color: 'neutral' as const, label: 'Rejected' }
}

// What an SEO article actually uses: section headings, lists, quotes,
// emphasis and links. No images, alignment or code blocks.
const TOOLBAR: EditorToolbarItem[][] = [[
    { kind: 'undo', icon: 'i-lucide-undo', tooltip: { text: 'Undo' } },
    { kind: 'redo', icon: 'i-lucide-redo', tooltip: { text: 'Redo' } }
], [
    { kind: 'heading', level: 2, icon: 'i-lucide-heading-2', tooltip: { text: 'Heading' } },
    { kind: 'heading', level: 3, icon: 'i-lucide-heading-3', tooltip: { text: 'Subheading' } },
    { kind: 'bulletList', icon: 'i-lucide-list', tooltip: { text: 'Bullet list' } },
    { kind: 'orderedList', icon: 'i-lucide-list-ordered', tooltip: { text: 'Numbered list' } },
    { kind: 'blockquote', icon: 'i-lucide-text-quote', tooltip: { text: 'Quote' } }
], [
    { kind: 'mark', mark: 'bold', icon: 'i-lucide-bold', tooltip: { text: 'Bold' } },
    { kind: 'mark', mark: 'italic', icon: 'i-lucide-italic', tooltip: { text: 'Italic' } },
    { kind: 'link', icon: 'i-lucide-link', tooltip: { text: 'Link' } }
]]

const showBody = ref(props.expanded ?? false)
const html = computed(() => DOMPurify.sanitize(marked.parse(props.article.body, { async: false })))

const editing = ref(false)
const draft = ref({ title: '', meta_description: '', body: '' })
const saving = ref(false)
const publishOpen = ref(false)
const publishedUrl = ref('')
const publishing = ref(false)
const rejectOpen = ref(false)
const rejectReason = ref('')
const rejecting = ref(false)
const deleting = ref(false)

const slug = computed(() => page.props.currentProject!.slug)

// While editing, copies the draft so unsaved changes come along.
async function copy () {
    const { title, body } = editing.value ? draft.value : props.article

    try {
        await navigator.clipboard.writeText(`# ${title}\n\n${body}`)
        toast.add({ title: 'Copied as Markdown', color: 'success' })
    } catch {
        toast.add({ title: 'Could not copy - select and copy manually', color: 'error' })
    }
}

function startEdit () {
    draft.value = {
        title: props.article.title,
        meta_description: props.article.meta_description ?? '',
        body: props.article.body
    }
    editing.value = true
    showBody.value = true
}

function save () {
    saving.value = true
    router.put(articleRoutes.update.url({ project: slug.value, article: props.article.id }), draft.value, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ title: 'Article saved', color: 'success' })
            editing.value = false
        },
        onFinish: () => saving.value = false
    })
}

function rework () {
    openEvieChat(`Let's rework the article #${props.article.id} "${props.article.title}".`)
    emit('rework')
}

function confirmPublish () {
    publishing.value = true
    router.post(articleRoutes.publish.url({ project: slug.value, article: props.article.id }), { published_url: publishedUrl.value }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ title: 'Marked as published', color: 'success' })
            publishOpen.value = false
        },
        onFinish: () => publishing.value = false
    })
}

function confirmReject () {
    rejecting.value = true
    router.post(articleRoutes.reject.url({ project: slug.value, article: props.article.id }), { reason: rejectReason.value }, {
        preserveScroll: true,
        onSuccess: () => {
            toast.add({ title: 'Article rejected', color: 'neutral' })
            rejectOpen.value = false
        },
        onFinish: () => rejecting.value = false
    })
}

function destroy () {
    deleting.value = true
    router.delete(articleRoutes.destroy.url({ project: slug.value, article: props.article.id }), {
        preserveScroll: true,
        onSuccess: () => toast.add({ title: 'Article deleted', color: 'neutral' }),
        onFinish: () => deleting.value = false
    })
}
</script>

<template>
    <div class="space-y-3 rounded-lg p-4 ring ring-default">
        <div class="flex flex-wrap items-center gap-2">
            <UBadge
                color="neutral"
                variant="subtle"
                :label="SOURCE[article.source_type]"
            />
            <UBadge
                :color="STATUS[article.status].color"
                variant="subtle"
                :label="STATUS[article.status].label"
            />
            <UBadge
                v-if="article.language"
                color="neutral"
                variant="outline"
                :label="article.language.toUpperCase()"
            />

            <div class="ml-auto flex flex-wrap items-center gap-2">
                <UButton
                    icon="i-lucide-copy"
                    color="neutral"
                    variant="ghost"
                    size="xs"
                    label="Copy"
                    @click="copy"
                />
                <template v-if="article.status === 'draft'">
                    <UButton
                        icon="i-lucide-sparkles"
                        color="neutral"
                        variant="ghost"
                        size="xs"
                        label="Rework with Evie"
                        @click="rework"
                    />
                    <UButton
                        icon="i-lucide-pencil"
                        color="neutral"
                        variant="ghost"
                        size="xs"
                        label="Edit"
                        @click="editing ? editing = false : startEdit()"
                    />
                    <UButton
                        icon="i-lucide-check"
                        color="success"
                        variant="subtle"
                        size="xs"
                        label="Mark as published"
                        @click="publishedUrl = ''; publishOpen = true"
                    />
                    <UButton
                        icon="i-lucide-x"
                        color="error"
                        variant="ghost"
                        size="xs"
                        label="Reject"
                        @click="rejectReason = ''; rejectOpen = true"
                    />
                </template>
                <UButton
                    icon="i-lucide-trash"
                    color="neutral"
                    variant="ghost"
                    size="xs"
                    :loading="deleting"
                    @click="destroy"
                />
            </div>
        </div>

        <p class="text-sm text-dimmed">
            {{ article.evidence }}
            <a
                v-if="article.source_type === 'reddit_thread' && article.source_ref"
                :href="article.source_ref"
                target="_blank"
                rel="noopener"
                class="text-primary"
            >Open thread ↗</a>
        </p>

        <div
            v-if="editing"
            class="space-y-3"
        >
            <UFormField label="Title">
                <UInput
                    v-model="draft.title"
                    class="w-full"
                />
            </UFormField>
            <UFormField label="Meta description">
                <UInput
                    v-model="draft.meta_description"
                    class="w-full"
                />
            </UFormField>
            <!-- Rich text over the same Markdown the writer produced and the
                 Copy button hands out: the model stays a Markdown string. -->
            <UFormField label="Body">
                <UEditor
                    v-slot="{ editor }"
                    v-model="draft.body"
                    content-type="markdown"
                    :image="false"
                    :mention="false"
                    class="min-h-80 w-full rounded-md ring ring-default"
                    :ui="{ base: 'p-4 sm:px-6' }"
                >
                    <UEditorToolbar
                        :editor="editor"
                        :items="TOOLBAR"
                        class="sticky top-0 z-10 overflow-x-auto border-b border-muted bg-default px-4 py-2 sm:px-6"
                    />
                </UEditor>
            </UFormField>
            <div class="flex gap-2">
                <UButton
                    size="xs"
                    label="Save"
                    :loading="saving"
                    @click="save"
                />
                <UButton
                    icon="i-lucide-copy"
                    color="neutral"
                    variant="ghost"
                    size="xs"
                    label="Copy Markdown source"
                    @click="copy"
                />
            </div>
        </div>

        <template v-else>
            <div>
                <h3 class="font-semibold text-highlighted">
                    {{ article.title }}
                </h3>
                <p
                    v-if="article.meta_description"
                    class="mt-1 text-sm text-muted"
                >
                    {{ article.meta_description }}
                </p>
            </div>

            <!-- Rendered from the writer's own Markdown, sanitised the
                 same way as Evie's chat answers. -->
            <!-- eslint-disable vue/no-v-html -->
            <div
                v-if="showBody"
                class="prose prose-sm dark:prose-invert max-w-none"
                v-html="html"
            />
            <!-- eslint-enable vue/no-v-html -->
            <UButton
                color="neutral"
                variant="link"
                size="xs"
                class="px-0"
                :label="showBody ? 'Hide the article' : 'Read the article'"
                @click="showBody = !showBody"
            />
        </template>

        <a
            v-if="article.published_url"
            :href="article.published_url"
            target="_blank"
            rel="noopener"
            class="block text-sm text-primary"
        >{{ article.published_url }} ↗</a>

        <p
            v-if="article.status === 'rejected' && article.rejection_reason"
            class="text-sm text-dimmed"
        >
            Rejected: {{ article.rejection_reason }}
        </p>

        <UModal
            v-model:open="publishOpen"
            title="Mark as published"
        >
            <template #body>
                <UFormField
                    label="Where did it go live?"
                    description="Eveil reads the page and will not write the same article again."
                >
                    <UInput
                        v-model="publishedUrl"
                        class="w-full"
                        placeholder="https://yoursite.com/blog/..."
                    />
                </UFormField>
            </template>

            <template #footer>
                <UButton
                    color="neutral"
                    variant="ghost"
                    label="Cancel"
                    @click="publishOpen = false"
                />
                <UButton
                    color="success"
                    label="Mark as published"
                    :disabled="!publishedUrl"
                    :loading="publishing"
                    @click="confirmPublish"
                />
            </template>
        </UModal>

        <UModal
            v-model:open="rejectOpen"
            title="Reject this article"
        >
            <template #body>
                <UFormField
                    label="Why? (optional)"
                    description="Fed back to the writer so it doesn't repeat this."
                >
                    <UTextarea
                        v-model="rejectReason"
                        :rows="4"
                        class="w-full"
                        placeholder="Wrong topic, too generic, ..."
                    />
                </UFormField>
            </template>

            <template #footer>
                <UButton
                    color="neutral"
                    variant="ghost"
                    label="Cancel"
                    @click="rejectOpen = false"
                />
                <UButton
                    color="error"
                    label="Reject"
                    :loading="rejecting"
                    @click="confirmReject"
                />
            </template>
        </UModal>
    </div>
</template>
