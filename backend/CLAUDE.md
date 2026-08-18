# CLAUDE.md (backend)

Backend-specific conventions for `backend/` (Laravel 12, API only — no admin panel, no Livewire). See the root `CLAUDE.md` for Docker/monorepo-wide rules, `frontend/CLAUDE.md` for the Vue app.

## Current state

Fresh `laravel/laravel` skeleton. Only the framework itself + `predis/predis` are installed (`composer.json`) — no Sanctum, no queue driver beyond `database`, no domain code yet (default `User` model, default migrations, no `routes/api.php`). Don't reference packages that aren't in `composer.json` — check before assuming something (Sanctum, Livewire, a specific cache/queue driver) is available.

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

Ship tests with the API code that needs them — adding/changing an endpoint means adding/updating its feature test in the same change. Match the existing `tests/Unit/ExampleTest.php` / `tests/Feature/ExampleTest.php` style: class-based (`class XTest extends TestCase { use RefreshDatabase; public function test_snake_case_description(): void { ... } }`), not Pest's functional `it()`/`test()` style.

## Real-time (backend side)

See root `CLAUDE.md` § Real-time — nothing is wired up here yet. When a feature needs to push a live update, publish to Redis (`Redis::publish($channel, $payload)`) on the same channel name the frontend/websocket side subscribes to. No private-channel auth exists yet — design one (signed ticket, session check, whatever fits) before publishing anything user-specific.
