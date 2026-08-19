# CLAUDE.md (frontend)

Frontend-specific conventions for `frontend/` (Vue 3 + TypeScript + Vite public site). See root `CLAUDE.md` for Docker/monorepo-wide rules, `backend/CLAUDE.md` for the Laravel side.

## Current state

Styling is Tailwind CSS 4 (`@tailwindcss/vite`, entry at `src/assets/css/main.css`) — new components use Tailwind utility classes directly in the template, not SCSS. The old `assets/scss/variables.scss` design-token setup still exists for legacy pieces but isn't the convention for new work. Installed beyond the original `axios`/`pinia`/`vue`/`vue-router`: `vee-validate` + `yup` (forms), `@tiptap/*` incl. `@tiptap/extension-code-block` (rich text editing — see below), `vue-advanced-cropper` (image crop). Don't reference a package that isn't in `package.json`.

The comments feature (`components/ui/comments/`) is now a full read+write loop against the real API, not just a submission form: `CommentForm.vue` (also reused inline as the reply-compose box, via a `variant` prop), `list/CommentList.vue` + `CommentItem.vue` (recursive — a reply never renders its own further-nested replies, only a flat list under its top-level parent) + `CommentSortBar.vue`, plus `components/ui/base/BasePagination.vue` and `ExternalLinkConfirm.vue` — the latter is wired **once, app-wide, in `App.vue`** (a single `document`-level click listener intercepting any cross-origin `http(s)` link click, anywhere), not per-feature. A new feature with outbound links doesn't need its own interception — it's already covered. `views/Home.vue` owns the actual `api.get('comments', ...)` fetch and reacts to `route.query` changes (see the query-param pattern below) — see it as the current working example of "a page that owns page-scoped fetch + calls it whenever the URL's own state changes."

**Every `<button>`/clickable element needs an explicit `cursor-pointer` class.** Tailwind's preflight does not default `<button>` to a pointer cursor — this bit multiple components (form buttons, sort pills, editor toolbar, comment action buttons) before it was caught. `BaseButton` has it on its root so anything routed through that component is covered for free; hand-rolled `<button>`s (toolbars, pill controls, icon buttons) each need it added explicitly.

## Structure

```
src/
  assets/css/main.css   ← Tailwind entry (@import "tailwindcss";)
  components/
    ui/base/             ← generic reusable pieces (BaseButton, BaseInput, ...)
    ui/<domain>/          ← feature-specific components, e.g. ui/comments/
  stores/                 ← Pinia stores — see rule below
  views/                  ← route-level components — assemble components only, no UI logic of their own
  router/index.ts         ← Vue Router, history mode
public/                   ← static assets served as-is (Vite can't resolve dynamic src/assets paths, so anything referenced from JS data goes here)
```

Vite alias `@` → `src/`, `@public` → `public/` (`vite.config.ts`).

**A self-contained feature with several moving parts gets its own subfolder, not one giant component.** `ui/base/editor/` holds `RichTextEditor.vue` (toolbar + TipTap instance) and `LinkPopover.vue` (the link insert/edit modal) as siblings; `ui/base/imageUpload/` holds `ImageUpload.vue` and `CropModal.vue` the same way. The split point is usually a modal/popover — pull it into its own component exposing an `open(...)` method via `defineExpose`, called from the parent through a `ref`, emitting results back up (`@cropped`, `@cancelled`) rather than the parent reaching into the child's internal state.

**`RichTextEditor.vue`/`LinkPopover.vue`/`ImageUpload.vue`/`CropModal.vue`/`ImageLightbox.vue` all live under `ui/base/`, not `ui/comments/`** — none of "pick/crop/view an image" or "format bold/italic/code/a link" is comment-specific, even though the comment form is currently their only caller. **Caveat if `RichTextEditor.vue` gets a second caller:** its TipTap extension config (which marks exist, which tag each renders as — `<strong>`/`<i>`/bare `<code>`/`<a>`) is currently hand-tuned to match `CommentService::sanitizeBody()`'s exact allowed-tag whitelist one-for-one. A new caller with a *different* backend sanitizer/allowed-tag set needs to either match that same whitelist or extend the editor's config — don't assume the current toolset is a safe default for content going through a different sanitizer.

## Forms — vee-validate + yup

