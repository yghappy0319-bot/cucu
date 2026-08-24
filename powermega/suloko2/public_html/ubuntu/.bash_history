cd /home
ll
sudo mkdir suloko
ll
sudo chown -R ubuntu:ubuntu /home/suloko
exit;
sudo chown -R ubuntu:ubuntu /etc/apache2
sudo chown -R ubuntu:ubuntu /etc/apache
sudo apt update
sudo apt install software-properties-common ca-certificates lsb-release apt-transport-https
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install php7.2
sudo apt install libapache2-mod-php7.2
php -v
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo systemctl status apache2
sudo chown -R ubuntu:ubuntu /etc/apache2
sudo systemctl restart apache2
sudo apt install libapache2-mod-php7.2
sudo a2enmod php7.2
sudo systemctl restart apache2
sudo apt update
sudo apt install php-mysql
php -v
sudo chown -R ubuntu:ubuntu /etc/php/7.2/apache2/php.in
sudo chown -R ubuntu:ubuntu /etc/php/7.2/apache2/php.ini
sudo systemctl restart apache2
php -m | grep mysqli
php -v
sudo apt install php7.2 php7.2-mysqli
php -v
php -m | grep mysqli
sudo systemctl restart apache2
exit;
php -m | grep json
php -m | grep -E 'pdo_mysql|mysqli'
exit;
php -m | grep curl
sudo apt update
sudo apt install php-curl
sudo systemctl restart apache2   
php -m | grep curl
sudo apt update
sudo apt install php7.2-curl
php -m | grep curl
sudo apt-get update
sudo apt-get install php7.2-mbstring
sudo systemctl restart apache2
exit;
sudo apt install certbot python3-certbot-apache
sudo a2enmod rewrite
sudo systemctl restart apache2
sudo a2enmod ssl
sudo systemctl restart apache2
certbot certonly --webroot -w /home/suloko/public_html -d suloko.com
sudo certbot certonly --webroot -w /home/suloko/public_html -d suloko.com
sudo certbot certonly --webroot -w /home/suloko/public_html -d www.suloko.com
sudo certbot certonly --webroot -w /home/suloko/public_html -d suloko.com
exit;
sudo certbot certonly --webroot -w /home/suloko/public_html -d suloko7.com
sudo certbot certonly --webroot -w /home/suloko/public_html -d www.suloko7.com
sudo systemctl restart apache2
sudo chown -R ubuntu:ubuntu /etc/letsencrypt/live
sudo a2enmod ssl
sudo a2enmod headers
sudo systemctl restart apache2
sudo certbot certonly --webroot -w /home/suloko/public_html -d sulokolink.com
sudo certbot certonly --webroot -w /home/suloko/public_html -d www.sulokolink.com
sudo systemctl restart apache2
exit;
top
exit;
sudo systemctl restart apache2
exit;
sudo systemctl restart apache2
sudo chown -R ubuntu:ubuntu /var/spool/cron/crontabs
sudo systemctl restart apache2
sudo crontab -e
sudo systemctl restart apache2
sudo certbot certonly --webroot -w /home/suloko/public_html -d suloko.com
sudo certbot certonly --webroot -w /home/suloko/public_html -d www.suloko.com
exit;
sudo certbot certonly --webroot -w /home/suloko/public_html -d www.suloko.com
sudo systemctl restart apache2
sudo certbot certonly --webroot -w /home/suloko/public_html -d www.suloko.com
sudo systemctl restart apache2
sudo certbot certonly --webroot -w /home/suloko/public_html -d www.suloko.com
sudo systemctl restart apache2
sudo certbot certonly --webroot -w /home/suloko/public_html -d sulotko.com
sudo certbot certonly --webroot -w /home/suloko/public_html -d www.sulotko.com
sudo systemctl restart apache2
sudo certbot certonly --webroot -w /home/suloko/public_html -d superlottokorea.com
sudo certbot certonly --webroot -w /home/suloko/public_html -d www.superlottokorea.com
sudo systemctl restart apache2
sudo certbot certonly --webroot -w /home/suloko/public_html -d suloko.net
crontab -e
sudo certbot certonly --webroot -w /home/suloko/public_html -d www.suloko.net
sudo systemctl restart apache2
sudo certbot certonly --webroot -w /home/suloko/public_html -d *.sulokor.com
sudo certbot certonly --webroot -w /home/suloko/public_html -d sulokor.com
sudo certbot certonly --webroot -w /home/suloko/public_html -d www.sulokor.com
sudo certbot certonly --webroot -w /home/suloko/public_html -d superlottokorea.net
sudo certbot certonly --webroot -w /home/suloko/public_html -d www.superlottokorea.net
sudo systemctl restart apache2
sudo certbot certonly --webroot -w /home/suloko/public_html -d surokolink.com
sudo certbot certonly --webroot -w /home/suloko/public_html -d www.surokolink.com
sudo systemctl restart apache2
date
sudo systemctl restart apache2
exit;
sudo ufw status
sudo systemctl restart apache2
