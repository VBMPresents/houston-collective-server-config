sudo apt update && sudo apt upgrade -y
sudo apt install -y ufw fail2ban unattended-upgrades apt-listchanges
sudo dpkg-reconfigure -plow unattended-upgrades
sudo useradd -m -s /bin/bash -c "Stream Admin User" streamadmin
sudo usermod -aG sudo streamadmin
sudo nano /etc/ssh/sshd_config
sudo passwd streamadmin
sudo systemctl restart sshd
sudo ufw default deny incoming && sudo ufw default allow outgoing
sudo ufw allow 2222/tcp
sudo ufw allow 80/tcp && sudo ufw allow 443/tcp
sudo ufw allow 1935/tcp && sudo ufw allow 1985/tcp && sudo ufw allow 8080/tcp && sudo ufw allow 8000/udp && sudo ufw allow 10080/udp
sudo ufw enable
sudo apt install -y wget curl git build-essential cmake sqlite3 htop tree vim nano
sudo apt install -y python3 python3-pip python3-venv
sudo apt install -y php8.1 php8.1-fpm php8.1-sqlite3 php8.1-curl php8.1-json php8.1-mbstring
sudo apt install -y php8.1 php8.1-fpm php8.1-sqlite3 php8.1-curl php8.1-mbstring
sudo apt install -y nginx
sudo apt install -y ffmpeg mediainfo
curl -fsSL https://deb.nodesource.com/setup_lts.x | sudo -E bash - && sudo apt install -y nodejs
sudo pip3 install schedule sqlalchemy pillow python-magic
sudo mkdir -p /opt/streamserver/{content/{incoming,processed,archive},database,scripts,web/{admin,public,api,assets},config,logs,temp,srs/{hls,conf,logs}}
sudo chown -R streamadmin:streamadmin /opt/streamserver
sudo chmod -R 755 /opt/streamserver && sudo chmod -R 777 /opt/streamserver/{logs,temp,content,srs/hls}
cd /tmp && wget https://github.com/ossrs/srs/releases/download/v5.0-r2/srs-server-5.0-r2-linux-x86_64.tar.gz
docker --version
sudo apt update
sudo apt install docker.io
sudo systemctl start docker
sudo systemctl enable docker
