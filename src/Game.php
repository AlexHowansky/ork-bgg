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

use RuntimeException;

/**
 * Game class.
 *
 * @property-read float $averageRating
 * @property-read bool $cooperative
 * @property-read string $description
 * @property-read float $geekRating
 * @property-read string $hash
 * @property-read int $id
 * @property-read string $image
 * @property-read int $maxPlayers
 * @property-read int $maxPlayTime
 * @property-read int $minPlayers
 * @property-read int $minPlayTime
 * @property-read string $name
 * @property-read int $numVoters
 * @property-read string $players
 * @property-read int $playTime
 * @property-read int $rank
 * @property-read int $recommendedPlayers
 * @property-read string $thumbnail
 * @property-read string $url
 * @property-read float $weight
 * @property-read int $yearPublished
 */
class Game
{

    // These fields are not included in the `GET /collection` API response
    // packet, and are lazy-loaded from the `GET /thing` API endpoint only
    // when needed.
    private const array LAZY_LOAD_FIELDS = [
        'cooperative',
        'description',
        'hash',
        'recommendedPlayers',
        'weight',
    ];

    private const string DETAIL_PAGE_URL = 'https://boardgamegeek.com/boardgame/';

    public function __construct(private array $data)
    {
        ksort($this->data);
    }

    public function __get(string $name): mixed
    {
        $getter = 'get' . ucfirst($name);
        if (method_exists($this, $getter) === true) {
            return $this->$getter();
        }
        if (in_array($name, self::LAZY_LOAD_FIELDS) === true) {
            $this->lazyLoad();
        }
        return $this->data[$name] ?? throw new RuntimeException('No such attribute: ' . $name);
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    public function getPlayers(bool $long = false): string
    {
        return $this->minPlayers === $this->maxPlayers
            ? (string) $this->minPlayers
            : sprintf(
                '%d - %d (%s%d)',
                $this->minPlayers,
                $this->maxPlayers,
                $long === true ? 'best ' : '', $this->recommendedPlayers
            );
    }

    public function getPlayTime(): string
    {
        return $this->minPlayTime === $this->maxPlayTime
            ? (string) $this->minPlayTime
            : sprintf('%d - %d', $this->minPlayTime, $this->maxPlayTime);
    }

    public function getUrl(): string
    {
        return self::DETAIL_PAGE_URL . $this->id;
    }

    protected function lazyLoad(): void
    {
        if (array_key_exists('hash', $this->data) === false) {
            $this->data += (new Bgg())->getDetailsForThing($this->id);
            ksort($this->data);
            $this->data['hash'] = md5((string) json_encode($this->data, JSON_THROW_ON_ERROR));
            ksort($this->data);
        }
    }

    public function toArray(): array
    {
        $this->lazyLoad();
        return $this->data;
    }

}