Use `useForm()` (not the `<Form>` component) when the parent needs live reactive access to field values — e.g. to mirror a field into a Pinia store as the user types (see `stores/author.ts` + `CommentForm.vue`: a `watch(() => values.user_name, ...)` syncs into the store on every change, and the store's own `watch` persists to `localStorage`). The `<Form>` component only gives child components access to the form context, not the parent's own `<script setup>`.

Toolbar-style buttons (bold/italic/link/...) should be a data-driven array (`{ key, title, active, run }`) rendered with `v-for`, not one hand-written `<button>` per action — see `RichTextEditor.vue`'s `toolbar_buttons` computed.

## Rich text editing — use TipTap, not a hand-rolled contenteditable

`@tiptap/vue-3` (+ `starter-kit`, `pm`, and per-mark extensions) is the answer for anything beyond a plain `<input>`/`<textarea>`. A hand-rolled `contenteditable` + native `Selection`/`Range` API was tried first for the comment editor and hit a wall of real bugs (toolbar buttons losing the Range on click, ambiguous caret placement at empty-element boundaries, links "growing" when typing at their edge) that TipTap's ProseMirror foundation already solves. If a mark needs to render as a specific tag TipTap doesn't default to (e.g. `<i>` instead of `<em>`), extend the base extension and override `parseHTML`/`renderHTML` rather than reaching for something else. `insertContent()` given a plain string is parsed as HTML — pass `{ type: 'text', text }` explicitly when the content must stay 100% literal (e.g. a code block whose contents might itself contain `<i>`-looking text).

**A plain paragraph node does not reliably preserve multiple consecutive spaces the user types — a `codeBlock` node does, because of `whitespace: 'pre'` in its ProseMirror node spec, not because of the `<pre>` tag it happens to render as.** The comment editor's code button used to insert `[code]...[/code]` text markers into a normal paragraph as a workaround — that silently collapsed indentation/spacing on every code snippet. Fixed by using TipTap's real `@tiptap/extension-code-block` instead. If the allowed-tags whitelist can't include `<pre>` (ours can't — see sanitization below), extend `CodeBlock` and override just `parseHTML`/`renderHTML` (with `preserveWhitespace: 'full'` kept in the parse rule) to serialize as bare `<code>` — the node's whitespace-preserving behavior comes from the node spec, not the tag, so it survives the swap.

## API calls — `plugins/axios.ts`

An `axios.create({ baseURL: import.meta.env.VITE_API_URL, headers: { Accept, X-Requested-With } })` instance, plus `extract_error_message(error)` for pulling a display string out of a Laravel validation error response. Ported from the reference's own `plugins/axios.ts`, minus what doesn't apply here: no CSRF/`XSRF-TOKEN` cookie interceptor (that's Sanctum stateful-auth machinery — this backend has no Sanctum and every comment endpoint is public/guest), no toast-on-error side effect (`vue-toastification` isn't installed — surface errors via the calling component's own error state instead, e.g. `CommentForm.vue`'s `submit_error` ref).

## Query-param-driven sort/filter/pagination

