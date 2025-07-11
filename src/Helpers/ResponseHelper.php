<?php

declare(strict_types=1);

namespace App\Helpers;

use Psr\Http\Message\ResponseInterface as Response;

/**
 * ResponseHelper provides static methods for consistent JSON responses.
 */
class ResponseHelper
{
    /**
     * Write a JSON response to the HTTP response object.
     *
     * @param Response $response
     * @param array $data
     * @param int $status
     * @return Response
     */
    public static function jsonResponse(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_PRETTY_PRINT));
        return $response->withStatus($status)->withHeader('Content-Type', 'application/json');
    }
}
