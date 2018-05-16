#!/bin/bash

service memcached start
/usr/sbin/apache2ctl -D FOREGROUND
