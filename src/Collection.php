<?php

/**
 * Ork BGG
 *
 * @package   Ork\BGG
 * @copyright 2019-2022 Alex Howansky (https://github.com/AlexHowansky)
 * @license   https://github.com/AlexHowansky/ork-bgg/blob/master/LICENSE MIT License
 * @link      https://github.com/AlexHowansky/ork-bgg
 */

namespace Ork\Bgg;

/**
 * User collection class.
 */
class Collection
{

    /**
     * Sync a user's BGG collection to the local database.
     *
     * @param string $username The user to sync.
     * @param string $pattern Optionally filter the set to games matching this pattern.
     */
    public function sync(string $username, string $pattern = null): void
    {
        $db = new Db();
        $bgg = new Bgg();
        $ownedGameIds = [];
        foreach ($bgg->getCollectionForUser($username, $pattern) as $game) {
            $ownedGameIds[] = $game['id'];
            $db->upsertGame($game);
            $db->upsertOwnage($username, $game['id']);
        }
        if ($pattern === null && empty($ownedGameIds) === false) {
            $db->deleteNotOwned($username, $ownedGameIds);
        }
    }

}
