# Ork BGG

Ork BGG is a simple web app to catalog, browse, and search your board game
collection. It pulls data from [Board Game Geek](http://boardgamegeek.com) and
caches it in a local SQLite database for quick searches.

## Installation

```sh
git clone https://github.com/AlexHowansky/ork-bgg.git
composer install
```

Run a development server with `composer go` or point your webserver's document
root to the `public` directory.

## Data

Run `bin/sync <bgg username>` to pull/sync your collection from BGG. The
username is case sensitive. Any game in your "Own" collection on BGG will be
copied into the local database for the indicated user. As the BGG API has a
fairly restrictive usage throttle, this may take some time.
