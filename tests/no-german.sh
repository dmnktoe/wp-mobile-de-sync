#!/usr/bin/env sh
cd "$(dirname "$0")/.."

muster='\b(der|das|dem|den|des|und|oder|nicht|wird|werden|wurde|ist|sind|war|kein|keine|keinen|eine|einen|einem|einer|auch|noch|schon|beim|vom|zum|zur|durch|ueber|über|fuer|für|muss|kann|soll|sollte|damit|weil|wenn|dann|nur|sich|dass|aber|hier|dort|jede|jeder|jedes|ohne|gegen|zwischen|statt|bereits|deshalb|dadurch|sowie|etwa)\b'

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
