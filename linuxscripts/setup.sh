#!/bin/bash

# Global Variables
DBUSER='postgres'
COMPASS=$(date | md5sum | head -c 10)
DBPASS=$COMPASS
PIUSER='pi'
PIGROUP='pi'
PIPASS=$COMPASS
HOSTNAME='home-hub'
FINDCOMMENT='# #'
REPLACECOMMENT=''

# *********************************************************
# These variables need to be customised to your environment
MYIP='0.0.0.0'
MAILSERVER='your.mail.server'
EMAILSENDER='valid@example.com'
TEXTPROVIDER='{0}@your.text.provider'
ADMINRECIPIENT='admin@example.com'
DATAPOINTKEY='your-data-point-key'
DATAPOINTLOCATION='0000'
# *********************************************************

# Warn about serial console connections
echo "Ensure NO Serial devices are connected to RXD..." >$(tty)
echo

# Change pi password
echo "$PIUSER:$PIPASS" | chpasswd

# Inform user of the password
echo "*** THE $PIUSER USER'S PASSWORD HAS BEEN CHANGED TO $PIPASS ***" >$(tty)
echo

# Update
apt-get update

# Software - Pi OS #

# Set the hostname
echo $HOSTNAME > /etc/hostname
sed -i '/127.0.1.1\traspberrypi/c\127.0.1.1\t'"$HOSTNAME" /etc/hosts
/etc/init.d/hostname.sh #requires reboot to take effect

# Set the Timezone to London
cp /usr/share/zoneinfo/Europe/London /etc/localtime

# Change the RAM Split to server - one-shot
#requires reboot to take effect
LINE='gpu_mem=16'
FILE=/boot/config.txt
grep -q "$LINE" "$FILE" || echo "$LINE" >> "$FILE"

# create a directory for the python scripts and give the pi user access
cd /usr/local/bin
mkdir -p code/controller
chown $PIUSER:$PIGROUP code/controller

# Software - Samba #

# install samba
apt-get -q -y install samba samba-common-bin
COMMENT='comment = hub controller code repository'
LINE='[controller]\n'"$COMMENT"'\npath = /usr/local/bin/code/controller\nwriteable = yes\nguest ok = no\n'
FILE=/etc/samba/smb.conf
grep -q "$COMMENT" "$FILE" || printf "$LINE" >> "$FILE"
COMMENT='comment = script repository'
LINE='[scripts]\n'"$COMMENT"'\npath = /usr/local/bin\nwriteable = yes\nguest ok = no\n'
grep -q "$COMMENT" "$FILE" || printf "$LINE" >> "$FILE"
COMMENT='comment = website base'
LINE='[www]\n'"$COMMENT"'\npath = /var/www\nwriteable = yes\nguest ok = no\n'
grep -q "$COMMENT" "$FILE" || printf "$LINE" >> "$FILE"
COMMENT='comment = hub logs'
LINE='[logs]\n'"$COMMENT"'\npath = /var/log\nwriteable = yes\nguest ok = no\n'
grep -q "$COMMENT" "$FILE" || printf "$LINE" >> "$FILE"

(echo $PIPASS; echo $PIPASS) | smbpasswd -s -a $PIUSER
/etc/init.d/samba restart

# Software - Database Server #

# install postgresql
apt-get -q -y install postgresql

# uncomment listen on all interfaces
FIND='/#listen_addresses/s/^#//'
FILE=/etc/postgresql/9.4/main/postgresql.conf
sed -i $FIND $FILE

# listen interfaces localhost => *
LINE='listen_addresses'
FIND='localhost'
REPLACE='*'
sed -i "/$LINE/s/$FIND/$REPLACE/" $FILE

# restart database
/etc/init.d/postgresql restart

# allow client access to all databases
LOCATE="^host\sall\sall\s$MYIP\/32\strust"
LINE="host\tall\tall\t$MYIP/32\ttrust"
FILE=/etc/postgresql/9.4/main/pg_hba.conf
grep -q "$LOCATE" "$FILE" || printf "$LINE" >> "$FILE"

# reload the configuration
/etc/init.d/postgresql reload

# set the postgres user password
sudo -u postgres psql -c 'ALTER USER '"$DBUSER"' PASSWORD '"'""$DBPASS""'"';'
echo "*** THE DATABASE $DBUSER PASSWORD HAS BEEN CHANGED TO $DBPASS ***" >$(tty)
echo

