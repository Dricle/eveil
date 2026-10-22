<script setup lang="ts">
import { Head, router, usePage, usePoll } from '@inertiajs/vue3'
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import ChannelCards from '@/components/ChannelCards.vue'
import KnowledgeBaseForm from '@/components/KnowledgeBaseForm.vue'
import MailboxForm from '@/components/MailboxForm.vue'
import OpenQuestions from '@/components/OpenQuestions.vue'
import AppLayout from '@/layouts/AppLayout.vue'
import { dashboard } from '@/routes'
// Suffixed on purpose: an import sharing a name with a prop shadows that prop in
// every template expression, silently. `searches` and `knowledgeBase` are both
// props here.
import companyRoutes from '@/routes/companies'
import searchRoutes from '@/routes/onboarding'
import knowledgeBaseRoutes from '@/routes/settings/knowledge-base'
import targetRoutes from '@/routes/targets'
import type { Analysis, ChannelToggle, KnowledgeBase, OpenQuestion, Project, TargetProfile } from '@/types'

defineOptions({ layout: AppLayout })

const props = defineProps<{
    analysis: Analysis | null
    knowledgeBase: KnowledgeBase | null
    openQuestions: OpenQuestion[]
    profiles: TargetProfile[]
    deriving: boolean
    /** The latest segment derivation: queued, running, or why it failed. */
    derivation: { status: string, since: string | null, error: string | null } | null
    searches: number
    channels: ChannelToggle[]
    /** Which projects a mailbox connected here may be granted to. */
    projects: Project[]
}>()

const page = usePage()
const projectSlug = computed(() => page.props.currentProject!.slug)

// The shared flag the "no mailbox connected" banner already uses, read the
// other way round. True here means this project can actually send.
const hasMailbox = computed(() => page.props.setup?.mailbox === false)

// Email off means no lead search, so the run ends at the segments: there is
// no mailbox to ask for and nothing to start.
const emailEnabled = computed(() => props.channels.find(channel => channel.key === 'email')?.enabled ?? true)

// Agreeing to the segments, which is a position within this screen rather than
// a fact about the project: there is no column for "has seen the mailbox step"
// and adding one would be a third thing able to disagree with the other two.
// Reloading simply puts the segments back in front of you, which is harmless.
const confirmedTargets = ref(false)

// The same form the Mailboxes settings screen uses, opened here instead of
// navigating to it: the run is two buttons from finishing and a settings page
// is a worse place to be standing when it does.
const connecting = ref(false)

// Set the moment a search is posted. Saving a mailbox satisfies the step it
// was asked on, so without this the screen drops back to the segments for as
// long as the search takes to be accepted.
const starting = ref(false)

// The failed read a retry was asked from. The new row only exists once a
// worker picks the job up, so until then this one is still the latest and
// would put the error straight back on screen.
const retryingFromAnalysisId = ref<number | null>(null)

// The three screens between the reading and the segments: what the site never
// said, what was understood, where to reach the buyers. A position within this
// screen, not a fact about the project, so a reload starts over at the
// questions, with the answers already given still in their boxes. Null until
// somebody moves, because the questions only exist once the reading is done.
type ProductStep = 'questions' | 'portrait' | 'channels'
const productStepChosen = ref<ProductStep | null>(null)
const productStep = computed<ProductStep>(() =>
    productStepChosen.value ?? (props.openQuestions.length > 0 ? 'questions' : 'portrait'))

// The portrait corrected in place, so the step it was corrected from is still
// the one on screen once it is saved.
const correcting = ref(false)

const answered = computed(() => props.openQuestions.filter(question => question.answer !== null))

// Where the run has actually got to. Derived rather than stored: every one of
// these is a fact about the project, and a column saying "step 3" would be one
// more thing able to disagree with reality.
const step = computed(() => {
    if (props.searches > 0 || starting.value) {
        return 'searching'
    }

    if (props.deriving) {
        return 'deriving'
    }

    if (props.profiles.length > 0) {
        // With email off the segments are the end of the run: nothing is
        // searched, so there is nothing left to confirm them for. Derived from
        // the project, not from a click, so the dashboard and a reload agree.
        if (!emailEnabled.value) {
            return 'ready'
        }

        // The mailbox is asked for between agreeing to the segments and the
        // first search, because the search is the step that spends credits and
        // the send is the step that pays them back. Ending the run on the
        // search means a trial can be spent in full without a mail ever
        // leaving, and the banner on every screen does not stop that.
        return confirmedTargets.value && !hasMailbox.value ? 'mailbox' : 'review_targets'
    }

    if (props.analysis?.status === 'failed' && props.analysis.id !== retryingFromAnalysisId.value) {
        return 'failed'
    }

    if (props.knowledgeBase && !props.analysis?.running) {
        return productStep.value
    }

    return 'analysing'
})

