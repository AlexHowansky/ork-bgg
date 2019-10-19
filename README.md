# Ork BGG

Ork BGG is a simple web app to catalog, browse, and search your board game
collection. It pulls data from [Board Game Geek](http://boardgamegeek.com) and
caches it in a local SQLite database for quick searches.

## Installation

Install like any other web app. See `composer.json` for requirements. Run
`composer install` to install dependencies.

## Data

Run `bin/sync <bgg username>` to pull/sync your collection from BGG. The
username is case sensitive. Any game in your "Own" collection on BGG will be
copied into the local database for the indicated user. As the BGG API has a
fairly restrictive usage throttle, this may take some time.
