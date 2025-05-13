<?php

/**
 * Ork BGG
 *
 * @package   Ork\BGG
 * @copyright 2019-2024 Alex Howansky (https://github.com/AlexHowansky)
 * @license   https://github.com/AlexHowansky/ork-bgg/blob/master/LICENSE MIT License
 * @link      https://github.com/AlexHowansky/ork-bgg
 */

namespace Ork\Bgg\Route;

use Psr\Container\ContainerInterface as Container;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Abstract route.
 */
abstract class AbstractRoute implements RouteInterface
{

    /**
     * Request arguments.
     *
     * @var array
     */
    protected $args;

    /**
     * Request.
     *
     * @var Request
     */
    protected $request;

    /**
     * Response
     *
     * @var Response
     */
    protected $response;

    /**
     * Constructor.
     *
     * @param Container $container The request container.
     */
    public function __construct(protected Container $container)
    {
    }

    /**
     * Invoke a route.
     *
     * @param Request  $request  The request.
     * @param Response $response The response.
     * @param array    $args     The request arguments.
     *
     * @return Response
     */
    #[\Override]
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $this->request = $request;
        $this->response = $response;
        $this->args = $args;
        return $this->invoke();
    }

    /**
     * Invocation implementation abstraction.
     */
    abstract public function invoke(): Response;

    /**
     * Render a view.
     *
     * @param string $template The template to render.
     * @param array  $args     The arguments to pass to the template.
     */
    public function render(string $template, array $args = []): Response
    {
        return $this->container->get('view')->render($this->response, $template, $args);
    }

}