const working = computed(() => step.value === 'analysing' || step.value === 'deriving')

// Only while something is out. Nothing on this page changes on its own once the
// user is the one who has to act.
const poll = usePoll(3000, {}, { autoStart: working.value })

watch(working, busy => busy ? poll.start() : poll.stop())

// A clock for the waiting screen, ticking only while the segments are being
// worked out: "running for 40s" is what tells a slow model from a dead job.
const now = ref(Date.now())
let ticker: ReturnType<typeof setInterval> | undefined

watch(() => step.value === 'deriving', (waiting) => {
    clearInterval(ticker)
    ticker = waiting ? setInterval(() => now.value = Date.now(), 1000) : undefined
}, { immediate: true })

onBeforeUnmount(() => clearInterval(ticker))

const derivationSeconds = computed(() => props.derivation?.since
    ? Math.max(0, Math.round((now.value - Date.parse(props.derivation.since)) / 1000))
    : 0)

const derivationElapsed = computed(() => {
    const seconds = derivationSeconds.value

    return seconds < 60 ? `${seconds}s` : `${Math.floor(seconds / 60)}m ${seconds % 60}s`
})

// Still queued after a minute means nothing is working the queue: a worker
// picks a job up within seconds when one is running.
const derivationStuck = computed(() => props.derivation?.status === 'pending' && derivationSeconds.value > 60)

// A derivation that failed lands back on the channels, with the reason and a
// way to try again, rather than silently looking like nothing was clicked.
const derivationFailed = computed(() => props.derivation?.status === 'failed' && props.profiles.length === 0)

const derivingTargets = ref(false)

function deriveTargets () {
    derivingTargets.value = true
    router.post(targetRoutes.derive.url({ project: projectSlug.value }), {}, {
        preserveScroll: true,
        onFinish: () => derivingTargets.value = false
    })
}

// The whole run, in order. The mailbox and the search only exist while cold
// email is on: without it the run ends at the segments.
const stepperItems = computed(() => [
    { value: 'read', title: 'Reading your site', icon: 'i-lucide-file-search' },
    ...(props.openQuestions.length > 0 ? [{ value: 'questions', title: 'A few questions', icon: 'i-lucide-message-circle-question' }] : []),
    { value: 'portrait', title: 'Your product', icon: 'i-lucide-scan-text' },
    { value: 'channels', title: 'Channels', icon: 'i-lucide-radio-tower' },
    { value: 'targets', title: 'Who buys it', icon: 'i-lucide-users' },
    ...(emailEnabled.value
        ? [
                { value: 'mailbox', title: 'Your mailbox', icon: 'i-lucide-mail' },
                { value: 'search', title: 'Looking for them', icon: 'i-lucide-search' }
            ]
        : [{ value: 'ready', title: 'All set', icon: 'i-lucide-check' }])
])

const stepperValue = computed(() => ({
    analysing: 'read',
    failed: 'read',
    questions: 'questions',
    portrait: 'portrait',
    channels: 'channels',
    deriving: 'targets',
    review_targets: 'targets',
    mailbox: 'mailbox',
    ready: 'ready',
    searching: 'search'
}[step.value]))

const LISTS = ['key_features', 'competitors', 'proof_points'] as const

function startSearching () {
    starting.value = true
    router.post(searchRoutes.searches.url({ project: projectSlug.value }), {}, {
        preserveScroll: true,
        onError: () => starting.value = false
    })
}

function retryAnalysis () {
    retryingFromAnalysisId.value = props.analysis?.id ?? null
    router.post(searchRoutes.analysis.url({ project: projectSlug.value }), {}, {
        preserveScroll: true,
        onError: () => retryingFromAnalysisId.value = null
    })
}

