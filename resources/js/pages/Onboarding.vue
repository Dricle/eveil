<script setup lang="ts">
import { Head, router, usePage, usePoll } from '@inertiajs/vue3'
import { computed, ref, watch } from 'vue'
import OpenQuestions from '@/components/OpenQuestions.vue'
import AppLayout from '@/layouts/AppLayout.vue'
// Suffixed on purpose: an import sharing a name with a prop shadows that prop in
// every template expression, silently. `searches` and `knowledgeBase` are both
// props here.
import companyRoutes from '@/routes/companies'
import searchRoutes from '@/routes/onboarding'
import knowledgeBaseRoutes from '@/routes/settings/knowledge-base'
import mailboxRoutes from '@/routes/settings/mailboxes'
import targetRoutes from '@/routes/targets'
import type { Analysis, KnowledgeBase, OpenQuestion, TargetProfile } from '@/types'

defineOptions({ layout: AppLayout })

const props = defineProps<{
    analysis: Analysis | null
    knowledgeBase: KnowledgeBase | null
    openQuestions: OpenQuestion[]
    profiles: TargetProfile[]
    deriving: boolean
    searches: number
}>()

const page = usePage()
const projectSlug = computed(() => page.props.currentProject!.slug)

// The shared flag the "no mailbox connected" banner already uses, read the
// other way round. True here means this project can actually send.
const hasMailbox = computed(() => page.props.setup?.mailbox === false)

// Agreeing to the segments, which is a position within this screen rather than
// a fact about the project: there is no column for "has seen the mailbox step"
// and adding one would be a third thing able to disagree with the other two.
// Reloading simply puts the segments back in front of you, which is harmless.
const confirmedTargets = ref(false)

// Where the run has actually got to. Derived rather than stored: every one of
// these is a fact about the project, and a column saying "step 3" would be one
// more thing able to disagree with reality.
const step = computed(() => {
    if (props.searches > 0) {
        return 'searching'
    }

    if (props.deriving) {
        return 'deriving'
    }

    if (props.profiles.length > 0) {
        // The mailbox is asked for between agreeing to the segments and the
        // first search, because the search is the step that spends credits and
        // the send is the step that pays them back. Ending the run on the
        // search means a trial can be spent in full without a mail ever
        // leaving, and the banner on every screen does not stop that.
        return confirmedTargets.value && !hasMailbox.value ? 'mailbox' : 'review_targets'
    }

    if (props.analysis?.status === 'failed') {
        return 'failed'
    }

    if (props.knowledgeBase && !props.analysis?.running) {
        return 'review_product'
    }

    return 'analysing'
})

const working = computed(() => step.value === 'analysing' || step.value === 'deriving')

// Only while something is out. Nothing on this page changes on its own once the
// user is the one who has to act.
const poll = usePoll(3000, {}, { autoStart: working.value })

watch(working, busy => busy ? poll.start() : poll.stop())

const STEPS = [
    { key: 'product', label: 'Reading your site' },
    { key: 'targets', label: 'Working out who buys it' },
    { key: 'mailbox', label: 'Connecting your mailbox' },
    { key: 'search', label: 'Looking for them' }
] as const

function state (key: typeof STEPS[number]['key']) {
    // The one stage that is not positional: it can be satisfied from another
    // screen, or have been satisfied before this project existed, and it can
    // be skipped. Skipped is deliberately not done — a tick here on a run
    // that went past it without a mailbox would be the screen agreeing that
    // nothing is outstanding.
    if (key === 'mailbox') {
        return hasMailbox.value
            ? 'done'
            : step.value === 'mailbox' ? 'current' : 'waiting'
    }

    const order = ['product', 'targets', 'mailbox', 'search']
    const reached = {
        analysing: 0, failed: 0, review_product: 1, deriving: 1, review_targets: 2, mailbox: 2, searching: 4
    }[step.value] ?? 0

    const index = order.indexOf(key)

    return index < reached ? 'done' : index === reached ? 'current' : 'waiting'
}

const LISTS = ['key_features', 'competitors', 'proof_points'] as const

function startSearching () {
    router.post(searchRoutes.searches.url({ project: projectSlug.value }), {}, { preserveScroll: true })
}

/**
 * Agreeing to the segments. With a mailbox already attached this is what it
 * has always been, a single click that starts the search; without one it stops
 * at the mailbox step first, which is the only point in the run where asking
 * for it is still cheap.
 */
