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
 * @property-read int $playTime
 * @property-read int $rank
 * @property-read int $recommendedPlayers
 * @property-read string $thumbnail
 * @property-read string $url
 * @property-read float $weight
 * @property-read int $yearPublished
 */
readonly class Game
{
    private const string DETAIL_PAGE_URL = 'https://boardgamegeek.com/boardgame/';

    public function __construct(private array $data)
    {
    }

    public function __get(string $name): mixed
    {
        return method_exists($this, $name) === true
            ? $this->$name()
            : $this->data[$name] ?? throw new RuntimeException('No such attribute.');
    }

    public function __isset(string $name): bool
    {
        return array_key_exists($name, $this->data);
    }

    public function players(bool $long = false): string
    {
        return $this->minPlayers === $this->maxPlayers
            ? (string) $this->minPlayers
            : sprintf(
                '%d - %d (%s%d)',
                $this->minPlayers,
                $this->maxPlayers,
                $long ? 'best ' : '', $this->recommendedPlayers
            );
    }

    public function playTime(): string
    {
        return $this->minPlayTime === $this->maxPlayTime
            ? (string) $this->minPlayTime
            : sprintf('%d - %d', $this->minPlayTime, $this->maxPlayTime);
    }

    public function url(): string
    {
        return self::DETAIL_PAGE_URL . $this->id;
    }

}
