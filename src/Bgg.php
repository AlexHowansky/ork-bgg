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

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use Psr\Http\Message\ResponseInterface;

/**
 * BGG API helper.
 */
class Bgg
{

    // The BGG API base URI.
    protected const BASE_URI = 'https://www.boardgamegeek.com/xmlapi2/';

    // If we get rejected due to a rate limit restriction, sleep this long and then try again.
    protected const RATE_LIMIT_SLEEP = 30;

    /**
     * Make an API request.
     *
     * @param string $url  The request URL.
     * @param array  $args The request arguments.
     *
     * @return \SimpleXMLElement The decoded response body.
     *
     * @throws \RuntimeException On error.
     */
    protected function get(string $url, array $args): \SimpleXMLElement
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
                if (
                    $e->getResponse() instanceof ResponseInterface &&
                    $e->getResponse()->getStatusCode() === 429
                ) {
                    sleep(self::RATE_LIMIT_SLEEP);
                    continue;
                } else {
                    throw new \RuntimeException('BGG API error: ' . $e->getMessage());
                }
            }
            break;
        }
        if ($xml === false) {
            throw new \RuntimeException('BGG API response was not valid XML.');
        }
        if ($xml->getName() === 'errors') {
            throw new \RuntimeException('BGG API returned error: ' . $xml->error->message ?? 'unknown');
        }
        if ($xml->getName() === 'message') {
            throw new \RuntimeException('BGG API returned message: ' . trim((string) $xml));
        }
        return $xml;
    }

    /**
     * Get a user's owned collection.
     *
     * @param string $username The user to get the collection for.
     * @param string $pattern  Optionally filter the set to games matching this pattern.
     *
     * @return \Generator An iterator over the user's collected items.
     *
     * @throws \RuntimeException If the user owns no games.
     */
    public function getCollectionForUser(string $username, string $pattern = null): \Generator
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
            throw new \RuntimeException('User owns no games.');
        }
        foreach ($collection as $game) {
            if ((string) $game->attributes()['subtype'] !== 'boardgame') {
                continue;
            }
            if ($pattern !== null) {
                if (strpos($pattern, '/') === 0) {
                    // Treat $pattern as a regex if it starts with a slash.
                    if (preg_match($pattern, (string) $game->name) !== 1) {
                        continue;
                    }
                } else {
                    // Otherwise, just look for a substring.
                    if (stripos((string) $game->name, $pattern) === false) {
                        continue;
                    }
                }
            }
            $row = [
                'id' => (int) $game->attributes()['objectid'],
                'name' => preg_replace('/\s+/', ' ', (string) $game->name),
                'yearPublished' => (int) $game->yearpublished,
                'image' => (string) $game->image,
                'thumbnail' => (string) $game->thumbnail,
                'minPlayers' => (int) $game->stats->attributes()['minplayers'],
                'maxPlayers' => (int) $game->stats->attributes()['maxplayers'],
                'minPlayTime' => (int) $game->stats->attributes()['minplaytime'],
                'maxPlayTime' => (int) $game->stats->attributes()['maxplaytime'],
                'playTime' => (int) $game->stats->attributes()['playingtime'],
                'geekRating' => (float) $game->stats->rating->bayesaverage->attributes()['value'],
                'averageRating' => (float) $game->stats->rating->average->attributes()['value'],
                'numVoters' => (int) $game->stats->rating->usersrated->attributes()['value'],
            ];
            foreach ($game->stats->rating->ranks->rank as $rank) {
                if ((string) $rank->attributes()['name'] === 'boardgame') {
                    $row['rank'] = (int) $rank->attributes()['value'];
                    break;
                }
            }
            $row += $this->getDetailsForThing($row['id']);
            ksort($row);
            $row['hash'] = md5((string) json_encode($row));
            yield $row;
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
                    if ((string) $result->attributes()['value'] === 'Best') {
                        $recommended[(string) $results->attributes()['numplayers']] =
                            (int) $result->attributes()['numvotes'];
                        break;
                    }
                }
            }
            arsort($recommended);
            return [
                'recommendedPlayers' => key($recommended),
                'weight' => (float) $thing->statistics->ratings->averageweight->attributes()['value'],
                'description' => (string) $thing->description,
            ];
        }
        return [];
    }

}