# Software - Hub Database #
cd ~
wget -q http://warrensoft.co.uk/home-hub/database/scripts/hub-skeleton.sql
PGPASSWORD=$DBPASS psql -h localhost -U $DBUSER -d postgres -f hub-skeleton.sql

# Website - Install Apache #
apt-get -q -y install apache2

# create a directory for the database connection script, if not existing
mkdir -p /var/www/private_html
# and give the linux user access to all website directories
chown -R $PIUSER:$PIGROUP /var/www/

# install PHP, Pear MDB2 and psql
apt-get -q -y install php5 libapache2-mod-php5 php-pear php5-pgsql
pear install MDB2
pear install pear/MDB2#pgsql

# turn on PHP Display Errors
sed -i '/display_errors = Off/c\display_errors = On' /etc/php5/apache2/php.ini

# restart apache
service apache2 restart

# download db connection
sudo -u $PIUSER wget -q -P /var/www/private_html http://www.warrensoft.co.uk/home-hub/code/website/private_html/hub_connect.php

# update password
FILE=/var/www/private_html/hub_connect.php
LINE='$db_pass'
FIND='raspberry'
REPLACE=$DBPASS
sed -i "/$LINE/s/$FIND/$REPLACE/" $FILE

# download skeleton website
sudo -u $PIUSER wget -q -r -nH --cut-dirs=4 --reject "index.html*" -P /var/www/html/ -i /var/www/html/manifest1.txt http://www.warrensoft.co.uk/home-hub/manifests/website/manifest1.txt

# Controller - Install Psycopg
apt-get -q -y install python-psycopg2

# Controller - Install SetupTools
apt-get -q -y install python-setuptools

# Controller - Install pip
easy_install pip

# download skeleton controller
sudo -u $PIUSER wget -q -nH -x --cut-dirs=3 -P /usr/local/bin/code/controller/ -i /usr/local/bin/code/controller/manifest1.txt http://www.warrensoft.co.uk/home-hub/manifests/controller/manifest1.txt

# update password
FILE=/usr/local/bin/code/controller/hub_connect.py
LINE='"host='
FIND='raspberry'
REPLACE=$DBPASS
sed -i "/$LINE/s/$FIND/$REPLACE/" $FILE

# update rc.local
FIND='exit 0'
FILE=/etc/rc.local
REPLACE=''
ENTRY='# Run the Hub Program in a seperate process'
LINE='\n'"$ENTRY"'\ncd /usr/local/bin/code/controller\nsudo python main_sched.py --log=INFO --slave=false >> hub.log &\n\nexit 0'
grep -q "$ENTRY" "$FILE" || (sudo sed -i "/$FIND/s/$FIND/$REPLACE/" $FILE;printf "$LINE" >> "$FILE")

# Controller - Install git, python tools
apt-get -q -y install git python-dev python-openssl
cd ~
git clone https://github.com/adafruit/Adafruit_Python_DHT.git
cd Adafruit_Python_DHT
python setup.py install

# download am2302 sensor helper
sudo -u $PIUSER wget -q -P /usr/local/bin/code/controller/sensor_helpers http://www.warrensoft.co.uk/home-hub/code/controller/sensor_helpers/am2302.py

# uncomment am2302 in sensor_helpers
SENSHELPERFILE='/usr/local/bin/code/controller/sensor_helpers/__init__.py'
LINE='am2302'
sed -i "/$LINE/s/$FINDCOMMENT/$REPLACECOMMENT/" $SENSHELPERFILE

# download organisation files
sudo -u $PIUSER wget -q -nH --cut-dirs=4 --reject "index.html*" -P /var/www/html/ -i /var/www/html/manifest2.txt http://www.warrensoft.co.uk/home-hub/manifests/website/manifest2.txt

# controller email/txt settings
PGPASSWORD=$DBPASS psql -U $DBUSER -w -d hub -h localhost -c 'update "UserSetting" set "Value" = '"'""$MAILSERVER""'"' WHERE "Name" = '"'"'MailServer'"'"';'
PGPASSWORD=$DBPASS psql -U $DBUSER -w -d hub -h localhost -c 'update "UserSetting" set "Value" = '"'""$HOSTNAME""'"' WHERE "Name" = '"'"'EmailFrom'"'"';'
PGPASSWORD=$DBPASS psql -U $DBUSER -w -d hub -h localhost -c 'update "UserSetting" set "Value" = '"'""$EMAILSENDER""'"' WHERE "Name" = '"'"'EmailSender'"'"';'
PGPASSWORD=$DBPASS psql -U $DBUSER -w -d hub -h localhost -c 'update "UserSetting" set "Value" = '"'""$TEXTPROVIDER""'"' WHERE "Name" = '"'"'TextProvider'"'"';'
PGPASSWORD=$DBPASS psql -U $DBUSER -w -d hub -h localhost -c 'update "UserSetting" set "Value" = '"'""$ADMINRECIPIENT""'"' WHERE "Name" = '"'"'AdminRecipient'"'"';'
PGPASSWORD=$DBPASS psql -U $DBUSER -w -d hub -h localhost -c 'update "UserSetting" set "Value" = True WHERE "Name" = '"'"'SummaryEnabled'"'"';'

