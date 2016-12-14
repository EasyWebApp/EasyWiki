#!/usr/bin/env bash


fdisk /dev/vdb;
mkfs.ext3 /dev/vdb1;

mkdir /iData;
mount /dev/vdb1 /iData;

echo "/dev/vdb1 /iData ext3 defaults 0 0"  >>  /etc/fstab;

chmod 775 /iData