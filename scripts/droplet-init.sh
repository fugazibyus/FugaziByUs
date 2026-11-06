#!/usr/bin/env bash
# One-time setup for a new staging or production Droplet (Ubuntu 24.04 LTS). Run it as root:
#
#   scp scripts/droplet-init.sh root@<ip>:
#   ssh root@<ip> "bash droplet-init.sh '$(gh variable get DEPLOY_SSH_PUBLIC_KEY)'"
#
# It is safe to run again. What it does:
#   - installs Docker (if the image doesn't have it), security updates and a swap file
#   - creates the `deploy` user that GitHub Actions logs in as (key-only SSH, in the docker group)
#   - turns off SSH passwords, opens only ports 22, 80 and 443
#   - prepares /opt/fugazi (the deploy workflow fills it) and a nightly database backup
#
# The deploy user is in the docker group, which is as powerful as root on this server.
# Its private key lives only in the DEPLOY_SSH_KEY GitHub secret.
set -euo pipefail

DEPLOY_PUBLIC_KEY=${1:-}
APP_DIR=/opt/fugazi

[[ $EUID -eq 0 ]] || { echo "Run this as root." >&2; exit 1; }
[[ "$DEPLOY_PUBLIC_KEY" == ssh-* ]] || { echo "Pass the deploy public key: bash droplet-init.sh 'ssh-ed25519 AAAA… fugazi-deploy'" >&2; exit 1; }

export DEBIAN_FRONTEND=noninteractive
step() { printf '\n==> %s\n' "$*"; }

step "Security updates"
apt-get update -q
apt-get upgrade -yq
apt-get install -yq ca-certificates curl jq ufw unattended-upgrades
dpkg-reconfigure -f noninteractive unattended-upgrades

step "Docker"
if ! command -v docker >/dev/null || ! docker compose version >/dev/null 2>&1; then
  install -m 0755 -d /etc/apt/keyrings
  curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
  chmod a+r /etc/apt/keyrings/docker.asc
  # shellcheck source=/dev/null
  echo "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu $(. /etc/os-release && echo "$VERSION_CODENAME") stable" >/etc/apt/sources.list.d/docker.list
  apt-get update -q
  apt-get install -yq docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
fi
# Rotate container logs so they can't fill the disk.
if [[ ! -f /etc/docker/daemon.json ]]; then
  echo '{ "log-driver": "json-file", "log-opts": { "max-size": "10m", "max-file": "5" } }' >/etc/docker/daemon.json
  systemctl restart docker
fi
systemctl enable --now docker

step "Swap (2 GB Droplets have little headroom while images are pulled)"
if ! swapon --show | grep -q .; then
  fallocate -l 2G /swapfile
  chmod 600 /swapfile
  mkswap /swapfile
  swapon /swapfile
  grep -q '^/swapfile' /etc/fstab || echo '/swapfile none swap sw 0 0' >>/etc/fstab
  sysctl -w vm.swappiness=10 >/dev/null
  echo 'vm.swappiness=10' >/etc/sysctl.d/60-swappiness.conf
fi

step "Deploy user"
id deploy >/dev/null 2>&1 || adduser --disabled-password --gecos '' deploy
usermod -aG docker deploy
install -d -m 700 -o deploy -g deploy /home/deploy/.ssh
echo "$DEPLOY_PUBLIC_KEY" >/home/deploy/.ssh/authorized_keys
chown deploy:deploy /home/deploy/.ssh/authorized_keys
chmod 600 /home/deploy/.ssh/authorized_keys

step "SSH: keys only"
cat >/etc/ssh/sshd_config.d/10-fugazi.conf <<'EOF'
PasswordAuthentication no
KbdInteractiveAuthentication no
PermitRootLogin prohibit-password
EOF
sshd -t
systemctl reload ssh

step "Firewall: 22, 80 and 443 only"
ufw allow OpenSSH
ufw allow 80/tcp
ufw allow 443/tcp
ufw --force enable

step "App folder and nightly backup"
install -d -m 750 -o deploy -g deploy "$APP_DIR" "$APP_DIR/backups"
echo "30 3 * * * deploy [ -x $APP_DIR/backup.sh ] && $APP_DIR/backup.sh >> $APP_DIR/backups/backup.log 2>&1" >/etc/cron.d/fugazi-backup

step "Done"
echo "Host key line for the DEPLOY_KNOWN_HOSTS environment variable:"
ssh-keyscan -t ed25519 localhost 2>/dev/null | sed "s/^localhost/$(curl -fsS --max-time 5 http://169.254.169.254/metadata/v1/interfaces/public/0/ipv4/address || hostname -I | cut -d' ' -f1)/"