function confirmTargets () {
    confirmedTargets.value = true

    if (hasMailbox.value) {
        startSearching()
    }
}
</script>

<template>
    <Head title="Getting started" />

    <div class="mx-auto max-w-3xl space-y-6 p-6">
        <div>
            <h2 class="font-medium">
                Setting up
            </h2>
            <p class="text-sm text-muted">
                Nothing is sent to anybody during any of this. You are agreeing
                to what the agent understood before it is used to write a word.
            </p>
        </div>

        <!-- Three stages, and which one we are on. Worth the space: the first
             one takes a minute or two, and a spinner alone does not say what
             is being waited for. -->
        <div class="flex flex-wrap gap-3">
            <div
                v-for="item in STEPS"
                :key="item.key"
                class="flex flex-1 items-center gap-2 rounded-lg p-3 text-sm ring"
                :class="state(item.key) === 'current' ? 'ring-primary' : 'ring-default'"
            >
                <UIcon
                    :name="state(item.key) === 'done'
                        ? 'i-lucide-check'
                        : state(item.key) === 'current' ? 'i-lucide-loader' : 'i-lucide-circle-dashed'"
                    :class="state(item.key) === 'current' && working ? 'animate-spin' : ''"
                />
                <span :class="state(item.key) === 'waiting' ? 'text-dimmed' : ''">{{ item.label }}</span>
            </div>
        </div>

        <!-- 1. Reading the site -->
        <div
            v-if="step === 'analysing'"
            class="space-y-2 rounded-lg p-4 ring ring-default"
        >
            <p class="font-medium">
                Reading your site
            </p>
            <p class="text-sm text-muted">
                {{ analysis?.pages_read ?? 0 }} of up to {{ analysis?.pages_planned ?? 0 }} pages read.
                It takes a minute or two. The portrait appears here as soon as
                the model has seen enough.
            </p>
            <UProgress
                v-if="analysis?.pages_planned"
                :model-value="analysis.pages_read"
                :max="analysis.pages_planned"
            />
        </div>

        <UAlert
            v-else-if="step === 'failed'"
            color="error"
            variant="subtle"
            icon="i-lucide-triangle-alert"
            title="The site could not be read"
            :description="analysis?.error ?? 'Check the address and try again.'"
        >
            <template #actions>
                <UButton
                    color="neutral"
                    variant="subtle"
                    label="Project settings"
                    @click="router.get(knowledgeBaseRoutes.edit.url({ project: projectSlug }))"
                />
            </template>
        </UAlert>

        <!-- 2. What it understood, and the confirmation that starts the next
             stage. -->
        <div
            v-else-if="step === 'review_product'"
            class="space-y-3 rounded-lg p-4 ring ring-default"
        >
            <p class="font-medium">
                This is what it understood
            </p>

            <dl class="space-y-2 text-sm">
                <div v-if="knowledgeBase?.what_it_does">
                    <dt class="text-dimmed">
                        What it does
                    </dt>
                    <dd>{{ knowledgeBase.what_it_does }}</dd>
                </div>
                <div v-if="knowledgeBase?.who_it_is_for">
                    <dt class="text-dimmed">
                        Who it is for
                    </dt>
                    <dd>{{ knowledgeBase.who_it_is_for }}</dd>
                </div>
                <div v-if="knowledgeBase?.value_proposition">
                    <dt class="text-dimmed">
                        Why anybody switches
                    </dt>
                    <dd>{{ knowledgeBase.value_proposition }}</dd>
                </div>
                <div
                    v-for="list in LISTS"
                    :key="list"
                >
                    <dt
                        v-if="knowledgeBase?.[list]?.length"
                        class="text-dimmed"
                    >
                        {{ list.replaceAll('_', ' ') }}
                    </dt>
                    <dd v-if="knowledgeBase?.[list]?.length">
                        {{ knowledgeBase[list].join(' · ') }}
                    </dd>
                </div>
            </dl>

            <!-- Asked before the confirmation, not after it: the answers
                 feed the segments the next button derives. -->
            <OpenQuestions :questions="openQuestions" />

            <div class="flex flex-wrap items-center gap-2">
                <UButton
                    icon="i-lucide-check"
                    label="That's right, find who buys it"
                    @click="router.post(targetRoutes.derive.url({ project: projectSlug }), {}, { preserveScroll: true })"
                />
                <UButton
                    color="neutral"
                    variant="ghost"
                    label="Correct it first"
                    @click="router.get(knowledgeBaseRoutes.edit.url({ project: projectSlug }))"
                />
            </div>

            <p class="text-sm text-dimmed">
                Every mail is written from this. Correcting it now is cheaper
                than correcting it in three hundred mails.
            </p>
        </div>

        <div
            v-else-if="step === 'deriving'"
            class="space-y-2 rounded-lg p-4 ring ring-default"
        >
            <p class="font-medium">
                Working out who buys it
            </p>
            <p class="text-sm text-muted">
                Segments, and the search terms that find each one. This may take a minute or two.
            </p>
        </div>

        <!-- 3. The segments, and the confirmation that starts the search. -->
        <div
            v-else-if="step === 'review_targets'"
            class="space-y-3 rounded-lg p-4 ring ring-default"
        >
            <p class="font-medium">
                Who it thinks buys it
            </p>

            <div
                v-for="profile in profiles"
                :key="profile.id"
                class="space-y-1 rounded-lg p-3 ring ring-default"
            >
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-medium">{{ profile.name }}</span>
                    <UBadge
                        v-if="profile.type === 'partner'"
                        color="primary"
                        variant="subtle"
                        label="Partner"
                        icon="i-lucide-handshake"
                    />
                    <UBadge
                        v-if="!profile.is_active"
                        color="neutral"
                        variant="subtle"
                        label="Paused"
                    />
                </div>
                <p
                    v-if="profile.criteria?.rationale"
                    class="text-sm text-muted"
                >
                    {{ profile.criteria.rationale }}
                </p>
                <p
                    v-if="profile.criteria?.geography?.length"
                    class="text-sm text-dimmed"
                >
                    {{ profile.criteria.geography.join(', ') }}
                </p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Agreeing goes through the mailbox first when there is
                     none. The search itself is unchanged: it is still one
                     click away, and skipping is still allowed. -->
                <UButton
                    icon="i-lucide-radar"
                    label="Start looking for them"
                    @click="confirmTargets"
                />
                <UButton
                    color="neutral"
                    variant="ghost"
                    label="Edit the segments"
                    @click="router.get(targetRoutes.index.url({ project: projectSlug }))"
                />
            </div>

            <p class="text-sm text-dimmed">
                One search per segment left switched on. Nothing is written to
                anybody. The searches only find companies.
            </p>
        </div>

        <!-- 4. Where the mail will go out from, asked before the search and
             not after it. Searching and qualifying are what spend the credits;
             sending is what they were spent for. A run that ends at the search
             can empty a balance and still have shown nobody a single mail. -->
        <div
            v-else-if="step === 'mailbox'"
            class="space-y-3 rounded-lg p-4 ring ring-default"
        >
            <p class="font-medium">
                Where should the mail go out from?
            </p>

            <p class="text-sm text-muted">
                Your own mailbox, over ordinary SMTP, with replies read back
                over IMAP. Nothing is relayed through us, so what arrives is a
                message from you. Pick your provider on the next screen and the
                host names and ports fill themselves in.
            </p>

            <div class="flex flex-wrap items-center gap-2">
                <UButton
                    icon="i-lucide-mail"
                    label="Connect a mailbox"
                    @click="router.get(mailboxRoutes.index.url({ project: projectSlug }, { query: { from: 'onboarding' } }))"
                />
                <UButton
                    color="neutral"
                    variant="ghost"
                    label="Skip, start the search anyway"
                    @click="startSearching"
                />
            </div>

            <p class="text-sm text-dimmed">
                Skipping is fine, and it is not free: the search and the
                qualifying spend credits, and without a mailbox the campaign
                written from them sits still. You can come back to this from
                Settings at any point.
            </p>
        </div>

        <!-- 5. Done: the searching is under way and this page has nothing
             left to do. -->
        <div
            v-else
            class="space-y-3 rounded-lg p-4 ring ring-default"
        >
            <p class="font-medium">
                The search is running
            </p>
            <p class="text-sm text-muted">
                Companies appear in Leads as they are found and qualified, each
                with the sentence that justifies its score. When you have some,
                a campaign is written from the same portrait you just agreed to.
            </p>

            <div class="flex flex-wrap items-center gap-2">
                <UButton
                    label="See what has been found"
                    @click="router.get(companyRoutes.index.url({ project: projectSlug }))"
                />
                <UButton
                    color="neutral"
                    variant="ghost"
                    label="Watch the search"
                    @click="router.get(targetRoutes.index.url({ project: projectSlug }))"
                />
            </div>
        </div>
    </div>
</template>
