CREATE TABLE game (
    id INT NOT NULL,
    name TEXT NOT NULL,
    yearPublished INT,
    image TEXT,
    thumbnail TEXT,
    minPlayers INT,
    maxPlayers INT,
    recommendedPlayers INT,
    minPlayTime INT,
    maxPlayTime INT,
    playTime INT,
    geekRating FLOAT,
    averageRating FLOAT,
    numVoters INT,
    rank INT,
    weight FLOAT,
    description TEXT,
    hash TEXT NOT NULL,
    PRIMARY KEY (id)
);

CREATE INDEX game_name ON game (name);
CREATE UNIQUE INDEX game_hash ON game (hash);

CREATE TABLE own (
    username TEXT NOT NULL,
    gameId INT NOT NULL REFERENCES game(id) ON DELETE CASCADE ON UPDATE CASCADE,
    PRIMARY KEY (username, gameId)
);
