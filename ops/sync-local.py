#!/usr/bin/env python3
"""Sync tracked source code into the local EU Docker volume; preserve uploads."""
import os, subprocess
from pathlib import Path
os.environ.setdefault('DOCKER_HOST','unix:///mnt/wsl/docker-desktop/shared-sockets/host-services/docker.proxy.sock')
root=Path(__file__).resolve().parents[1]
archive=subprocess.Popen(['tar','--exclude=.git','--exclude=./wp-content/uploads','--exclude=./wp-content/debug.log','-C',str(root),'-cf','-','.'],stdout=subprocess.PIPE)
subprocess.run(['docker','exec','-i','pilloway_eu-wordpress-1','tar','-xf','-','-C','/var/www/html'],stdin=archive.stdout,check=True)
archive.stdout.close()
if archive.wait(): raise SystemExit('Archive failed')
subprocess.run(['docker','exec','pilloway_eu-wordpress-1','chown','-R','www-data:www-data','/var/www/html/wp-content/plugins','/var/www/html/wp-content/themes'],check=True)
print('EU source synchronized; uploads preserved.')