For any list view where sort/filter/page should survive a reload and be shareable via URL, use `composables/useQueryPatch.ts` (`patch_query(patch, { reset_page?, order? })`, ported from the reference's products-listing page): merges `patch` into `route.query`, defaults to clearing `page` (any sort/filter change implicitly resets pagination — pass `reset_page: false` when the patch *is* the page change itself), and rewrites the query in a given key `order` when provided so the URL's parameter order stays deterministic regardless of *which* control was touched first.

**Order is only enforced when sort/filter state changes, not on every pagination click** — matches the reference. `CommentSortBar.vue` passes `order: ['sort_by', 'page']` when it patches `sort_by`; `BasePagination.vue` does **not** take an `order` prop at all and patches `page` with no `order` option — `page` is only ever appended after `sort_by` is already present, so it naturally stays last without re-sorting the whole query on every click.

**Each control that changes a slice of the URL owns calling `patch_query` itself** — `CommentSortBar.vue` reads/writes `route.query.sort_by` directly, `BasePagination.vue` reads/writes `route.query.page` directly. Neither goes through a prop/emit chain up to the page component for this. The page component (`Home.vue`) only owns one thing: `watch(() => route.query, fetch_comments, { immediate: true, deep: true })` — any URL change refetches, full stop. This is a deliberate architecture choice (matches the reference), not the more manual "parent owns local sort/page refs and passes down `@sort-change`/`@page-change` handlers" pattern that was tried first here and got replaced.

**Sort UI data lives in `types/sort.ts`, not a comment-namespaced type** — `SortOption<TValue>` (`{ title, selects: readonly [TValue, TValue] }`), `SORT_FIELD_OPTIONS`, and the derived `SortFieldKey`/`SortKey`/`SORT_VALUES` (typed off `SORT_FIELD_OPTIONS` itself via `keyof typeof`/indexed access, never hand-duplicated as a separate literal union). Sorting is the same problem for any future sortable list, so the options table and its derived types are generic infrastructure even though comments are currently the only caller. `ui/base/SortButton.vue` is a plain toggle button — given `title`/`selects`/`value` it works out `is_active`/`target_value` itself — so `CommentSortBar.vue` is just a `v-for` over `Object.keys(SORT_FIELD_OPTIONS)`.

**Sanitizing the query is split by what actually needs guarding, not lumped into one function.** `Home.vue`'s `sanitize_sort_by()` runs **before** the fetch and only checks `sort_by`, because the backend hard-validates it (`Rule::in`) and an invalid value would 422 the request outright. `sanitize_page()` runs **after** the fetch resolves and only checks that `page` is numeric — Laravel's paginator degrades an out-of-range or malformed `page` gracefully (clamps to page 1 internally), so there's no risk in sending it first. **There is no "clamp `page` down to `last_page`" logic anywhere** — a URL requesting a page beyond the real range is sent as-is and the (empty) result is shown as requested, matching the reference's `ProductList.vue`. `CommentList.vue` keeps `BasePagination` visible independent of whether the current page's item list is empty (only `lastPage > 1` from the real server response controls whether the nav renders) — don't couple pagination visibility to `comments.length`, or an out-of-range page silently loses its own page-jump controls.

**`Home.vue` keeps pagination as one `pagination = ref<Pagination>({ current_page, last_page, total })`** (see `types/comment.ts`'s `Pagination` interface, matching the backend's `RespondTrait::paginationMeta()` shape 1:1) — not separate `current_page`/`last_page` refs — assigned wholesale from the response (`pagination.value = items.pagination`), mirroring the reference's `ProductList.vue`.

## Comment HTML sanitization

`utils/sanitizeCommentHtml.ts` — DOM-based (real element/attribute tree-walk via a `<template>`, not regex) whitelist: only `a`/`code`/`i`/`strong` survive, everything else is unwrapped (children kept, tag dropped) with a `\n` text node inserted at the unwrap point for block-level tags (`p`/`br`/`div`) so paragraph breaks aren't lost — pair with `white-space: pre-line` on the rendering container, or the inserted `\n` renders as nothing. `<a>` gets `rel`/`target` force-set regardless of input. **This is a client-side defense-in-depth layer only — the backend (`CommentService::sanitizeBody`, see `backend/CLAUDE.md`) is the real sanitization boundary and uses a different technique (`strip_tags` + regex, not DOM parsing).** The two must stay in sync on the allowed-tag set; they are not shared code, just parallel implementations of the same rule.

## Component rules

- Reusable components live under `src/components/ui/` — never write a component tied to one specific view.
- Views only assemble components — no inline styles, no UI logic of their own.
- **Never hand-roll a one-off element that duplicates what an existing base component already does** (a plain `<button>` styled as a CTA when a `BaseButton` exists, a tab-row instead of `BaseTabs`, etc.) — extend the existing component with a new variant/prop instead of copying its look into a new place. This matters because a hand-rolled duplicate is invisible until a design change means finding every copy by hand. `BaseButton`'s `variant` prop currently covers `primary`/`outline`/`text`/`chip`/`accent` — add a new variant here rather than one-off classes on a caller before assuming a look doesn't fit the existing set.

## Pinia stores

**A store is only for data genuinely global across pages/components** (e.g. cart count, logged-in user, site-wide layout data fetched once) — data many unrelated components need without a prop chain, that shouldn't be re-fetched on every mount. **Data scoped to a single page is not a store**, even if several sibling components need it — fetch it once in the page's view component and pass it down via props. Use the Composition API form for stores (`defineStore('name', () => { ... return {...} })` with `ref`/`computed`), not the Options API form.

Shared TS interfaces for page-fetched data shapes go in a domain-named file under `types/` (not re-declared per component, and not smuggled into a store just to have an export point). A component's own `Props` interface is the one exception that stays local to the `.vue` file.

## Routing — always use named routes, never hardcoded path strings

Every internal link/navigation goes through the router's route **names** (`{ name: 'home' }`, `{ name: 'product', params: { slug } }`), never a literal path string — `router/index.ts`'s own route *definitions* (`path: '...'`) are the one legitimate place a path string still appears. A component that links to a resource by id/slug should compute its own route target internally from that prop, not accept a raw `href`/path from its caller.

## Loading states

Any component that fetches its own data asynchronously should ship a loading state in the same change it's added in — not as a follow-up once someone notices content jumping. Prefer a `loading?: boolean` prop on the component that already renders that data shape over a separate parallel `XSkeleton.vue` file, so a future change to the real markup can't silently desync from its skeleton.