# download current values files
sudo -u $PIUSER wget -q -r -nH --cut-dirs=4 --reject "index.html*" -P /var/www/html/ -i /var/www/html/manifest3.txt http://www.warrensoft.co.uk/home-hub/manifests/website/manifest3.txt

# download controller sampler files
sudo -u $PIUSER wget -q -nH -x --cut-dirs=3 -P /usr/local/bin/code/controller/ -i /usr/local/bin/code/controller/manifest2.txt http://www.warrensoft.co.uk/home-hub/manifests/controller/manifest2.txt

# uncomment sampler in main_sched.py
MAINFILE='/usr/local/bin/code/controller/main_sched.py'
LINE='sampler'
sed -i "/$LINE/s/$FINDCOMMENT/$REPLACECOMMENT/" $MAINFILE

# download website graphing files
sudo -u $PIUSER wget -q -nH --cut-dirs=4 --reject "index.html*" -P /var/www/html/ -i /var/www/html/manifest4.txt http://www.warrensoft.co.uk/home-hub/manifests/website/manifest4.txt

# download controller actuator files
sudo -u $PIUSER wget -q -nH -x --cut-dirs=3 -P /usr/local/bin/code/controller/ -i /usr/local/bin/code/controller/manifest3.txt http://www.warrensoft.co.uk/home-hub/manifests/controller/manifest3.txt

# uncomment actuators in main_sched.py
LINE='actuators'
sed -i "/$LINE/s/$FINDCOMMENT/$REPLACECOMMENT/" $MAINFILE

# uncomment timers in main_sched.py
LINE='timers'
sed -i "/$LINE/s/$FINDCOMMENT/$REPLACECOMMENT/" $MAINFILE

# download website actuator files
sudo -u $PIUSER wget -q -nH --cut-dirs=4 --reject "index.html*" -P /var/www/html/ -i /var/www/html/manifest5.txt http://www.warrensoft.co.uk/home-hub/manifests/website/manifest5.txt

# download controller monitor files
sudo -u $PIUSER wget -q -nH -x --cut-dirs=3 -P /usr/local/bin/code/controller/ -i /usr/local/bin/code/controller/manifest4.txt http://www.warrensoft.co.uk/home-hub/manifests/controller/manifest4.txt

# uncomment monitor in main_sched.py
# also enables truncate samples in main_sched
LINE='monitor'
sed -i "/$LINE/s/$FINDCOMMENT/$REPLACECOMMENT/" $MAINFILE

# download website conditions files
sudo -u $PIUSER wget -q -nH --cut-dirs=4 --reject "index.html*" -P /var/www/html/ -i /var/www/html/manifest6.txt http://www.warrensoft.co.uk/home-hub/manifests/website/manifest6.txt

# uncomment rules engine in main_sched.py
LINE='rules_engine'
sed -i "/$LINE/s/$FINDCOMMENT/$REPLACECOMMENT/" $MAINFILE

# download controller impulse file
sudo -u $PIUSER wget -q -P /usr/local/bin/code/controller http://www.warrensoft.co.uk/home-hub/code/controller/impulses.py

# uncomment impulses in main_sched.py
LINE='impulses'
sed -i "/$LINE/s/$FINDCOMMENT/$REPLACECOMMENT/" $MAINFILE

# download controller statistics file
sudo -u $PIUSER wget -q -P /usr/local/bin/code/controller http://www.warrensoft.co.uk/home-hub/code/controller/statistics.py

# uncomment statistics in main_sched.py
LINE='statistics'
sed -i "/$LINE/s/$FINDCOMMENT/$REPLACECOMMENT/" $MAINFILE

###################################
# # # # END OF PROJECT PLAN # # # #
###################################

