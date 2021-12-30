#!/bin/bash

source ../vars.sh

onError()
{
	exit 1;
}
trap 'onError' ERR

cd images

DIR=$(ls | egrep 'wb-*')

for d in $DIR; do 
	cd $d

	echo ""
	echo "*******************************************"
	echo "> $d"
	echo "*******************************************"
	echo ""
	
	sudo docker build -t $d:latest -f "$PWD/Dockerfile" "$PWD"

	cd ..
done

cd ..
