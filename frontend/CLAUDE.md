# CLAUDE.md (frontend)

Frontend-specific conventions for `frontend/` (Vue 3 + TypeScript + Vite public site). See root `CLAUDE.md` for Docker/monorepo-wide rules, `backend/CLAUDE.md` for the Laravel side.

## Current state

Minimal scaffold: `App.vue`, `router/index.ts` (one `home` route), `views/Home.vue`, `assets/scss/variables.scss`. Only `axios`, `pinia`, `vue`, `vue-router` are installed (`package.json`) — no form-validation library, no toast/notification library, no component kit. Don't reference a package that isn't in `package.json`.

## Structure to grow into

```
src/
  assets/scss/       ← global styles only
    variables.scss   ← design tokens, auto-injected into every component (see vite.config.ts's additionalData)
  components/
    layout/           ← AppHeader, AppFooter, Layout
    ui/base/          ← generic reusable pieces (BaseButton, BaseInput, ...)
    ui/<domain>/       ← feature-specific but still reusable components
  composables/        ← shared reactive logic extracted out of components
  views/              ← route-level components — assemble components only, no UI logic of their own
  stores/              ← Pinia stores — see rule below
  types/               ← shared TS interfaces, grouped by domain file, not per-component
  router/index.ts      ← Vue Router, history mode
public/                ← static assets served as-is (Vite can't resolve dynamic src/assets paths, so anything referenced from JS data goes here)
```

Vite alias `@` → `src/`, `@public` → `public/` (`vite.config.ts`).

## SCSS

`vite.config.ts`'s `additionalData` auto-injects `@use "@/assets/scss/variables" as *;` into every component — **never redeclare a variable from `variables.scss` inside a component's `<style>`**, it's already in scope. Component styles: `<style lang="scss" scoped>`.

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
