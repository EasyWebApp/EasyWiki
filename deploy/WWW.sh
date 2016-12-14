#!/usr/bin/env bash


mkdir /iData/Web;

rm -rf /var/www;

ln -s /iData/Web /var/www;

cp . /var/www;