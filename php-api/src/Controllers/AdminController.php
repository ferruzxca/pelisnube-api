<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Env;
use App\Core\HttpException;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AuditRepository;
use App\Repositories\ContentRepository;
use App\Repositories\FavoriteRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\PlanRepository;
use App\Repositories\SectionRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\UserRepository;
use App\Services\AuditService;

final class AdminController extends BaseController
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly ContentRepository $contents,
        private readonly SectionRepository $sections,
        private readonly PlanRepository $plans,
        private readonly SubscriptionRepository $subscriptions,
        private readonly PaymentRepository $payments,
        private readonly FavoriteRepository $favorites,
        private readonly AuditRepository $auditRepository,
        private readonly AuditService $auditService,
    ) {
    }

    public function dashboardKpis(Request $request): void
    {
        $this->requireAdmin($request);

        $roles = $this->users->countByRoleActive();
        $data = [
            'activeUsersByRole' => $roles,
            'activeUsersTotal' => array_sum($roles),
            'activeContentTotal' => $this->contents->countActive(),
            'activeSubscriptionsTotal' => $this->subscriptions->countActive(),
            'paymentsSuccessCurrentMonth' => $this->payments->countSuccessCurrentMonth(),
            'currency' => Env::get('APP_CURRENCY', 'MXN'),
            'generatedAt' => gmdate('c'),
        ];

        Response::success(I18n::t('admin_kpis', $request->lang()), $data);
    }

    public function chartUsersMonthly(Request $request): void
    {
        $this->requireAdmin($request);
        $data = $this->users->monthlyUsers(12);
        Response::success(I18n::t('admin_chart', $request->lang()), ['items' => $data]);
    }

    public function chartSubscriptionsStatus(Request $request): void
    {
        $this->requireAdmin($request);
        $data = $this->subscriptions->statusCounts();
        Response::success(I18n::t('admin_chart', $request->lang()), ['items' => $data]);
    }

    public function chartPaymentsMonthly(Request $request): void
    {
        $this->requireAdmin($request);
        $data = $this->payments->monthlyStats(12);
        Response::success(I18n::t('admin_chart', $request->lang()), ['items' => $data]);
    }

    public function chartTopContentFavorites(Request $request): void
    {
        $this->requireAdmin($request);
        $data = $this->favorites->topContentFavorites(10);
        Response::success(I18n::t('admin_chart', $request->lang()), ['items' => $data]);
    }

    public function auditLogs(Request $request): void
    {
        $this->requireAdmin($request);

        $pagination = $this->pagination($request);
        $result = $this->auditRepository->paginated(
            $pagination['page'],
            $pagination['pageSize'],
            $request->queryParam('targetType'),
            $request->queryParam('action'),
            $request->queryParam('actorUserId'),
            $request->queryParam('from'),
            $request->queryParam('to'),
        );

        Response::paginated(
            I18n::t('admin_audit', $request->lang()),
            $result['items'],
            $pagination['page'],
            $pagination['pageSize'],
            $result['total'],
        );
    }

    public function listContents(Request $request): void
    {
        $this->requireAdmin($request);
        $pagination = $this->pagination($request);
        $result = $this->contents->listAdmin($pagination['page'], $pagination['pageSize'], $request->queryParam('search'));

        Response::paginated(
            I18n::t('resource_list', $request->lang()),
            $result['items'],
            $pagination['page'],
            $pagination['pageSize'],
            $result['total'],
        );
    }

    public function createContent(Request $request): void
    {
        $this->requireAdmin($request);

        $body = $request->body();
        $title = trim((string) ($body['title'] ?? ''));
        $type = strtoupper(trim((string) ($body['type'] ?? 'MOVIE')));
        $synopsis = trim((string) ($body['synopsis'] ?? ''));
        $year = (int) ($body['year'] ?? 0);
        $duration = (int) ($body['duration'] ?? 0);
        $rating = (float) ($body['rating'] ?? 0);
        $trailerWatchUrl = trim((string) ($body['trailerWatchUrl'] ?? ''));
        $posterUrl = trim((string) ($body['posterUrl'] ?? ''));
        $bannerUrl = trim((string) ($body['bannerUrl'] ?? ''));

        if ($title === '' || $synopsis === '' || $trailerWatchUrl === '' || $posterUrl === '' || $bannerUrl === '') {
            throw new HttpException(422, I18n::t('validation_error', $request->lang()), [
                'required' => ['title', 'synopsis', 'trailerWatchUrl', 'posterUrl', 'bannerUrl'],
            ]);
        }

        if (!in_array($type, ['MOVIE', 'SERIES'], true)) {
            throw new HttpException(422, I18n::t('validation_error', $request->lang()), ['type' => 'invalid']);
        }

        foreach ([$trailerWatchUrl, $posterUrl, $bannerUrl] as $url) {
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new HttpException(422, I18n::t('validation_error', $request->lang()), ['url' => 'invalid']);
            }
        }

        $slug = trim((string) ($body['slug'] ?? ''));
        if ($slug === '') {
            $slug = slugify($title);
        }
        $slug = $this->uniqueContentSlug($slug);

        $created = $this->contents->create([
            'id' => uuidv4(),
            'title' => $title,
            'slug' => $slug,
            'type' => $type,
            'synopsis' => $synopsis,
            'year' => $year,
            'duration' => $duration,
            'rating' => $rating,
            'trailer_watch_url' => $trailerWatchUrl,
            'trailer_embed_url' => youtube_embed_url($trailerWatchUrl),
            'poster_url' => $posterUrl,
            'banner_url' => $bannerUrl,
            'is_active' => 1,
        ]);

        $sectionIds = $this->arrayInput($body['sectionIds'] ?? []);
        if ($sectionIds !== []) {
            $this->contents->syncSections((string) $created['id'], $sectionIds);
        }

        $updated = $this->contents->catalogDetailBySlug((string) $created['slug']) ?? $this->contents->normalizeContent($created, []);

        $this->auditService->mutation($request, 'contents', (string) $created['id'], 'CREATE', null, $updated);

        Response::success(I18n::t('resource_created', $request->lang()), $updated, 201);
    }

    public function updateContent(Request $request): void
    {
        $this->requireAdmin($request);

        $id = (string) $request->routeParam('id');
        $before = $this->contents->findById($id);

        if (!$before) {
            throw new HttpException(404, $request->lang() === 'en' ? 'Content not found.' : 'Contenido no encontrado.');
        }

        $body = $request->body();
        $data = [];

        if (array_key_exists('title', $body)) {
            $title = trim((string) $body['title']);
            if ($title === '') {
                throw new HttpException(422, I18n::t('validation_error', $request->lang()), ['title' => 'required']);
            }
            $data['title'] = $title;
            if (!array_key_exists('slug', $body)) {
                $data['slug'] = $this->uniqueContentSlug(slugify($title), (string) $before['id']);
            }
        }

        if (array_key_exists('slug', $body)) {
            $slug = trim((string) $body['slug']);
            $data['slug'] = $this->uniqueContentSlug(slugify($slug), (string) $before['id']);
        }

        if (array_key_exists('type', $body)) {
            $type = strtoupper(trim((string) $body['type']));
            if (!in_array($type, ['MOVIE', 'SERIES'], true)) {
                throw new HttpException(422, I18n::t('validation_error', $request->lang()), ['type' => 'invalid']);
            }
            $data['type'] = $type;
        }

        if (array_key_exists('synopsis', $body)) {
            $data['synopsis'] = trim((string) $body['synopsis']);
        }

        if (array_key_exists('year', $body)) {
            $data['year'] = (int) $body['year'];
        }

        if (array_key_exists('duration', $body)) {
            $data['duration'] = (int) $body['duration'];
        }

        if (array_key_exists('rating', $body)) {
            $data['rating'] = (float) $body['rating'];
        }

        if (array_key_exists('trailerWatchUrl', $body)) {
            $url = trim((string) $body['trailerWatchUrl']);
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new HttpException(422, I18n::t('validation_error', $request->lang()), ['trailerWatchUrl' => 'invalid']);
            }
            $data['trailer_watch_url'] = $url;
            $data['trailer_embed_url'] = youtube_embed_url($url);
        }

        if (array_key_exists('posterUrl', $body)) {
            $url = trim((string) $body['posterUrl']);
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new HttpException(422, I18n::t('validation_error', $request->lang()), ['posterUrl' => 'invalid']);
            }
            $data['poster_url'] = $url;
        }

        if (array_key_exists('bannerUrl', $body)) {
            $url = trim((string) $body['bannerUrl']);
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                throw new HttpException(422, I18n::t('validation_error', $request->lang()), ['bannerUrl' => 'invalid']);
            }
            $data['banner_url'] = $url;
        }

        if (array_key_exists('isActive', $body)) {
            $data['is_active'] = $this->boolToInt($body['isActive']);
        }

        $updatedRow = $this->contents->update($id, $data);
        if (!$updatedRow) {
            throw new HttpException(404, $request->lang() === 'en' ? 'Content not found.' : 'Contenido no encontrado.');
        }

        if (array_key_exists('sectionIds', $body)) {
            $this->contents->syncSections($id, $this->arrayInput($body['sectionIds']));
        }

        $after = $this->contents->catalogDetailBySlug((string) $updatedRow['slug']) ?? $this->contents->normalizeContent($updatedRow, []);

        $this->auditService->mutation($request, 'contents', $id, 'UPDATE', $before, $after);

        Response::success(I18n::t('resource_updated', $request->lang()), $after);
    }

    public function inactivateContent(Request $request): void
    {
        $this->requireAdmin($request);

        $id = (string) $request->routeParam('id');
        $before = $this->contents->findById($id);
        if (!$before) {
            throw new HttpException(404, $request->lang() === 'en' ? 'Content not found.' : 'Contenido no encontrado.');
        }

        $this->contents->inactivate($id);
        $after = $this->contents->findById($id);

        $this->auditService->mutation($request, 'contents', $id, 'INACTIVATE', $before, $after);

        Response::success(I18n::t('resource_inactive', $request->lang()), [
            'id' => $id,
            'isActive' => false,
        ]);
    }

    public function listSections(Request $request): void
    {
        $this->requireAdmin($request);
        $pagination = $this->pagination($request);
        $result = $this->sections->listAdmin($pagination['page'], $pagination['pageSize'], $request->queryParam('search'));

        Response::paginated(I18n::t('resource_list', $request->lang()), $result['items'], $pagination['page'], $pagination['pageSize'], $result['total']);
    }

    public function createSection(Request $request): void
    {
        $this->requireAdmin($request);

        $body = $request->body();
        $name = trim((string) ($body['name'] ?? ''));
        if ($name === '') {
            throw new HttpException(422, I18n::t('validation_error', $request->lang()), ['name' => 'required']);
        }

        $key = trim((string) ($body['key'] ?? ''));
        if ($key === '') {
            $key = slugify($name);
        }

        if ($this->sections->findByKey($key)) {
            throw new HttpException(409, $request->lang() === 'en' ? 'Section key already exists.' : 'La clave de seccion ya existe.');
        }

        $created = $this->sections->create([
            'id' => uuidv4(),
            'section_key' => $key,
            'name' => $name,
            'description' => trim((string) ($body['description'] ?? '')),
            'sort_order' => (int) ($body['sortOrder'] ?? 0),
            'is_home_visible' => $this->boolToInt($body['isHomeVisible'] ?? true),
            'is_active' => 1,
        ]);

        if (array_key_exists('contentIds', $body)) {
            $this->sections->syncContents((string) $created['id'], $this->arrayInput($body['contentIds']));
        }

        $after = $this->sections->normalizeSection($created);
        $this->auditService->mutation($request, 'sections', (string) $created['id'], 'CREATE', null, $after);

        Response::success(I18n::t('resource_created', $request->lang()), $after, 201);
    }

    public function updateSection(Request $request): void
    {
        $this->requireAdmin($request);

        $id = (string) $request->routeParam('id');
        $before = $this->sections->findById($id);

        if (!$before) {
            throw new HttpException(404, $request->lang() === 'en' ? 'Section not found.' : 'Seccion no encontrada.');
        }

        $body = $request->body();
        $data = [];

        if (array_key_exists('name', $body)) {
            $data['name'] = trim((string) $body['name']);
        }

        if (array_key_exists('key', $body)) {
            $newKey = slugify((string) $body['key']);
            $exists = $this->sections->findByKey($newKey);
            if ($exists && $exists['id'] !== $id) {
                throw new HttpException(409, $request->lang() === 'en' ? 'Section key already exists.' : 'La clave de seccion ya existe.');
            }
            $data['section_key'] = $newKey;
        }

        if (array_key_exists('description', $body)) {
            $data['description'] = trim((string) $body['description']);
        }

        if (array_key_exists('sortOrder', $body)) {
            $data['sort_order'] = (int) $body['sortOrder'];
        }

        if (array_key_exists('isHomeVisible', $body)) {
            $data['is_home_visible'] = $this->boolToInt($body['isHomeVisible']);
        }

        if (array_key_exists('isActive', $body)) {
            $data['is_active'] = $this->boolToInt($body['isActive']);
        }

        $updated = $this->sections->update($id, $data);
        if (!$updated) {
            throw new HttpException(404, $request->lang() === 'en' ? 'Section not found.' : 'Seccion no encontrada.');
        }

        if (array_key_exists('contentIds', $body)) {
            $this->sections->syncContents($id, $this->arrayInput($body['contentIds']));
        }

        $after = $this->sections->normalizeSection($updated);
        $this->auditService->mutation($request, 'sections', $id, 'UPDATE', $before, $after);

        Response::success(I18n::t('resource_updated', $request->lang()), $after);
    }

    public function inactivateSection(Request $request): void
    {
        $this->requireAdmin($request);

        $id = (string) $request->routeParam('id');
        $before = $this->sections->findById($id);

        if (!$before) {
            throw new HttpException(404, $request->lang() === 'en' ? 'Section not found.' : 'Seccion no encontrada.');
        }

        $this->sections->inactivate($id);
        $after = $this->sections->findById($id);

        $this->auditService->mutation($request, 'sections', $id, 'INACTIVATE', $before, $after);

        Response::success(I18n::t('resource_inactive', $request->lang()), ['id' => $id, 'isActive' => false]);
    }

    public function listUsers(Request $request): void
    {
        $this->requireAdmin($request);

        $pagination = $this->pagination($request);
        $result = $this->users->paginated(
            $pagination['page'],
            $pagination['pageSize'],
            $request->queryParam('search'),
            $request->queryParam('role'),
            $request->queryParam('status'),
        );

        Response::paginated(I18n::t('resource_list', $request->lang()), $result['items'], $pagination['page'], $pagination['pageSize'], $result['total']);
    }

    public function createUser(Request $request): void
    {
        $actor = $this->requireAdmin($request);
        $body = $request->body();

        $name = trim((string) ($body['name'] ?? ''));
        $email = strtolower(trim((string) ($body['email'] ?? '')));
        $password = (string) ($body['password'] ?? '');
        $role = strtoupper(trim((string) ($body['role'] ?? 'USER')));
        $status = strtoupper(trim((string) ($body['status'] ?? 'ACTIVE')));

        if ($name === '' || $email === '' || $password === '') {
            throw new HttpException(422, I18n::t('validation_error', $request->lang()), ['required' => ['name', 'email', 'password']]);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new HttpException(422, I18n::t('validation_error', $request->lang()), ['email' => 'invalid']);
        }

        if (!in_array($role, ['SUPER_ADMIN', 'ADMIN', 'USER'], true)) {
            throw new HttpException(422, I18n::t('validation_error', $request->lang()), ['role' => 'invalid']);
        }

        if ($actor['role'] === 'ADMIN' && $role !== 'USER') {
            throw new HttpException(403, I18n::t('forbidden', $request->lang()));
        }

        if ($role === 'SUPER_ADMIN' && $actor['role'] !== 'SUPER_ADMIN') {
            throw new HttpException(403, I18n::t('forbidden', $request->lang()));
        }

        if ($this->users->findByEmail($email)) {
            throw new HttpException(409, $request->lang() === 'en' ? 'Email already exists.' : 'El correo ya existe.');
        }

        $created = $this->users->create([
            'id' => uuidv4(),
            'name' => $name,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_BCRYPT),
            'role' => $role,
            'status' => $status,
            'is_active' => 1,
            'preferred_lang' => strtolower((string) ($body['preferredLang'] ?? 'es')) === 'en' ? 'en' : 'es',
            'must_change_password' => $this->boolToInt($body['mustChangePassword'] ?? false),
        ]);

        $this->auditService->mutation($request, 'users', (string) $created['id'], 'CREATE', null, $this->sanitizeUser($created));

        Response::success(I18n::t('resource_created', $request->lang()), $this->sanitizeUser($created), 201);
    }

    public function updateUser(Request $request): void
    {
        $actor = $this->requireAdmin($request);
        $id = (string) $request->routeParam('id');
        $before = $this->users->findById($id);

        if (!$before) {
            throw new HttpException(404, $request->lang() === 'en' ? 'User not found.' : 'Usuario no encontrado.');
        }

        if ($actor['role'] === 'ADMIN' && $before['role'] !== 'USER') {
            throw new HttpException(403, I18n::t('forbidden', $request->lang()));
        }

        $body = $request->body();
        $data = [];

        if (array_key_exists('name', $body)) {
            $data['name'] = trim((string) $body['name']);
        }

        if (array_key_exists('status', $body)) {
            $status = strtoupper(trim((string) $body['status']));
            if (!in_array($status, ['PENDING', 'ACTIVE', 'SUSPENDED', 'INACTIVE'], true)) {
                throw new HttpException(422, I18n::t('validation_error', $request->lang()), ['status' => 'invalid']);
            }
            $data['status'] = $status;
        }

        if (array_key_exists('preferredLang', $body)) {
            $lang = strtolower((string) $body['preferredLang']);
            $data['preferred_lang'] = $lang === 'en' ? 'en' : 'es';
        }

        if (array_key_exists('role', $body)) {
            $role = strtoupper(trim((string) $body['role']));
            if (!in_array($role, ['SUPER_ADMIN', 'ADMIN', 'USER'], true)) {
                throw new HttpException(422, I18n::t('validation_error', $request->lang()), ['role' => 'invalid']);
            }

            if ($actor['role'] === 'ADMIN' && $role !== 'USER') {
                throw new HttpException(403, I18n::t('forbidden', $request->lang()));
            }

            if ($role === 'SUPER_ADMIN' && $actor['role'] !== 'SUPER_ADMIN') {
                throw new HttpException(403, I18n::t('forbidden', $request->lang()));
            }

            $data['role'] = $role;
        }

        if (array_key_exists('mustChangePassword', $body)) {
            $data['must_change_password'] = $this->boolToInt($body['mustChangePassword']);
        }

        if (array_key_exists('isActive', $body)) {
            $isActive = $this->boolToInt($body['isActive']);
            $data['is_active'] = $isActive;
            if ($isActive === 0) {
                $data['status'] = 'INACTIVE';
            }
        }

        $updated = $this->users->update($id, $data);

        $this->auditService->mutation($request, 'users', $id, 'UPDATE', $this->sanitizeUser($before), $this->sanitizeUser($updated ?? $before));

        Response::success(I18n::t('resource_updated', $request->lang()), $this->sanitizeUser($updated ?? $before));
    }

    public function resetUserPassword(Request $request): void
    {
        $actor = $this->requireAdmin($request);
        $id = (string) $request->routeParam('id');
        $target = $this->users->findById($id);

        if (!$target) {
            throw new HttpException(404, $request->lang() === 'en' ? 'User not found.' : 'Usuario no encontrado.');
        }

        if ($actor['role'] === 'ADMIN' && $target['role'] !== 'USER') {
            throw new HttpException(403, I18n::t('forbidden', $request->lang()));
        }

        if ($target['role'] === 'SUPER_ADMIN' && $actor['role'] !== 'SUPER_ADMIN') {
            throw new HttpException(403, I18n::t('forbidden', $request->lang()));
        }

        $newPassword = (string) ($request->input('newPassword') ?? '');
        if ($newPassword === '') {
            $newPassword = substr(bin2hex(random_bytes(8)), 0, 12);
        }

        if (strlen($newPassword) < 8) {
            throw new HttpException(422, I18n::t('validation_error', $request->lang()), ['newPassword' => 'min_8']);
        }

        $this->users->updatePassword($id, password_hash($newPassword, PASSWORD_BCRYPT), true);
        $after = $this->users->findById($id);

        $this->auditService->mutation($request, 'users', $id, 'RESET_PASSWORD', $this->sanitizeUser($target), $this->sanitizeUser($after ?? $target));

        Response::success(I18n::t('resource_updated', $request->lang()), [
            'userId' => $id,
            'temporaryPassword' => $newPassword,
            'mustChangePassword' => true,
        ]);
    }

    public function inactivateUser(Request $request): void
    {
        $actor = $this->requireAdmin($request);
        $id = (string) $request->routeParam('id');
        $before = $this->users->findById($id);

        if (!$before) {
            throw new HttpException(404, $request->lang() === 'en' ? 'User not found.' : 'Usuario no encontrado.');
        }

        if ($actor['id'] === $id) {
            throw new HttpException(422, $request->lang() === 'en' ? 'You cannot inactivate yourself.' : 'No puedes inactivarte a ti mismo.');
        }

        if ($actor['role'] === 'ADMIN' && $before['role'] !== 'USER') {
            throw new HttpException(403, I18n::t('forbidden', $request->lang()));
        }

        if ($before['role'] === 'SUPER_ADMIN' && $actor['role'] !== 'SUPER_ADMIN') {
            throw new HttpException(403, I18n::t('forbidden', $request->lang()));
        }

        $this->users->inactivate($id);
        $after = $this->users->findById($id);

        $this->auditService->mutation($request, 'users', $id, 'INACTIVATE', $this->sanitizeUser($before), $this->sanitizeUser($after ?? $before));

        Response::success(I18n::t('resource_inactive', $request->lang()), ['id' => $id, 'isActive' => false]);
    }

    public function listPlans(Request $request): void
    {
        $this->requireAdmin($request);
        $pagination = $this->pagination($request);
        $result = $this->plans->paginated($pagination['page'], $pagination['pageSize'], $request->queryParam('search'));

        Response::paginated(I18n::t('resource_list', $request->lang()), $result['items'], $pagination['page'], $pagination['pageSize'], $result['total']);
    }

    public function createPlan(Request $request): void
    {
        $this->requireAdmin($request);
        $body = $request->body();

        $code = strtoupper(trim((string) ($body['code'] ?? '')));
        $name = trim((string) ($body['name'] ?? ''));
        $price = (float) ($body['priceMonthly'] ?? 0);

        if ($code === '' || $name === '' || $price <= 0) {
            throw new HttpException(422, I18n::t('validation_error', $request->lang()), ['required' => ['code', 'name', 'priceMonthly']]);
        }

        if ($this->plans->findByCode($code)) {
            throw new HttpException(409, $request->lang() === 'en' ? 'Plan code already exists.' : 'El codigo del plan ya existe.');
        }

        $created = $this->plans->create([
            'id' => uuidv4(),
            'code' => $code,
            'name' => $name,
            'price_monthly' => $price,
            'currency' => strtoupper((string) ($body['currency'] ?? Env::get('APP_CURRENCY', 'MXN'))),
            'quality' => trim((string) ($body['quality'] ?? 'HD')),
            'screens' => (int) ($body['screens'] ?? 1),
            'is_active' => 1,
        ]);

        $after = $this->plans->normalizePlan($created);
        $this->auditService->mutation($request, 'plans', (string) $created['id'], 'CREATE', null, $after);

        Response::success(I18n::t('resource_created', $request->lang()), $after, 201);
    }

    public function updatePlan(Request $request): void
    {
        $this->requireAdmin($request);

        $id = (string) $request->routeParam('id');
        $before = $this->plans->findById($id);

        if (!$before) {
            throw new HttpException(404, $request->lang() === 'en' ? 'Plan not found.' : 'Plan no encontrado.');
        }

        $body = $request->body();
        $data = [];

        if (array_key_exists('code', $body)) {
            $code = strtoupper(trim((string) $body['code']));
            $exists = $this->plans->findByCode($code);
            if ($exists && $exists['id'] !== $id) {
                throw new HttpException(409, $request->lang() === 'en' ? 'Plan code already exists.' : 'El codigo del plan ya existe.');
            }
            $data['code'] = $code;
        }

        if (array_key_exists('name', $body)) {
            $data['name'] = trim((string) $body['name']);
        }

        if (array_key_exists('priceMonthly', $body)) {
            $data['price_monthly'] = (float) $body['priceMonthly'];
        }

        if (array_key_exists('currency', $body)) {
            $data['currency'] = strtoupper(trim((string) $body['currency']));
        }

        if (array_key_exists('quality', $body)) {
            $data['quality'] = trim((string) $body['quality']);
        }

        if (array_key_exists('screens', $body)) {
            $data['screens'] = (int) $body['screens'];
        }

        if (array_key_exists('isActive', $body)) {
            $data['is_active'] = $this->boolToInt($body['isActive']);
        }

        $updated = $this->plans->update($id, $data);

        if (!$updated) {
            throw new HttpException(404, $request->lang() === 'en' ? 'Plan not found.' : 'Plan no encontrado.');
        }

        $this->auditService->mutation(
            $request,
            'plans',
            $id,
            'UPDATE',
            $this->plans->normalizePlan($before),
            $this->plans->normalizePlan($updated),
        );

        Response::success(I18n::t('resource_updated', $request->lang()), $this->plans->normalizePlan($updated));
    }

    public function inactivatePlan(Request $request): void
    {
        $this->requireAdmin($request);

        $id = (string) $request->routeParam('id');
        $before = $this->plans->findById($id);

        if (!$before) {
            throw new HttpException(404, $request->lang() === 'en' ? 'Plan not found.' : 'Plan no encontrado.');
        }

        $this->plans->inactivate($id);
        $after = $this->plans->findById($id);

        $this->auditService->mutation(
            $request,
            'plans',
            $id,
            'INACTIVATE',
            $this->plans->normalizePlan($before),
            $after ? $this->plans->normalizePlan($after) : null,
        );

        Response::success(I18n::t('resource_inactive', $request->lang()), ['id' => $id, 'isActive' => false]);
    }

    public function listSubscriptions(Request $request): void
    {
        $this->requireAdmin($request);

        $pagination = $this->pagination($request);
        $result = $this->subscriptions->paginatedWithDetails(
            $pagination['page'],
            $pagination['pageSize'],
            $request->queryParam('status'),
        );

        Response::paginated(I18n::t('resource_list', $request->lang()), $result['items'], $pagination['page'], $pagination['pageSize'], $result['total']);
    }

    public function updateSubscription(Request $request): void
    {
        $this->requireAdmin($request);

        $id = (string) $request->routeParam('id');
        $before = $this->subscriptions->findById($id);

        if (!$before) {
            throw new HttpException(404, $request->lang() === 'en' ? 'Subscription not found.' : 'Suscripcion no encontrada.');
        }

        $body = $request->body();
        $data = [];

        if (array_key_exists('status', $body)) {
            $status = strtoupper(trim((string) $body['status']));
            if (!in_array($status, ['PENDING', 'ACTIVE', 'CANCELED', 'EXPIRED'], true)) {
                throw new HttpException(422, I18n::t('validation_error', $request->lang()), ['status' => 'invalid']);
            }
            $data['status'] = $status;
        }

        if (array_key_exists('isActive', $body)) {
            $data['is_active'] = $this->boolToInt($body['isActive']);
        }

        if (array_key_exists('renewalAt', $body)) {
            $data['renewal_at'] = (string) $body['renewalAt'];
        }

        if (array_key_exists('endedAt', $body)) {
            $data['ended_at'] = (string) $body['endedAt'];
        }

        if (array_key_exists('planCode', $body)) {
            $plan = $this->plans->findByCode((string) $body['planCode']);
            if (!$plan) {
                throw new HttpException(404, $request->lang() === 'en' ? 'Plan not found.' : 'Plan no encontrado.');
            }
            $data['plan_id'] = $plan['id'];
        }

        $updated = $this->subscriptions->update($id, $data);

        $this->auditService->mutation($request, 'subscriptions', $id, 'UPDATE', $before, $updated);

        Response::success(I18n::t('resource_updated', $request->lang()), $updated ?? $before);
    }

    public function inactivateSubscription(Request $request): void
    {
        $this->requireAdmin($request);

        $id = (string) $request->routeParam('id');
        $before = $this->subscriptions->findById($id);

        if (!$before) {
            throw new HttpException(404, $request->lang() === 'en' ? 'Subscription not found.' : 'Suscripcion no encontrada.');
        }

        $this->subscriptions->inactivate($id);
        $after = $this->subscriptions->update($id, ['status' => 'EXPIRED', 'ended_at' => now_utc()]);

        $this->auditService->mutation($request, 'subscriptions', $id, 'INACTIVATE', $before, $after);

        Response::success(I18n::t('resource_inactive', $request->lang()), ['id' => $id, 'isActive' => false]);
    }

    /**
     * @return array<string,mixed>
     */
    private function requireAdmin(Request $request): array
    {
        $user = $this->requireUser($request);
        if (!in_array($user['role'], ['SUPER_ADMIN', 'ADMIN'], true)) {
            throw new HttpException(403, I18n::t('forbidden', $request->lang()));
        }

        return $user;
    }

    private function uniqueContentSlug(string $baseSlug, ?string $excludeId = null): string
    {
        $slug = $baseSlug;
        $counter = 1;

        while (true) {
            $existing = $this->contents->findBySlug($slug);
            if (!$existing || ($excludeId !== null && $existing['id'] === $excludeId)) {
                return $slug;
            }

            $counter++;
            $slug = $baseSlug . '-' . $counter;
        }
    }

    /**
     * @param mixed $value
     */
    private function boolToInt(mixed $value): int
    {
        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_numeric($value)) {
            return ((int) $value) === 1 ? 1 : 0;
        }

        $text = strtolower(trim((string) $value));
        return in_array($text, ['1', 'true', 'yes', 'on'], true) ? 1 : 0;
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function arrayInput(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map('strval', $value), static fn(string $item): bool => trim($item) !== ''));
        }

        if (is_string($value)) {
            $parts = array_map('trim', explode(',', $value));
            return array_values(array_filter($parts, static fn(string $item): bool => $item !== ''));
        }

        return [];
    }

    /**
     * @param array<string,mixed> $user
     * @return array<string,mixed>
     */
    private function sanitizeUser(array $user): array
    {
        return [
            'id' => $user['id'],
            'name' => $user['name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'status' => $user['status'],
            'isActive' => (int) $user['is_active'] === 1,
            'preferredLang' => $user['preferred_lang'],
            'mustChangePassword' => (int) $user['must_change_password'] === 1,
            'createdAt' => $user['created_at'] ?? null,
            'updatedAt' => $user['updated_at'] ?? null,
        ];
    }
}
