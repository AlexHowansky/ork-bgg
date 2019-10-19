<?php

/**
 * Ork BGG
 *
 * @package   Ork\BGG
 * @copyright 2019 Alex Howansky (https://github.com/AlexHowansky)
 * @license   https://github.com/AlexHowansky/ork-bgg/blob/master/LICENSE MIT License
 * @link      https://github.com/AlexHowansky/ork-bgg
 */

namespace Ork\Bgg\Route;

use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Route interface.
 */
interface RouteInterface
{

    /**
     * Invoke a route.
     *
     * @param Request  $request  The request.
     * @param Response $response The response.
     * @param array    $args     The request arguments.
     *
     * @return Response
     */
    public function __invoke(Request $request, Response $response, array $args): Response;

}