/**
 * Agreeing to the segments. With a mailbox already attached this is what it
 * has always been, a single click that starts the search; without one the
 * mailbox step comes first, and both of its buttons start the search.
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

        <!-- The whole run, and where it has got to. Worth the space: the
             first stage takes a minute or two, and a spinner alone does not
             say what is being waited for. Not clickable: each stage moves on
             from its own buttons. -->
        <UStepper
            :items="stepperItems"
            :model-value="stepperValue"
            disabled
            size="sm"
            class="w-full"
        />

        <UAlert
            v-if="derivationFailed && (step === 'questions' || step === 'portrait' || step === 'channels')"
            color="error"
            variant="subtle"
            icon="i-lucide-triangle-alert"
            title="Working out who buys it failed"
            :description="derivation?.error ?? 'The agent stopped before it finished.'"
        >
            <template #actions>
                <UButton
                    icon="i-lucide-rotate-ccw"
                    label="Try again"
                    :loading="derivingTargets"
                    @click="deriveTargets"
                />
            </template>
        </UAlert>

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
                    icon="i-lucide-rotate-ccw"
                    label="Try again"
                    @click="retryAnalysis"
                />
                <UButton
                    color="neutral"
                    variant="subtle"
                    label="Project settings"
                    @click="router.get(knowledgeBaseRoutes.edit.url({ project: projectSlug }))"
                />
            </template>
        </UAlert>

        <!-- 2. What the site never said. Asked first: the answers feed the
             portrait the next screen shows and the segments derived after it. -->
        <div
            v-else-if="step === 'questions'"
            class="space-y-3"
        >
            <OpenQuestions
                :questions="openQuestions"
                title="A few things your site does not say"
                submit-label="Save and continue"
                @saved="productStepChosen = 'portrait'"
            >
                <template #actions>
                    <UButton
                        color="neutral"
                        variant="ghost"
                        label="Skip for now"
                        @click="productStepChosen = 'portrait'"
                    />
                </template>
            </OpenQuestions>
        </div>

        <!-- 3. What it understood. Corrected in a modal, so agreeing stays the
             only way forward. -->
        <div
            v-else-if="step === 'portrait'"
            class="space-y-4 rounded-lg p-4 ring ring-default"
        >
            <div>
                <p class="font-medium">
                    This is what Eveil understood
                </p>
                <p class="text-sm text-muted">
                    Every mail and post is written from this. Correcting it now
                    is cheaper than correcting it in three hundred mails.
                </p>
            </div>

            <dl class="space-y-3 text-sm">
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
                <!-- The answers are part of the portrait now: shown here so
                     what was just typed visibly landed somewhere. -->
                <div v-if="answered.length">
                    <dt class="text-dimmed">
                        What you told us
                    </dt>
                    <dd
                        v-for="question in answered"
                        :key="question.key"
                    >
                        <span class="text-muted">{{ question.question }}</span> {{ question.answer }}
                    </dd>
                </div>
            </dl>

            <div class="flex flex-wrap items-center gap-2">
                <UButton
                    icon="i-lucide-check"
                    label="That's right"
                    @click="productStepChosen = 'channels'"
                />
                <UButton
                    color="neutral"
                    variant="subtle"
                    icon="i-lucide-pencil"
                    label="Correct it first"
                    @click="correcting = true"
                />
                <UButton
                    v-if="openQuestions.length"
                    color="neutral"
                    variant="ghost"
                    label="Back to the questions"
                    @click="productStepChosen = 'questions'"
                />
            </div>
        </div>

        <!-- 4. Where to reach the buyers. Each card saves on its own click;
             the button starts the segments. -->
        <div
            v-else-if="step === 'channels'"
            class="space-y-4"
        >
            <div>
                <p class="font-medium">
                    Where to reach them
                </p>
                <p class="text-sm text-muted">
                    The channels that fit are switched on. Click a card to
                    change it, now or later from the project settings.
                </p>
            </div>

            <ChannelCards :channels="channels" />

            <div class="flex flex-wrap items-center gap-2">
                <UButton
                    icon="i-lucide-arrow-right"
                    trailing
                    label="Find who buys it"
                    :loading="derivingTargets"
                    @click="deriveTargets"
                />
                <UButton
                    color="neutral"
                    variant="ghost"
                    label="Back"
                    @click="productStepChosen = 'portrait'"
                />
            </div>
        </div>

        <div
            v-else-if="step === 'deriving'"
            class="space-y-3 rounded-lg p-4 ring ring-default"
        >
            <p class="font-medium">
                Working out who buys it
            </p>
            <p class="text-sm text-muted">
                Segments, and the search terms that find each one. This may take a minute or two.
            </p>

            <div class="flex items-center gap-2 text-sm">
                <UIcon
                    name="i-lucide-loader"
                    class="size-4 animate-spin text-primary"
                />
                <span v-if="derivation?.status === 'running'">
                    The agent is on it. Running for {{ derivationElapsed }}.
                </span>
                <span v-else>
                    Queued, waiting for a worker to pick it up ({{ derivationElapsed }}).
                </span>
            </div>

            <!-- Indeterminate: the model gives no progress to report, only
                 that it has not finished. -->
            <UProgress
                v-if="derivation?.status === 'running'"
                size="sm"
            />

            <UAlert
                v-if="derivationStuck"
                color="warning"
                variant="subtle"
                icon="i-lucide-clock-alert"
                title="Nothing has picked this up yet"
                description="A queue worker normally starts within seconds. Check that the queue is running (Horizon, or queue:work)."
            />
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
                     none, so this does not promise the search: it says what
                     it does, and the next screen is the one that starts it. -->
                <UButton
                    icon="i-lucide-check"
                    label="These look right"
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
                message from you. Pick your provider in the form and the host
                names and ports fill themselves in.
            </p>

            <!-- Both of these start the search. The mailbox is worth asking
                 for here, because searching and qualifying are what spend the
                 credits and sending is what they were spent for, but it is an
                 ask and not a gate. -->
            <div class="flex flex-wrap items-center gap-2">
                <UButton
                    icon="i-lucide-mail"
                    label="Connect a mailbox"
                    @click="connecting = true"
                />
                <UButton
                    color="neutral"
                    variant="ghost"
                    label="Skip for now"
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

        <!-- Done without a search: email is off, and the channels that are
             on run from their own schedule. -->
        <div
            v-else-if="step === 'ready'"
            class="space-y-3 rounded-lg p-4 ring ring-default"
        >
            <p class="font-medium">
                You are set up
            </p>
            <p class="text-sm text-muted">
                The channels you switched on draft on their own schedule, and
                each one has its queue in the sidebar. They write for these
                segments. Turning cold email on from the project settings
                starts the lead search.
            </p>

            <div class="flex flex-wrap gap-2">
                <UBadge
                    v-for="profile in profiles"
                    :key="profile.id"
                    color="neutral"
                    variant="subtle"
                    :label="profile.name"
                />
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <UButton
                    label="Go to the dashboard"
                    @click="router.get(dashboard.url({ project: projectSlug }))"
                />
                <UButton
                    color="neutral"
                    variant="ghost"
                    label="Edit the segments"
                    @click="router.get(targetRoutes.index.url({ project: projectSlug }))"
                />
            </div>
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

        <!-- Outside the step it belongs to: saving moves the run on, and a
             modal nested in the panel that is about to be replaced would be
             torn down before its own success callback ran. The project
             waiting on the mailbox is ticked, otherwise this saves one the
             campaign still may not send through. -->
        <!-- Outside the step for the same reason as the mailbox form below:
             saving re-renders the page, and the step it was opened from has to
             still be there when it does. -->
        <UModal
            v-model:open="correcting"
            title="Correct the portrait"
            :ui="{ content: 'max-w-2xl' }"
        >
            <template #body>
                <KnowledgeBaseForm
                    :knowledge-base="knowledgeBase"
                    @saved="correcting = false"
                >
                    <template #actions>
                        <UButton
                            color="neutral"
                            variant="ghost"
                            label="Cancel"
                            @click="correcting = false"
                        />
                    </template>
                </KnowledgeBaseForm>
            </template>
        </UModal>

        <MailboxForm
            v-model:open="connecting"
            :projects="projects"
            :default-projects="page.props.currentProject ? [page.props.currentProject.id] : []"
            @saved="startSearching"
        />
    </div>
</template>
