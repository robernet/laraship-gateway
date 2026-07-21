<?php

namespace Corals\Foundation\Http\Controllers;

use App\Http\Controllers\Controller;

class APIBaseController extends Controller
{
    protected $corals_middleware_except = [];
    protected $corals_middleware = ['auth:sanctum'];

    /**
     * BaseController constructor.
     */
    public function __construct()
    {
        $this->corals_middleware = \Filters::do_filter('corals_api_middleware', $this->corals_middleware, request());

        $this->middleware($this->corals_middleware, ['except' => $this->corals_middleware_except]);
    }

    /**
     * Proxy to the global apiResponse helper.
     *
     * @param mixed $data
     * @param string $message
     * @param string $status
     * @param int $HttpStatus
     * @param array $headers
     * @param int $options
     * @return \Illuminate\Http\JsonResponse
     */
    protected function apiResponse($data, $message = '', $status = 'success', $HttpStatus = 200, $headers = [], $options = 0)
    {
        return apiResponse($data, $message, $status, $HttpStatus, $headers, $options);
    }
}
