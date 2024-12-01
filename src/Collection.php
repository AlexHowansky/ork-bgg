<?php

/**
 * Ork BGG
 *
 * @package   Ork\BGG
 * @copyright 2019-2024 Alex Howansky (https://github.com/AlexHowansky)
 * @license   https://github.com/AlexHowansky/ork-bgg/blob/master/LICENSE MIT License
 * @link      https://github.com/AlexHowansky/ork-bgg
 */

namespace Ork\Bgg;

/**
 * User collection class.
 */
class Collection
{

    protected const array ANSI = [
        '</>' => "\x1b[0m",
        '<grey>' => "\x1b[30;40;1m",
        '<red>' => "\x1b[31;40;1m",
        '<green>' => "\x1b[32;40;1m",
        '<yellow>' => "\x1b[33;40;1m",
        '<blue>' => "\x1b[34;40;1m",
        '<magenta>' => "\x1b[35;40;1m",
        '<cyan>' => "\x1b[36;40;1m",
        '<white>' => "\x1b[37;40;1m",
    ];

    protected function log(string $message, int|string ...$args): void
    {
        echo str_replace(array_keys(self::ANSI), array_values(self::ANSI), sprintf($message, ...$args));
    }

    /**
     * Sync a user's BGG collection to the local database.
     *
     * @param string $username The user to sync.
     * @param string $pattern Optionally filter the set to games matching this pattern.
     * @param bool $full If true, sync details for all games, else only new games.
     */
    public function sync(string $username, string $pattern = null, bool $full = false): void
    {
        $db = new Db();
        $ownedGameIds = [];
        foreach ((new Bgg())->getCollectionForUser($username, $pattern) as $apiGame) {
            $ownedGameIds[] = $apiGame->id;
            $dbGame = $db->getGame($apiGame->id);
            if (empty($dbGame) === true) {
                $db->insertGame($apiGame);
                $this->log("<grey>[%d]</> <cyan>%s</> <green>inserted</>\n", $apiGame->id, $apiGame->name);
            } elseif ($full === true) {
                if ($dbGame->hash !== $apiGame->hash) {
                    $db->updateGame($apiGame);
                    $this->log("<grey>[%d]</> <cyan>%s</> <yellow>updated</>\n", $apiGame->id, $apiGame->name);
                } else {
                    $this->log("<grey>[%d]</> <cyan>%s</> unchanged\n", $apiGame->id, $apiGame->name);
                }
            }
            if ($db->userOwnsGame($username, $apiGame->id) === false) {
                $db->addOwnage($username, $apiGame->id);
                $this->log("<grey>[%d]</> <cyan>%s</> <green>added ownership</>\n", $apiGame->id, $apiGame->name);
            }
        }
        if ($pattern === null && empty($ownedGameIds) === false) {
            foreach ($db->deleteOwnage($username, $ownedGameIds) as $game) {
                $this->log("<grey>[%d]</> <cyan>%s</> <red>deleted ownership</>\n", $game->id, $game->name);
            }
        }
        foreach ($db->deleteOrphans() as $game) {
            $this->log("<grey>[%d]</> <cyan>%s</> <red>deleted orphan</>\n", $game->id, $game->name);
        }
        $this->log("<cyan>%d</> games synced\n", count($ownedGameIds));
    }

}
