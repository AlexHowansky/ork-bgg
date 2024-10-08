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

use Ork\Bgg\Db;
use Psr\Http\Message\ResponseInterface as Response;

/**
 * Index route.
 */
class Index extends AbstractRoute
{

    /**
     * The methods to allow for this route.
     */
    final public const array METHODS = ['GET', 'POST'];

    /**
     * The slug for this route.
     */
    final public const string ROUTE = '/';

    /**
     * Invocation implementation.
     */
    #[\Override]
    public function invoke(): Response
    {
        $db = new Db();
        return $this->render(
            'index.twig',
            [
                'post' => (array) $this->request->getParsedBody(),
                'users' => $db->getUsers(),
                'games' => $db->getGames((array) $this->request->getParsedBody()),
            ]
        );
    }

}
