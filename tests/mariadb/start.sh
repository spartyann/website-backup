#!/bin/bash

cd $(dirname $0)

set -e

sudo docker-compose up -d
