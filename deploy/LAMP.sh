#!/usr/bin/env bash


apt-get update;

apt-get install -y lamp-server^;

/etc/init.d/apache2 restart;