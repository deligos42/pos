#!/bin/bash
set -e

echo "Enabled Apache modules:"
ls /etc/apache2/mods-enabled || true

# Show which MPM is loaded
apachectl -M | grep mpm || true
