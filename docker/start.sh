#!/bin/bash
# Fly.io sets the PORT env variable - update Apache to listen on it
sed -i "s/Listen 80/Listen ${PORT:-8080}/" /etc/apache2/ports.conf
sed -i "s/:80/:${PORT:-8080}/" /etc/apache2/sites-available/000-default.conf

# Start Apache in foreground
apache2-foreground
