<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ContentRepository;
use App\Repositories\FavoriteRepository;
use App\Services\AuditService;

final class FavoritesController extends BaseController
{
    public function __construct(
        private readonly FavoriteRepository $favorites,
        private readonly ContentRepository $contents,
        private readonly AuditService $auditService,
    ) {
    }

    public function list(Request $request): void
    {
        $user = $this->requireUser($request);
        if ($user['role'] !== 'USER') {
            throw new HttpException(403, I18n::t('forbidden', $request->lang()));
        }

        $pagination = $this->pagination($request);
        $result = $this->favorites->listByUser((string) $user['id'], $pagination['page'], $pagination['pageSize']);

        Response::paginated(
            I18n::t('favorites_list', $request->lang()),
            $result['items'],
            $pagination['page'],
            $pagination['pageSize'],
            $result['total'],
        );
    }

    public function save(Request $request): void
    {
        $user = $this->requireUser($request);
        if ($user['role'] !== 'USER') {
            throw new HttpException(403, I18n::t('forbidden', $request->lang()));
        }

        $contentId = (string) $request->routeParam('contentId');
        $content = $this->contents->findById($contentId);

        if (!$content || (int) $content['is_active'] !== 1) {
            throw new HttpException(404, $request->lang() === 'en' ? 'Content not found.' : 'Contenido no encontrado.');
        }

        $saved = $this->favorites->activate((string) $user['id'], $contentId);

        $this->auditService->mutation(
            $request,
            'favorites',
            (string) $saved['id'],
            'FAVORITE_ACTIVATE',
            null,
            $saved,
        );

        Response::success(I18n::t('favorite_saved', $request->lang()), [
            'favorite' => $saved,
        ]);
    }

    public function inactive(Request $request): void
    {
        $user = $this->requireUser($request);
        if ($user['role'] !== 'USER') {
            throw new HttpException(403, I18n::t('forbidden', $request->lang()));
        }

        $contentId = (string) $request->routeParam('contentId');
        $this->favorites->inactivate((string) $user['id'], $contentId);

        $this->auditService->mutation(
            $request,
            'favorites',
            $contentId,
            'FAVORITE_INACTIVATE',
            ['content_id' => $contentId],
            ['content_id' => $contentId, 'is_active' => 0],
        );

        Response::success(I18n::t('favorite_inactive', $request->lang()));
    }
}
