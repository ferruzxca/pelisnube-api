<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\HttpException;
use App\Core\I18n;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\ContentRepository;
use App\Repositories\SectionRepository;

final class CatalogController extends BaseController
{
    public function __construct(
        private readonly ContentRepository $contents,
        private readonly SectionRepository $sections,
    ) {
    }

    public function list(Request $request): void
    {
        $pagination = $this->pagination($request);
        $type = $request->queryParam('type');
        $search = $request->queryParam('search');
        $section = $request->queryParam('section');

        $result = $this->contents->listCatalog(
            $pagination['page'],
            $pagination['pageSize'],
            $type,
            $search,
            $section,
        );

        Response::paginated(
            I18n::t('catalog_list', $request->lang()),
            $result['items'],
            $pagination['page'],
            $pagination['pageSize'],
            $result['total'],
        );
    }

    public function detail(Request $request): void
    {
        $slug = (string) $request->routeParam('slug');
        $item = $this->contents->catalogDetailBySlug($slug);

        if (!$item) {
            throw new HttpException(404, $request->lang() === 'en' ? 'Content not found.' : 'Contenido no encontrado.');
        }

        Response::success(I18n::t('catalog_detail', $request->lang()), $item);
    }

    public function homeSections(Request $request): void
    {
        $items = $this->sections->homeSections();
        Response::success(I18n::t('sections_home', $request->lang()), [
            'items' => $items,
        ]);
    }
}
