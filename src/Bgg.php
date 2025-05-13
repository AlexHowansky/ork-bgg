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

use Generator;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;
use SimpleXMLElement;

/**
 * BGG API helper.
 */
class Bgg
{

    // The BGG API base URI.
    protected const BASE_URI = 'https://www.boardgamegeek.com/xmlapi2/';

    // If we get rejected due to a rate limit restriction, sleep this long and
    // then try again. The API throws a 429 status but does not provide any
    // sort of X-Retry-After header, so we just have to guess.
    protected const RATE_LIMIT_SLEEP = 30;

    /**
     * Build a Game object of details from a API XML packet.
     *
     * @param SimpleXMLElement $game The API data to build the Game object from.
     *
     * @return Game The Game object.
     */
    protected function buildGame(SimpleXMLElement $game): Game
    {
        $row = [
            'id' => (int) ($game->attributes()['objectid'] ?? 0),
            'name' => $this->filterName((string) $game->name),
            'yearPublished' => (int) ($game->yearpublished ?? 0),
            'image' => (string) $game->image,
            'thumbnail' => (string) $game->thumbnail,
            'minPlayers' => (int) ($game->stats->attributes()['minplayers'] ?? 0),
            'maxPlayers' => (int) ($game->stats->attributes()['maxplayers'] ?? 0),
            'minPlayTime' => (int) ($game->stats->attributes()['minplaytime'] ?? 0),
            'maxPlayTime' => (int) ($game->stats->attributes()['maxplaytime'] ?? 0),
            'playTime' => (int) ($game->stats->attributes()['playingtime'] ?? 0),
            'geekRating' => (float) ($game->stats->rating->bayesaverage->attributes()['value'] ?? 0),
            'averageRating' => (float) ($game->stats->rating->average->attributes()['value'] ?? 0),
            'numVoters' => (int) ($game->stats->rating->usersrated->attributes()['value'] ?? 0),
        ];
        foreach ($game->stats->rating->ranks->rank as $rank) {
            if ((string) ($rank->attributes()['name'] ?? '') === 'boardgame') {
                $row['rank'] = (int) ($rank->attributes()['value'] ?? 0);
                break;
            }
        }
        return new Game($row);
    }

    protected function filterName(string $name): string
    {
        // Replace all multiple-character whitespace blocks with a single space.
        $name = (string) preg_replace('/\s+/', ' ', $name);

        // Trim spaces from both ends.
        $name = trim($name);

        // Strip ": The Board Game" from the end of names, we get it, it's a board game.
        $name = (string) preg_replace('/:? (the )?board ?game$/i', '', $name);

        // Strip "The " from the start of names so labels are smaller and we sort correctly.
        $name = (string) preg_replace('/^the /i', '', $name);

        return $name;
    }

    /**
     * Make an API request.
     *
     * @param string $url The request URL.
     * @param array $args The request arguments.
     *
     * @return SimpleXMLElement The decoded response body.
     *
     * @throws RuntimeException On error.
     */
    protected function get(string $url, array $args): SimpleXMLElement
    {
        while (true) {
            $xml = false;
            try {
                $xml = simplexml_load_string(
                    (new Client(['base_uri' => self::BASE_URI]))
                        ->get($url, ['query' => $args])
                        ->getBody()
                        ->getContents()
                );
            } catch (ClientException $e) {
                if ($e->getResponse()->getStatusCode() === 429) {
                    sleep(self::RATE_LIMIT_SLEEP);
                    continue;
                } else {
                    throw new RuntimeException('BGG API error: ' . $e->getMessage());
                }
            }
            break;
        }
        if ($xml === false) {
            throw new RuntimeException('BGG API response was not valid XML.');
        }
        if ($xml->getName() === 'errors') {
            throw new RuntimeException('BGG API returned error: ' . $xml->error->message);
        }
        if ($xml->getName() === 'message') {
            throw new RuntimeException('BGG API returned message: ' . trim((string) $xml));
        }
        return $xml;
    }

    /**
     * Get a user's owned collection.
     *
     * @param string $username The user to get the collection for.
     * @param string $pattern Optionally filter the set to games matching this pattern.
     *
     * @return Generator<Game> An iterator over the user's collected items.
     *
     * @throws RuntimeException If the user owns no games.
     */
    public function getCollectionForUser(string $username, ?string $pattern = null): Generator
    {
        $collection = $this->get(
            'collection',
            [
                'username' => $username,
                'version' => 1,
                'stats' => 1,
                'own' => 1,
            ]
        );
        if (count($collection) === 0) {
            throw new RuntimeException('User owns no games.');
        }
        foreach ($collection as $game) {
            if ((string) ($game->attributes()['subtype'] ?? '') !== 'boardgame') {
                continue;
            }
            if ($pattern !== null) {
                // phpcs:ignore Generic.PHP.NoSilencedErrors.Discouraged
                if (@preg_match($pattern, '') === false) {
                    if (stripos((string) $game->name, $pattern) === false) {
                        continue;
                    }
                } else {
                    if (preg_match($pattern, (string) $game->name) !== 1) {
                        continue;
                    }
                }
            }
            yield $this->buildGame($game);
        }
    }

    /**
     * Get the details for a thing.
     *
     * @param int $id The ID of the thing to get the details for.
     *
     * @return array The thing details.
     */
    public function getDetailsForThing(int $id): array
    {
        $things = $this->get(
            'thing',
            [
                'id' => $id,
                'version' => 1,
                'stats' => 1,
                'own' => 1,
            ]
        );
        foreach ($things as $thing) {
            $recommended = [];
            foreach ($thing->xpath('//poll[@name="suggested_numplayers"]/results') as $results) {
                foreach ($results as $result) {
                    if ((string) ($result->attributes()['value'] ?? '') === 'Best') {
                        $recommended[(int) ($results->attributes()['numplayers'] ?? 0)] =
                            (int) ($result->attributes()['numvotes'] ?? 0);
                        break;
                    }
                }
            }
            arsort($recommended);
            return [
                'cooperative' => !empty($thing->xpath('//link[@type="boardgamemechanic"][@value="Cooperative Game"]')),
                'description' => (string) $thing->description,
                'recommendedPlayers' => key($recommended),
                'weight' => (float) ($thing->statistics->ratings->averageweight->attributes()['value'] ?? 0),
            ];
        }
        return [];
    }

}
