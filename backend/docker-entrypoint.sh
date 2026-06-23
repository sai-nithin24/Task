#!/bin/bash
# Railway injects a PORT environment variable.
# Apache must listen on that port instead of the default 80.
# This script patches Apache's port config at container startup.

PORT="${PORT:-80}"

# Update Apache to listen on $PORT
sed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/:80>/:${PORT}>/" /etc/apache2/sites-available/000-default.conf

echo "Starting Apache on port ${PORT}..."
exec apache2-foreground
