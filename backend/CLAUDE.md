# CLAUDE.md (backend)

Backend-specific conventions for `backend/` (Laravel 12, API only — no admin panel, no Livewire). See the root `CLAUDE.md` for Docker/monorepo-wide rules, `frontend/CLAUDE.md` for the Vue app.

## Current state

Still only the framework itself + `predis/predis` in `composer.json` — no Sanctum, no queue driver beyond `database`. Don't reference packages that aren't in `composer.json` — check before assuming something (Sanctum, Livewire, a specific cache/queue driver) is available.

`routes/api.php` now exists and is registered via `api:` in `bootstrap/app.php`'s `withRouting(...)`. First (and so far only) resource: `comments` — `Comment` model + migration (self-referencing `parent_id`, plus a bare `replied_to_comment_id` with no FK, matching the reference's threaded-reply pattern), `CommentController` (`index`/`store` only — no `update`/`destroy`, since comments are anonymous/guest with no auth to gate ownership), `CommentService`, `CommentResource`, `CommentRequest` + `CommentFilterRequest` (validates/defaults `sort_by`). `App\Http\Controllers\Controller` now `use`s `App\Traits\RespondTrait` (ported from the reference) — every controller response goes through `respondWithJson`/`respondWithError`, never a hand-built array.

**Guest-submitted image uploads (base64 data URLs) go through `App\Traits\SavesBase64Images`** (alongside `App\Traits\RespondTrait` — this is where all cross-cutting traits live, not a per-namespace `Concerns` folder). Never trust the client's declared MIME type or file extension — decode via GD's `imagecreatefromstring()` and reject (`ValidationException`) anything that fails to decode as a real image, then always re-encode to a fresh PNG before storing (strips any non-image payload disguised with a fake `data:image/png;base64,` prefix, e.g. a PHP webshell). Uses plain `php-gd` (already in `_docker/app/Dockerfile`), not Imagick — Imagick isn't installed here or in the reference, and adding it means a Dockerfile/image-rebuild change; ask before reaching for it. `saveBase64ImageFit(...)` scales down only if over given max dimensions (never upscales, preserves aspect ratio); `saveBase64ImageCover(...)` resizes to an exact target size (may distort if the source aspect ratio differs — there is no crop-to-avoid-distortion step, that was deliberately simplified away, see [[feedback-reuse-reference-patterns]]).

**HTML sanitization for user-submitted rich text is a strict tag whitelist (`a`, `code`, `i`, `strong`) enforced on the backend** (`CommentService::sanitizeBody()`, plain `strip_tags()` + regex, not `DOMDocument` — kept intentionally simple, see [[feedback-reuse-reference-patterns]]) **and mirrored on the frontend** (`utils/sanitizeCommentHtml.ts`) for defense in depth. If the allowed tag set ever changes, update both sides together — they're independent implementations of the same whitelist, not shared code. `<a>` gets `rel="nofollow noindex"` force-injected server-side regardless of what the client sent. Regex tag-matching for `<p>` must use a lookahead (`(?=[\s>\/])`) after the tag name — a naive `<\/?p[^>]*>` also matches `<pre>`/`</pre>` since `[^>]*` swallows the "re".

## Running commands in the backend container

The container WORKDIR is `/var/www` — always `cd /var/www/backend` first.

```bash
docker exec -it dzencode_app bash -c "cd /var/www/backend && php artisan migrate"
docker exec -it dzencode_app bash -c "cd /var/www/backend && composer require vendor/package"
docker exec -it dzencode_app bash
cd /var/www/backend
php artisan migrate
php artisan make:model ModelName -mfs
php artisan tinker
```

## Architecture conventions — apply these as real endpoints get built

**Validation lives in Form Requests, never in controllers.** One request class per resource, shared between create and update — read the route's id param inside `rules()` to adjust (e.g. `Rule::unique(...)->ignore($id)` for edit vs. plain `unique` for create).

```php
class ProductRequest extends FormRequest
{
    public function rules(): array
    {
        $id = $this->route('id');
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', Rule::unique('products', 'slug')->ignore($id)],
        ];
    }
}
```

**No business logic in controllers.** A controller method only resolves a Request, calls a Service (`app/Services/`), and returns the response.

**Before creating a new one-off Service class, check whether an existing service already owns that domain and just add a method** — don't default to a new top-level Service/Controller per endpoint when one already covers that resource area.

**Response array shape belongs in `app/Http/Resources/`, not inline in a service method.** Every model→array transformation should be a `JsonResource`. One resource class per model/domain, not one per shape variant — a boolean constructor flag (e.g. `ProductResource(Product $product, bool $detailed = false)`) can cover both a list-card shape and a full-detail shape instead of splitting into separate files.

**Group routes by resource, don't declare flat separate `Route::` calls.** One `Route::prefix('resource')->controller(Controller::class)->group(function () { ... })` block per resource area.

## Caching

`CACHE_STORE=redis` is already set. Reach for it on read-heavy, non-personalized endpoints (aggregates, reference/listing data, same response for every visitor) once those exist — cache invalidation should hook into model write events (`saved`/`deleted`), not scattered manual calls in controllers/services. Endpoints that are inherently per-user or highly parameterized (search, filtered lists) need a deliberate key strategy — don't cache those by default.

**Never cache a raw `Collection` or Eloquent model — always `->toArray()` before returning from a cached closure.** Laravel's `serializable_classes => false` default means a cached object comes back as `__PHP_Incomplete_Class` on the next request.

## Tests

Ship tests with the API code that needs them — adding/changing an endpoint means adding/updating its feature test in the same change, and if a request/response contract changes, the existing test for it gets updated alongside, not left asserting the old shape. Match the existing `tests/Unit/ExampleTest.php` / `tests/Feature/ExampleTest.php` style: class-based (`class XTest extends TestCase { use RefreshDatabase; public function test_snake_case_description(): void { ... } }`), not Pest's functional `it()`/`test()` style.

`tests/Feature/CommentControllerTest.php` is the reference example for a full endpoint test suite — includes validation/relation edge cases plus dedicated security tests: XSS payloads (`<script>`, event-handler attrs, `javascript:` hrefs) asserted stripped from stored output, and SQL-injection payloads asserted stored as literal data (not executed) via `assertDatabaseHas` + `Schema::hasTable()` still returning true afterward. New user-generated-content endpoints should get the same two categories of test, not just happy-path/validation coverage. Don't write literal exploit strings as test fixtures when a benign non-image/non-script string proves the same thing just as well (e.g. testing "invalid image data is rejected" needs *any* non-image bytes, not a PHP webshell one-liner) — a real exploit signature committed to a file gets flagged by antivirus scanners even though it's inert, and even after removing it from the working tree, this harness's own file-history snapshots can retain the old version, so the string keeps getting flagged until those are found and purged too.

## Real-time (backend side)

See root `CLAUDE.md` § Real-time — nothing is wired up here yet. When a feature needs to push a live update, publish to Redis (`Redis::publish($channel, $payload)`) on the same channel name the frontend/websocket side subscribes to. No private-channel auth exists yet — design one (signed ticket, session check, whatever fits) before publishing anything user-specific.
