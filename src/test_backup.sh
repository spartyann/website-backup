#!/bin/bash
set -e

PARAMS="$@"
cd $(dirname $0)

if [ -z "$PARAMS" ];
then
	CMD="php backup.php"
else
	CMD="php backup.php $PARAMS"
fi

VOL=$(dirname $PWD)

sudo docker run --rm --network host -w "/data/src" -v "$VOL:/data" -ti wb-php $CMD
