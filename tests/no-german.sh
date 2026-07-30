#!/usr/bin/env sh
# Flags German prose left in the source tree.
#
# The plugin's source language is English; German ships as a translation
# under /languages. This catches the common giveaways - articles, particles,
# umlaut transliterations and the words that turn up most in our own
# comments. It is a heuristic, not a parser: it will not catch everything,
# but it reliably catches a file that was forgotten.
#
#   sh tests/no-german.sh

cd "$(dirname "$0")/.."

# Function words only, matched at word boundaries. Nouns are deliberately
# left out: "fahrzeuge" is the post type slug and appears throughout by
# design, and "in", "so", "die" inside English words would be noise.
muster='\b(der|die|das|dem|den|des|und|oder|nicht|wird|werden|wurde|ist|sind|war|kein|keine|keinen|eine|einen|einem|einer|auch|noch|schon|beim|vom|zum|zur|durch|ueber|über|fuer|für|muss|kann|soll|sollte|damit|weil|wenn|dann|nur|sich|dass|aber|hier|dort|jede|jeder|jedes|ohne|gegen|zwischen|statt|bereits|deshalb|dadurch|sowie|etwa)\b'

treffer=$(grep -rniE "$muster" \
	--include='*.php' --include='*.md' --include='*.txt' \
	--include='*.xml' --include='*.yml' --include='*.css' --include='*.sh' \
	--include='*.json' \
	--exclude-dir=.git --exclude-dir=languages --exclude-dir=vendor \
	--exclude='no-german.sh' \
	. 2>/dev/null)

if [ -n "$treffer" ]; then
	echo "$treffer"
	echo
	echo "German prose found in the source tree. English is the source language;"
	echo "translations belong under /languages."
	exit 1
fi

echo "No German prose found."
