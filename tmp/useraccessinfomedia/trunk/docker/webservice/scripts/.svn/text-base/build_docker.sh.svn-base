#!/bin/bash

# Stop running container - suppress errors (if container is not there)
docker ps -q --filter ancestor="docker-d.dbc.dk/infomedia" | xargs -r docker stop
# Remove container
docker ps -aq --filter ancestor="docker-d.dbc.dk/infomedia"| xargs -r docker rm
# CLEAN UP - remove unused images

# NOTICE be carefull with this if running elsewhere than dscrum.dbc.dk
# docker image prune deletes ALL unused images - might delete something not to be deleted
# docker image prune -f

# build image
docker build -t docker-d.dbc.dk/infomedia:latest .
# Run container - on port 9999
docker run -p 9999:80 --name infomedia docker-d.dbc.dk/infomedia:latest
