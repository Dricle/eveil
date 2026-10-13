<?php

namespace App\Mcp\Tools\Concerns;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\JsonSchema\Types\Type;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;

/**
 * The API's pagination, minus the links: a `links.next` URL would point at
 * `/mcp?page=2`, which means nothing to a client that speaks JSON-RPC. The
 * page number is a tool argument instead, and the answer says how many exist.
 */
trait PagesResults
{
    /**
     * @param  Builder<*>|Relation<*, *, *>  $query
     * @param  class-string<JsonResource>  $resource
     */
    protected function paginated(Builder|Relation $query, string $resource, Request $request): ResponseFactory
    {
        $page = $query->paginate(50, page: max(1, $request->integer('page', 1)));

        return Response::structured([
            'data' => $resource::collection($page->getCollection())->resolve(),
            'page' => $page->currentPage(),
            'last_page' => $page->lastPage(),
            'total' => $page->total(),
        ]);
    }

    protected function pageArgument(JsonSchema $schema): Type
    {
        return $schema->integer()->min(1)->description('The page to read, 50 items each. Defaults to 1; the answer gives last_page.');
    }
}
