!!! WARNING !!!

Should be only used along with

[Comgress64 VPN project](https://github.com/comgress64/vpn) and [Comgress64 VPN Frontend](https://github.com/comgress64/vpn_frontend
)


# vpn_api


#### Install required software
```
apt-get install php
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php -r "if (hash_file('SHA384', 'composer-setup.php') === '669656bab3166a7aff8a7506b8cb2d1c292f042046c5a994c43155c0be6190fa0355160742ab2e1c88d40d5be660b410') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;"
php composer-setup.php --install-dir=/usr/bin/ && cp /usr/bin/composer.phar /usr/bin/composer
php -r "unlink('composer-setup.php');"
curl -sL https://deb.nodesource.com/setup_7.x | sudo -E bash -
apt-get install nodejs
npm -g i webpack@\^2.2
apt-get install -y php-xml zip unzip php-zip iputils-ping php-mbstring php-mcrypt php-pear php-mysqlnd php-pdo php-soap php-opcache php-pspell php-tidy php-xml
```

#### Download API 

```shell
mkdir /var/www/vpn-api 
cd /var/www/vpn-api
git https://github.com/comgress64/vpn_api.git develop
cd develop
```

#### Configure API 

**Set server token at the bottom of config/app.php, then build the app**

```shell
composer install
cp .env.example .env
php artisan key:generate
chown -R 65534 storage/
```

#### /api/getkey

register new key by given user ID, or update if need. If key is already registered and valid - just return key URL. Group_ip is a parameter, describing ip addresses (subnets) where current account allowed to connect. Ip_address in response return ip address assigned to current client

    request:
    device_id:string
    token: string 
    group_ip:group_ip (ipv6 range in form fdxx:xxxx:xxxx:xxxx:xxxx:yyyy:0000) which this device belongs to (in lowercase)
    response:
    status:OK|Error description
    ip_address:string
    key_url:Url-of-client-key  (only if status is OK)
    key_version: integer, incrementing each time we have new key

##### Description:

This single request covers several functions:


* Create new key (end-point device)
* Re-generate or prolongate key
* Assign key/device to group or groups
* Re-assign or remove from group or groups

##### Example:

```shell

    curl -H 'Host: vpnapi.comgress64.com' -H 'Content-Type: application/json' --data '{"user_id":"k", "group_id":"fd7d:6fbd:828c:022c:0000:0000:0000:0000"}' http://localhost:8888/api/getkey
```

#### /api/stopkey

remove or suspend key by given user ID

    request:
    device_id:string
    token: string 
    action:suspend|remove
    response:
    status:OK|Error description

##### Description:

This request is for removing or temporary suspending key. When action:remove chosen key completely removing from server and  all records removed from firewall.     
In case when action:suspend chosen device blocked only on firewall leaving key itself intact.

##### Example:

```shell

    curl -H 'Host: vpnapi.comgress64.com' -H 'Content-Type: application/json' --data '{"user_id":"k", "action":"suspend"}' http://localhost:8888/api/stopkey

```

#### /api/getstatus

get status of device by given user ID

    request:
    device_id:string
    token: string 

    response:
    device_id:string
    Status:connected|disconnected|error description

##### Description:

This request returns current status of device

##### Example:

```shell

curl -H 'Host: vpnapi.comgress64.com' -H 'Content-Type: application/json' --data '{"user_id":"k"}' http://localhost:8888/api/getstatus

```

#### /api/setgroups

set groups to given device_id

    request:
    device_id:string (ID or address of device)
    token: string 
    group_ip:array of group_ip (ipv6 range in form fdxx:xxxx:xxxx:xxxx:xxxx:yyyy:0000). Can also be single ip to be allowed to connect to (in lowercase). 
    ports:array of IP ports to be allowed to connect to. 
    Action:add|remove to add or remove access of given device to given groups or single address
    response:
    status:OK|Error description

##### Description:

This request is used for access management (adding/removing access rights to certain users or subnets)

Note 1: modifying port and group access lists should consists from two requests -  rules than no more needed should be removed and only after that new rules can be added. 

Note 2: Opening ports inside group should be also done as separate request setting device_id = group_id 

Note 3: If 'ports' parameter is omitted, then full access is granted, otherwise it's tcp only.

##### Example:

```shell

curl -H 'Host: vpnapi.comgress64.com' -H 'Content-Type: application/json' --data '{"user_id":"k", "action":"add", "group_id":"fd7d:6fbd:828c:022c:0000:0000:0000:0000/112", "ports":["22","25"]}' http://localhost:8888/api/setgroups

```

Grant user 'k' access to any host in group 0, limited to ports 22 and 25

```shell
    curl -H 'Host: vpnapi.comgress64.com' -H 'Content-Type: application/json' --data '{"user_id":"fd7d:6fbd:828c:022c:0000:0000:0000:0000/112", "action":"add", "group_id":"fd7d:6fbd:828c:022c:0000:0000:0010:0000/112", "ports":["22","25"]}' http://localhost:8888/api/setgroups
    curl -H 'Host: vpnapi.comgress64.com' -H 'Content-Type: application/json' --data '{"user_id":"fd7d:6fbd:828c:022c:0000:0000:0010:0000/112", "action":"add", "group_id":"fd7d:6fbd:828c:022c:0000:0000:0000:0000/112", "ports":["22","25"]}' http://localhost:8888/api/setgroups
    
```
Grant access to all hosts from group 0 to all hosts in group 10 and vice versa, i.e. merge two groups.
