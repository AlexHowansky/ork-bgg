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

use PDO;
use PDOException;
use RuntimeException;

/**
 * Database abstraction.
 */
class Db
{

    protected PDO $pdo;

    public function __construct()
    {
        $this->pdo = new PDO($this->getDsn());
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->init();
    }

    /**
     * Indicate that a user owns a game.
     *
     * @param string $username The user who owns the game.
     * @param int $gameId The game the user owns.
     *
     * @return Db Allow method chaining.
     */
    public function addOwnage(string $username, int $gameId): Db
    {
        $sth = $this->pdo->prepare('INSERT INTO own (username, gameId) VALUES (:username, :gameId)');
        $sth->execute([
            'username' => $username,
            'gameId' => $gameId,
        ]);
        return $this;
    }

    /**
     * Delete games that a user no longer owns.
     *
     * @param string $username The user who no longer owns the games.
     * @param array $ownedGameIds A list of games the user owns.
     *
     * @return Db Allow method chaining.
     */
    public function deleteNotOwned(string $username, array $ownedGameIds): Db
    {
        $sth = $this->pdo->prepare('DELETE FROM own WHERE username = :username AND gameId = :gameId');
        foreach (array_diff($this->getOwnedGameIds($username), $ownedGameIds) as $ownedGameId) {
            printf("deleted   [%d]\n", $ownedGameId);
            $sth->execute([$username, $ownedGameId]);
        }
        return $this;
    }

    /**
     * Get the database directory.
     *
     * @return string The database directory.
     *
     * @throws RuntimeException On error.
     */
    protected function getDatabaseDir(): string
    {
        $dir = realpath(__DIR__ . '/../data/');
        if ($dir === false) {
            throw new RuntimeException('Unable to locate data directory.');
        }
        return $dir;
    }

    /**
     * Get the database DSN.
     *
     * @return string The database DSN.
     */
    protected function getDsn(): string
    {
        return 'sqlite:' . $this->getDatabaseDir() . '/bgg.sq3';
    }

    /**
     * Get a game.
     *
     * @param int $id The game to get.
     *
     * @return array The game details.
     */
    public function getGame(int $id): array
    {
        $sth = $this->pdo->prepare('SELECT * FROM game WHERE id = :id');
        $sth->execute(['id' => $id]);
        return (array) ($sth->fetch(\PDO::FETCH_ASSOC) ?: []);
    }

    /**
     * Search for games.
     *
     * @param array $params The search criteria.
     *
     * @return array The list of matching games.
     */
    public function getGames(array $params = []): array
    {
        $where = [];
        $bind = [];
        $sql = 'SELECT DISTINCT game.* FROM game';
        if (empty($params['username'] ?? null) === false) {
            $sql .= ' JOIN own ON own.gameId = game.id';
            $where[] = 'own.username = :username';
            $bind['username'] = $params['username'];
        }
        $this->whereCooperative($params, $where, $bind);
        $this->whereExpansions($params, $where);
        $this->whereMaxPlayTime($params, $where, $bind);
        $this->whereMaxWeight($params, $where, $bind);
        $this->whereNumPlayers($params, $where, $bind);
        $this->whereSearch($params, $where, $bind);
        if (empty($where) === false) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }
        $sql .= ' ORDER BY geekRating DESC';
        $sth = $this->pdo->prepare($sql);
        $sth->execute($bind);
        return $sth->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get the games that a user owns.
     *
     * @param string $username The user to get the owned games for.
     *
     * @return array A list of games the user owns.
     */
    public function getOwnedGameIds(string $username): array
    {
        $sth = $this->pdo->prepare(
            'SELECT id FROM game JOIN own ON own.gameId = game.id WHERE own.username = :username'
        );
        $sth->execute(['username' => $username]);
        return $sth->fetchAll(PDO::FETCH_COLUMN) ?: [];
    }

    /**
     * Get the name of the SQL source file for database creation.
     *
     * @return string The name of the SQL source file for database creation.
     *
     * @throws RuntimeException On error.
     */
    protected function getSqlFile(): string
    {
        $sqlFile = $this->getDatabaseDir() . '/bgg.sql';
        if (file_exists($sqlFile) === false) {
            throw new RuntimeException('Unable to locate SQL file.');
        }
        return $sqlFile;
    }

    /**
     * Get a list of users.
     *
     * @return array The list of users.
     *
     * @throws RuntimeException On error.
     */
    public function getUsers(): array
    {
        $sth = $this->pdo->query('SELECT DISTINCT username FROM own ORDER BY username');
        if ($sth === false) {
            throw new RuntimeException('getUsers() query failed.');
        }
        $result = $sth->fetchAll(\PDO::FETCH_COLUMN);
        if (empty($result) === true) {
            throw new RuntimeException('getUsers() fetch failed.');
        }
        return $result;
    }

    /**
     * Initialize the database.
     *
     * @return Db Allow method chaining.
     */
    protected function init(): Db
    {
        try {
            $this->pdo->query('SELECT 1 FROM game LIMIT 1');
        } catch (PDOException) {
            $this->pdo->exec((string) file_get_contents($this->getSqlFile()));
        }
        return $this;
    }

