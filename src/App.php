<?php

/**
 * Ork BGG
 *
 * @package   Ork\BGG
 * @copyright 2019-2021 Alex Howansky (https://github.com/AlexHowansky)
 * @license   https://github.com/AlexHowansky/ork-bgg/blob/master/LICENSE MIT License
 * @link      https://github.com/AlexHowansky/ork-bgg
 */

namespace Ork\Bgg;

use Ork\Bgg\Route\Index;
use Psr\Container\ContainerInterface;
use Slim\Http\Environment;
use Slim\Http\Uri;
use Slim\Views\Twig;
use Slim\Views\TwigExtension;

/**
 * App class.
 */
class App extends \Slim\App
{

    /**
     * Create new application
     *
     * @param ContainerInterface|array $container Either a ContainerInterface or an associative array of app settings.
     */
    public function __construct($container = [])
    {
        parent::__construct($container);
        $this->registerView()->registerRoutes([Index::class]);
    }

    /**
     * Register the routes for this app.
     *
     * @param array $routes The list of routes to register.
     *
     * @return App Allow method chaining.
     */
    protected function registerRoutes(array $routes): App
    {
        foreach ($routes as $route) {
            $this->map($route::METHODS, $route::ROUTE, $route);
        }
        return $this;
    }

    /**
     * Register the view component.
     *
     * @return App Allow method chaining.
     */
    protected function registerView(): App
    {
        $this->getContainer()['view'] = function ($container) {
            $view = new Twig(
                (string) realpath(__DIR__ . '/../templates'),
                ['cache' => false]
            );
            $router = $container->get('router');
            $uri = Uri::createFromEnvironment(new Environment($_SERVER));
            $view->addExtension(new TwigExtension($router, $uri));
            return $view;
        };
        return $this;
    }

}
