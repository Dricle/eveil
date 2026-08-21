---
paths:
  - 'resources/js/**'
---

# Js

## Sidebar is the pipeline, and it stops at five entries
The main nav follows the order work flows, not the data model: Dashboard (the run feed. What the app is doing now), Targets (profiles + discovery runs), Leads (companies with fit score, contacts, emails), Campaigns (sequences, sending, caps), Inbox (replies, auto-pause). Account and Settings hang off the user menu at the bottom.

Every other screen is a tab or a drill-down inside one of those five: never a sixth line. CSV import is a button on Leads, the lead sheet is a drill-down, the sequence editor is inside Campaigns.

Settings holds only what you set once and forget: project name/URL, the knowledge base, mailboxes, suppression and retention. Anything reread before each run belongs in the nav: that is why target profiles moved out of `/app/settings/`. Instance settings (AI models, host registry, registration) are a separate superadmin section, never mixed into project settings; organization members and billing are a third scope again.

## The app bar stays empty, and sections navigate in their own content
`AppLayout`'s header bar holds the sidebar toggle and nothing else. It is reserved for app-wide things still to come: a search field, a notification bell, so no page or layout puts a title, tabs or actions in the `#header` slot. Section navigation and page titles live in the content area (`<h2>`, a `UNavigationMenu` at the top of the panel, or an aside).

Targets is navigated BY its profiles, not by tabs: `/app/targets` redirects to the first profile (or renders `targets/Empty` with a derive button when there are none), the aside lists every profile as a link, and "New profile" plus "Derive again" sit at the BOTTOM of that list. Opening the section is normally to read a profile, not to rewrite them all. Each profile then has two pages of its own, `targets/Profile` and `targets/Searches`, switched by tabs inside the content.

A discovery run belongs to the profile that asked for it (`/app/targets/{id}/searches`), never to a project-wide list: a run means nothing without the criteria it was given. The profile list, the deriving flag and the last derivation error are shared by the `targets.share` middleware, so every page under the section has them without repeating the query.

## Never name a route import after a prop, and never type props with an imported alias
Two silent failures, both cost an afternoon on the Companies page:

1. In `<script setup>`, template expressions resolve setup bindings BEFORE props. `import companies from '@/routes/companies'` next to a `companies` prop means `companies.data` in the template reads the Wayfinder module, not the prop: no warning, just `undefined`. Wayfinder module names match resource prop names by nature, so suffix the import: `companyRoutes`, `contactRoutes`.

2. `defineProps<SomeAlias>()` where the alias is imported through the `@/types` barrel declares NO props at all: the compiler cannot resolve it and fails silently. Write the prop object inline; the member types (`Company`, `Paginated<Contact>`) can still be imported.

Neither is caught by eslint, `tsc`, the Vite build, or a Pest feature test (the server sends the props fine). To check a page compiled its props, run `@vue/compiler-sfc`'s `compileScript()` on it and look at the emitted `props:` object.

## A select option never carries an empty-string value
Reka (under Nuxt UI's `USelect`) reserves `''` for clearing a selection, so a `SelectItem` whose value is `''` throws on mount: "A <SelectItem /> must have a value prop that is not an empty string". An "everything / no filter" option therefore uses a sentinel: `'all'`, or `0` for numeric filters, and the page maps it to `undefined` when building the query string so the parameter is simply absent.

## Instance settings are their own section, and `status` is the one flash prop
App settings (instance scope) live at `/app/app-settings/*` behind `can:manage-app-settings` (`users.is_super_admin`), with `AppSettingsLayout.vue` as their aside: AI provider key, per-agent model mapping, tunable limits, host registry. The entry sits in the user menu and only renders for a superadmin: it is a third permission scope, never granted through an organization, so it must not be mixed into project settings (`SettingsLayout.vue`).

`HandleInertiaRequests` shares a single `status` prop (a flashed sentence) for actions whose result is invisible on the page they redirect to: a saved key, a provider that answered. `InstanceLayout` renders it. Failures use `withErrors()` instead, so the existing form fields show them.

## Never run wayfinder:generate inside the Sail container
Generate `resources/js/actions` and `resources/js/routes` from the HOST (`yarn build` / `yarn dev` shell out to `php artisan wayfinder:generate` with host PHP). Running `php artisan wayfinder:generate` inside the `laravel.test` container emits route modules WITHOUT the `.form()` helper, and every page using `v-bind="someRoute.update.form()"` then fails `yarn types:check` with "Property 'form' does not exist". A dozen errors in files you never touched, in a diff that looks unrelated.

The fix if it happens: run `yarn build` on the host, which regenerates them correctly.

## wayfinder:generate needs --with-form, even on the host
`php artisan wayfinder:generate` on its own emits route modules WITHOUT `.form()`, on the host as much as in the container: the `formVariants: true` option lives in the vite plugin config, and the bare command never sees it. Every page doing `v-bind="someRoute.update.form()"` then fails `yarn types:check` with "Property 'form' does not exist", in files you never touched.

Run `php artisan wayfinder:generate --with-form` when generating by hand, or just `yarn build` on the host, which passes it. Check with: `php -r 'echo substr_count(file_get_contents("resources/js/routes/settings/knowledge-base/index.ts"), ".form");'` — zero means regenerate.

## Never leave a form field on `default-value`: bind it with v-model
Nuxt UI reads `defaultValue` ONCE. `Textarea.vue` and `Input.vue` do `useVModel(props, 'modelValue', emits, { defaultValue: props.defaultValue })` without `passive`, so the value is captured at mount and never tracks the prop again. Vue then patches a form element's `value` against what the DOM currently holds rather than against the previous vnode, so **every re-render writes that frozen first value back over whatever the user typed**.

What it looks like: answer three questions on the onboarding, save, and the boxes come back empty while the counter above them says "3 of 3 answered". The data was saved correctly; the display overwrote itself. Any `back()`/redirect that re-renders the same component does it, and a `usePoll` on the page does it every few seconds while somebody is typing.

The fix is a local draft synced from props, which is what `app-settings/Agents.vue` already does:

    const draft = ref<Record<string, string>>({})
    watch(() => props.questions, questions => {
        draft.value = Object.fromEntries(questions.map(q => [q.key, q.answer ?? '']))
    }, { immediate: true, deep: true })

then `v-model="draft[q.key]"`. Inertia's `<Form>` still collects by input `name`, so nothing else changes. Do NOT "fix" it with a changing `:key`: that remounts the field and loses focus and caret mid-typing.

Still on `default-value` and due the same treatment: `settings/KnowledgeBase.vue`, `settings/Project.vue`, `targets/Profile.vue`, `app-settings/Limits.vue`, `account/Profile.vue`. `auth/ResetPassword.vue` is safe, nothing re-renders it.