    /**
     * Insert a new game record.
     *
     * @param array $game The game details.
     *
     * @return Db Allow method chaining.
     */
    protected function insertGame(array $game): Db
    {
        $sth = $this->pdo->prepare(
            'INSERT INTO game (
                 id, name, yearPublished, image, thumbnail, minPlayers, maxPlayers, recommendedPlayers,
                 minPlayTime, maxPlayTime, playTime, geekRating, averageRating, numVoters, rank, weight,
                 cooperative, description, hash
             ) VALUES (
                 :id, :name, :yearPublished, :image, :thumbnail, :minPlayers, :maxPlayers, :recommendedPlayers,
                 :minPlayTime, :maxPlayTime, :playTime, :geekRating, :averageRating, :numVoters, :rank, :weight,
                 :cooperative, :description, :hash
             )'
        );
        $sth->execute($game);
        return $this;
    }

    /**
     * Update a game record.
     *
     * @param array $game The game details.
     *
     * @return Db Allow method chaining.
     */
    protected function updateGame(array $game): Db
    {
        $sth = $this->pdo->prepare(
            'UPDATE game SET
                 name = :name,
                 yearPublished = :yearPublished,
                 image = :image,
                 thumbnail = :thumbnail,
                 minPlayers = :minPlayers,
                 maxPlayers = :maxPlayers,
                 recommendedPlayers = :recommendedPlayers,
                 minPlayTime = :minPlayTime,
                 maxPlayTime = :maxPlayTime,
                 playTime = :playTime,
                 geekRating = :geekRating,
                 averageRating = :averageRating,
                 numVoters = :numVoters,
                 rank = :rank,
                 weight = :weight,
                 cooperative = :cooperative,
                 description = :description,
                 hash = :hash
             WHERE id = :id'
        );
        $sth->execute($game);
        return $this;
    }

    /**
     * Update/insert a game.
     *
     * @param array $game The game details.
     *
     * @return Db Allow method chaining.
     */
    public function upsertGame(array $game): Db
    {
        $dbGame = $this->getGame($game['id']);
        if (empty($dbGame) === true) {
            printf("added     [%d] %s\n", $game['id'], $game['name']);
            $this->insertGame($game);
        } elseif ($dbGame['hash'] !== $game['hash']) {
            printf("updated   [%d] %s\n", $game['id'], $game['name']);
            $this->updateGame($game);
        } else {
            printf("unchanged [%d] %s\n", $game['id'], $game['name']);
        }
        return $this;
    }

    /**
     * Update/insert game ownership.
     *
     * @param string $username The user who owns the game.
     * @param int $gameId The game that the user owns.
     *
     * @return Db Allow method chaining.
     */
    public function upsertOwnage(string $username, int $gameId): Db
    {
        if ($this->userOwnsGame($username, $gameId) === true) {
            echo "    already owned\n";
        } else {
            echo "    newly acquired\n";
            $this->addOwnage($username, $gameId);
        }
        return $this;
    }

    /**
     * Does a user own a game?
     *
     * @param string $username The username to check.
     * @param int $gameId The game to check.
     *
     * @return bool True if the user owns the game.
     */
    public function userOwnsGame(string $username, int $gameId): bool
    {
        $sth = $this->pdo->prepare('SELECT * FROM own WHERE username = :username AND gameId = :gameId');
        $sth->execute([
            'username' => $username,
            'gameId' => $gameId,
        ]);
        return $sth->fetch() !== false;
    }

    /**
     * Possibly add a WHERE clause for cooperative games.
     *
     * @param array $params The input parameters array.
     * @param array $where The output WHERE clause array.
     * @param array $bind The output bind values.
     */
    protected function whereCooperative(array $params, array &$where, array &$bind): void
    {
        if (($params['cooperative'] ?? '') !== '') {
            $where[] = 'cooperative = :cooperative';
            $bind['cooperative'] = (bool) $params['cooperative'];
        }
    }

    /**
     * Possibly add a WHERE clause for cooperative games.
     *
     * @param array $params The input parameters array.
     * @param array $where The output WHERE clause array.
     */
    protected function whereExpansions(array $params, array &$where): void
    {
        if (empty($params['expansions'] ?? null) === true) {
            $where[] = 'rank > 0';
        }
    }

    /**
     * Possibly add a WHERE clause for max play time.
     *
     * @param array $params The input parameters array.
     * @param array $where The output WHERE clause array.
     * @param array $bind The output bind values.
     */
    protected function whereMaxPlayTime(array $params, array &$where, array &$bind): void
    {
        if (empty($params['maxPlayTime'] ?? null) === false) {
            $where[] = 'maxPlayTime <= :maxPlayTime';
            $bind['maxPlayTime'] = $params['maxPlayTime'];
        }
    }

    /**
     * Possibly add a WHERE clause for max weight.
     *
     * @param array $params The input parameters array.
     * @param array $where The output WHERE clause array.
     * @param array $bind The output bind values.
     */
    protected function whereMaxWeight(array $params, array &$where, array &$bind): void
    {
        if (empty($params['maxWeight'] ?? null) === false) {
            $where[] = 'weight <= :maxWeight';
            $bind['maxWeight'] = $params['maxWeight'];
        }
    }

    /**
     * Possibly add a WHERE clause for number of players.
     *
     * @param array $params The input parameters array.
     * @param array $where The output WHERE clause array.
     * @param array $bind The output bind values.
     */
    protected function whereNumPlayers(array $params, array &$where, array &$bind): void
    {
        if (empty($params['numPlayers'] ?? null) === false) {
            $bind['numPlayers'] = $params['numPlayers'];
            if (($params['numPlayersType'] ?? null) === 'suggested') {
                $where[] = 'recommendedPlayers = :numPlayers';
            } else {
                $where[] = 'minPlayers <= :numPlayers AND maxPlayers >= :numPlayers';
            }
        }
    }

    /**
     * Possibly add a WHERE clause for keyword search.
     *
     * @param array $params The input parameters array.
     * @param array $where The output WHERE clause array.
     * @param array $bind The output bind values.
     */
    protected function whereSearch(array $params, array &$where, array &$bind): void
    {
        if (empty($params['search'] ?? null) === false) {
            $where[] = 'name LIKE :search';
            $bind['search'] = '%' . $params['search'] . '%';
        }
    }

}
