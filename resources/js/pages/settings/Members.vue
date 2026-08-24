<script setup lang="ts">
import { Form, Head, router, usePage } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import SettingsLayout from '@/layouts/SettingsLayout.vue'
import memberRoutes from '@/routes/settings/members'
import type { Member, Project } from '@/types'

const props = defineProps<{
    members: Member[]
    projects: Project[]
}>()

const ROLES = [
    { label: 'Admin', value: 'admin' },
    { label: 'Member', value: 'member' }
]

const page = usePage()
const inviting = ref(false)

// Controlled rather than `default-value`: Nuxt UI reads that prop once at
// mount, so a later re-render (this modal reopening) would not restore it.
const inviteRole = ref('member')

// One draft per member row, so editing one role's select does not touch what
// another row is showing while its own save is in flight. Synced on every
// prop change, not just at mount: a re-render (this page reloading after any
// row's own save) would otherwise write the frozen first value back over
// what another row currently shows.
function draftsFrom (members: Member[]): Record<number, { role: string, projects: number[] }> {
    return Object.fromEntries(members.map(member => [member.id, { role: member.role, projects: member.projects }]))
}

const drafts = ref(draftsFrom(props.members))

watch(() => props.members, (members) => {
    drafts.value = draftsFrom(members)
})

function removeMember (member: Member) {
    router.delete(memberRoutes.destroy.url(member.id), { preserveScroll: true })
}
</script>

<template>
    <SettingsLayout title="Members">
        <Head title="Members" />

        <div class="max-w-3xl space-y-6">
            <p class="text-sm text-muted">
                Owner and Admin see every project in this organization.
                A Member sees only the projects ticked below for them, and a
                brand new one starts with none: the safe default for someone
                still being set up.
            </p>

            <!-- The one refusal `RemoveMember` can produce (the last owner)
                 has no per-row form to surface it in: `destroy` is a plain
                 delete, not a Form component with its own error slot. -->
            <UAlert
                v-if="page.props.errors?.member"
                color="error"
                variant="subtle"
                icon="i-lucide-triangle-alert"
                :description="String(page.props.errors.member)"
            />

            <div class="space-y-2">
                <div
                    v-for="member in members"
                    :key="member.id"
                    class="space-y-2 rounded-lg p-4 ring ring-default"
                >
                    <Form
                        v-slot="{ errors, processing }"
                        v-bind="memberRoutes.update.form(member.id)"
                        class="space-y-2"
                    >
                        <div class="flex flex-wrap items-center gap-3">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">
                                    {{ member.name }}
                                    <span
                                        v-if="member.is_you"
                                        class="text-sm text-muted"
                                    >(you)</span>
                                </p>
                                <p class="text-sm text-muted">
                                    {{ member.email }}
                                </p>
                            </div>

                            <template v-if="member.role === 'owner'">
                                <UBadge
                                    color="neutral"
                                    variant="subtle"
                                    label="Owner"
                                />
                            </template>
                            <USelect
                                v-else
                                v-model="drafts[member.id].role"
                                name="role"
                                :items="ROLES"
                                class="w-40"
                            />

                            <UButton
                                v-if="member.role !== 'owner'"
                                type="submit"
                                size="xs"
                                :loading="processing"
                                label="Save"
                            />

                            <UButton
                                color="error"
                                variant="ghost"
                                size="xs"
                                :label="member.is_you ? 'Leave' : 'Remove'"
                                @click="removeMember(member)"
                            />
                        </div>

                        <p
                            v-if="errors.role"
                            class="text-sm text-error"
                        >
                            {{ errors.role }}
                        </p>

                        <!-- Only a Member is restricted by the grant, so only
                             this role gets a picker: showing it for Admin
                             would offer a control that changes nothing. -->
                        <div
                            v-if="drafts[member.id].role === 'member'"
                            class="pt-1"
                        >
                            <UCheckboxGroup
                                v-model="drafts[member.id].projects"
                                :items="projects"
                                value-key="id"
                                label-key="name"
                            />
                            <input
                                v-for="id in drafts[member.id].projects"
                                :key="id"
                                type="hidden"
                                name="projects[]"
                                :value="id"
                            >
                        </div>
                    </Form>
                </div>

                <p
                    v-if="!members.length"
                    class="rounded-lg p-6 text-sm text-muted ring ring-default"
                >
                    No member yet.
                </p>
            </div>

            <UButton
                icon="i-lucide-user-plus"
                label="Invite somebody"
                @click="inviting = true"
            />
        </div>

        <UModal
            v-model:open="inviting"
            title="Invite somebody"
        >
            <template #body>
                <Form
                    v-slot="{ errors, processing }"
                    v-bind="memberRoutes.store.form()"
                    class="space-y-4"
                    @success="inviting = false"
                >
                    <UFormField
                        label="Email"
                        name="email"
                        :error="errors.email"
                    >
                        <UInput
                            name="email"
                            type="email"
                            autofocus
                            required
                            class="w-full"
                        />
                    </UFormField>

                    <UFormField
                        label="Role"
                        name="role"
                        :error="errors.role"
                    >
                        <USelect
                            v-model="inviteRole"
                            name="role"
                            :items="ROLES"
                            class="w-full"
                        />
                    </UFormField>

                    <UButton
                        type="submit"
                        :loading="processing"
                        label="Send invitation"
                    />
                </Form>
            </template>
        </UModal>
    </SettingsLayout>
</template>