# download controller remote files
sudo -u $PIUSER wget -q -nH -x --cut-dirs=3 -P /usr/local/bin/code/controller/ -i /usr/local/bin/code/controller/manifest5.txt http://www.warrensoft.co.uk/home-hub/manifests/controller/manifest5.txt

# uncomment remote in sensor_helpers
SENSHELPERFILE='/usr/local/bin/code/controller/sensor_helpers/__init__.py'
LINE='remote'
sed -i "/$LINE/s/$FINDCOMMENT/$REPLACECOMMENT/" $SENSHELPERFILE

# uncomment remote in actuator_helpers
ACTUHELPERFILE='/usr/local/bin/code/controller/actuator_helpers/__init__.py'
sed -i "/$LINE/s/$FINDCOMMENT/$REPLACECOMMENT/" $ACTUHELPERFILE

# Controller - Remote Installs
pip install requests
pip install --upgrade requests-cache

# Controller - Meteorology Installs
pip install python-dateutil
apt-get -q -y install python-lxml

# Meteorology - add DataPoint User Settings
PGPASSWORD=$DBPASS psql -U $DBUSER -w -d hub -h localhost -c 'INSERT INTO "UserSetting"("Name", "Value") VALUES ('"'"'DataPointKey'"'"', '"'""$DATAPOINTKEY""'"');'
PGPASSWORD=$DBPASS psql -U $DBUSER -w -d hub -h localhost -c 'INSERT INTO "UserSetting"("Name", "Value") VALUES ('"'"'DataPointLocation'"'"', '"'""$DATAPOINTLOCATION""'"');'

# download meteorology sensor helper
sudo -u $PIUSER wget -q -P /usr/local/bin/code/controller/sensor_helpers http://www.warrensoft.co.uk/home-hub/code/controller/sensor_helpers/meteorology.py

# Install python serial
apt-get -q -y install python-serial

# Disable Console Serial
# requires reboot to take effect
# cmdline.txt 'console=serial0,115200 ' => ''
FILE='/boot/cmdline.txt'
FIND='console=serial0,115200 '
REPLACE=''
sed -i "/$FIND/s/$FIND/$REPLACE/" $FILE

# Enable Serial Port - one-shot
# requires reboot to take effect
LINE='enable_uart=1'
FILE=/boot/config.txt
grep -q "$LINE" "$FILE" || echo "$LINE" >> "$FILE"

# Disable getty
# requires reboot to take effect
systemctl disable serial-getty@ttyAMA0.service

# download controller bme280 files
sudo -u $PIUSER wget -q -nH -x --cut-dirs=3 -P /usr/local/bin/code/controller/ -i /usr/local/bin/code/controller/manifest6.txt http://www.warrensoft.co.uk/home-hub/manifests/controller/manifest6.txt

# uncomment bme280 in sensor_helpers
LINE='bme280'
sed -i "/$LINE/s/$FINDCOMMENT/$REPLACECOMMENT/" $SENSHELPERFILE

# Dash Button Database Upgrade
PGPASSWORD=$DBPASS psql -U $DBUSER -w -d hub -h localhost -c '
DO
$$
BEGIN
IF not EXISTS (SELECT * 
               FROM information_schema.columns 
               WHERE table_schema='"'"'public'"'"' and table_name='"'"'Impulse'"'"' and column_name='"'"'MacAddress'"'"') THEN
alter table "Impulse" add column "MacAddress" character varying(17) default null ;
else
raise NOTICE '"'"'Already exists'"'"';
END IF;
END
$$
'

# Upgrade website form
sudo -u $PIUSER wget -q -O /var/www/html/impulse.php http://www.warrensoft.co.uk/home-hub/code/website/dash/impulse.php

# Upgrade controller
sudo -u $PIUSER wget -q -O /usr/local/bin/code/controller/main_sched.py http://www.warrensoft.co.uk/home-hub/code/controller/dash/main_sched.py
sudo -u $PIUSER wget -q -O /usr/local/bin/code/controller/impulses.py http://www.warrensoft.co.uk/home-hub/code/controller/dash/impulses.py

# Remove any manifest files
sudo find /usr/local/bin/code/controller/ /var/www/ -type f -name '*manifest*.txt' -delete

echo "*** THE $PIUSER USER'S PASSWORD HAS BEEN CHANGED TO $PIPASS ***" >$(tty)
echo
echo "*** THE DATABASE $DBUSER PASSWORD HAS BEEN CHANGED TO $DBPASS ***" >$(tty)
echo
echo "Pi is Rebooting..." >$(tty)
reboot
