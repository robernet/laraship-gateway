<?php

namespace Corals\Modules\Gateway\Http\Controllers\Portal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * OpenAPI docs for the developer portal (GW-307). Session-authenticated like
 * the rest of the portal — no separate developer signup (GW-306 decision).
 * Renders contracts/openapi.yaml (the module's single source of truth for
 * every API boundary) via Swagger UI so a third party can browse and try
 * requests without leaving the portal.
 */
class DocsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:issuer');
    }

    public function index()
    {
        return view('Gateway::portal.docs');
    }

    public function spec(): Response
    {
        return response(
            file_get_contents(__DIR__.'/../../../contracts/openapi.yaml'),
            200,
            ['Content-Type' => 'application/yaml']
        );
    }
}
