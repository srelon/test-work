# CLAUDE.md (frontend)

Frontend-specific conventions for `frontend/` (Vue 3 + TypeScript + Vite public site). See root `CLAUDE.md` for Docker/monorepo-wide rules, `backend/CLAUDE.md` for the Laravel side.

## Current state

Styling is Tailwind CSS 4 (`@tailwindcss/vite`, entry at `src/assets/css/main.css`) — new components use Tailwind utility classes directly in the template, not SCSS. The old `assets/scss/variables.scss` design-token setup still exists for legacy pieces but isn't the convention for new work. Installed beyond the original `axios`/`pinia`/`vue`/`vue-router`: `vee-validate` + `yup` (forms), `@tiptap/*` (rich text editing — see below), `vue-advanced-cropper` (image crop). Don't reference a package that isn't in `package.json`.

The one real feature built so far is the comment form (`components/ui/comments/`) — see its structure below as the working example of the conventions in this file.

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

**A self-contained feature with several moving parts gets its own subfolder, not one giant component.** `ui/comments/editor/` holds `CommentEditor.vue` (toolbar + TipTap instance) and `LinkPopover.vue` (the link insert/edit modal) as siblings; `ui/comments/imageUpload/` holds `ImageUpload.vue` and `CropModal.vue` the same way. The split point is usually a modal/popover — pull it into its own component exposing an `open(...)` method via `defineExpose`, called from the parent through a `ref`, emitting results back up (`@cropped`, `@cancelled`) rather than the parent reaching into the child's internal state.

## Forms — vee-validate + yup

Use `useForm()` (not the `<Form>` component) when the parent needs live reactive access to field values — e.g. to mirror a field into a Pinia store as the user types (see `stores/author.ts` + `CommentForm.vue`: a `watch(() => values.user_name, ...)` syncs into the store on every change, and the store's own `watch` persists to `localStorage`). The `<Form>` component only gives child components access to the form context, not the parent's own `<script setup>`.

Toolbar-style buttons (bold/italic/link/...) should be a data-driven array (`{ key, title, active, run }`) rendered with `v-for`, not one hand-written `<button>` per action — see `CommentEditor.vue`'s `toolbar_buttons` computed.

## Rich text editing — use TipTap, not a hand-rolled contenteditable

`@tiptap/vue-3` (+ `starter-kit`, `pm`, and per-mark extensions) is the answer for anything beyond a plain `<input>`/`<textarea>`. A hand-rolled `contenteditable` + native `Selection`/`Range` API was tried first for the comment editor and hit a wall of real bugs (toolbar buttons losing the Range on click, ambiguous caret placement at empty-element boundaries, links "growing" when typing at their edge) that TipTap's ProseMirror foundation already solves. If a mark needs to render as a specific tag TipTap doesn't default to (e.g. `<i>` instead of `<em>`), extend the base extension and override `parseHTML`/`renderHTML` rather than reaching for something else. `insertContent()` given a plain string is parsed as HTML — pass `{ type: 'text', text }` explicitly when the content must stay 100% literal (e.g. a code block whose contents might itself contain `<i>`-looking text).

## Component rules

- Reusable components live under `src/components/ui/` — never write a component tied to one specific view.
- Views only assemble components — no inline styles, no UI logic of their own.
- **Never hand-roll a one-off element that duplicates what an existing base component already does** (a plain `<button>` styled as a CTA when a `BaseButton` exists, a tab-row instead of `BaseTabs`, etc.) — extend the existing component with a new variant/prop instead of copying its look into a new place. This matters because a hand-rolled duplicate is invisible until a design change means finding every copy by hand.

## Pinia stores

**A store is only for data genuinely global across pages/components** (e.g. cart count, logged-in user, site-wide layout data fetched once) — data many unrelated components need without a prop chain, that shouldn't be re-fetched on every mount. **Data scoped to a single page is not a store**, even if several sibling components need it — fetch it once in the page's view component and pass it down via props. Use the Composition API form for stores (`defineStore('name', () => { ... return {...} })` with `ref`/`computed`), not the Options API form.

Shared TS interfaces for page-fetched data shapes go in a domain-named file under `types/` (not re-declared per component, and not smuggled into a store just to have an export point). A component's own `Props` interface is the one exception that stays local to the `.vue` file.

## Routing — always use named routes, never hardcoded path strings

Every internal link/navigation goes through the router's route **names** (`{ name: 'home' }`, `{ name: 'product', params: { slug } }`), never a literal path string — `router/index.ts`'s own route *definitions* (`path: '...'`) are the one legitimate place a path string still appears. A component that links to a resource by id/slug should compute its own route target internally from that prop, not accept a raw `href`/path from its caller.

## Loading states

Any component that fetches its own data asynchronously should ship a loading state in the same change it's added in — not as a follow-up once someone notices content jumping. Prefer a `loading?: boolean` prop on the component that already renders that data shape over a separate parallel `XSkeleton.vue` file, so a future change to the real markup can't silently desync from its skeleton.
