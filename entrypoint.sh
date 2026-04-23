#!/bin/bash
set -e
rm -f /etc/apache2/mods-enabled/mpm_event.conf /etc/apache2/mods-enabled/mpm_event.load
rm -f /etc/apache2/mods-enabled/mpm_worker.conf /etc/apache2/mods-enabled/mpm_worker.load
ln -sf /etc/apache2/mods-available/mpm_prefork.load /etc/apache2/mods-enabled/mpm_prefork.load
ln -sf /etc/apache2/mods-available/mpm_prefork.conf /etc/apache2/mods-enabled/mpm_prefork.conf
sed -i "s/Listen 80/Listen $PORT/" /etc/apache2/ports.conf
sed -i "s/*:80>/*:$PORT>/" /etc/apache2/sites-enabled/000-default.conf
exec apache2-foreground
