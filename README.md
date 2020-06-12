## About

This app acts as an Origin Shield between your CDN and S3.

## Installation

The installation is very easy and straightforward:

  * Create a `config.php` file with your settings.
  * Point NGINX to the `public/` directory.
  * Run `composer install` to install the dependencies.

```console
$ cp config.default.php config.php
$ composer install
```

## Development & Testing

To verify the integrity of the codebase you can run the PHP linter:

```console
$ composer install
$ composer phpcs
```

## Configuration of the Stack

I use HA Proxy for the SSL termination, Varnish as hot memory-cache, and NGINX
as web and file server.

> Note: HA Proxy and Varnish are optional, you could use NGINX For SSL termination and
> not use a hot-memory cache at all.

### Sample Configuration Parameters

* Hostname: `origin-shield.example.com`
* Root Path: `/opt/origin_shield`
* App Path: `${ROOT_PATH}/repo`
* Cache Path: `{$ROOT_PATH}/cache`

### NGINX Sample Configuration

```
server {
    listen 8080;
    server_name origin-shield.example.com;
    root /opt/origin_shield/repo/public;
    index index.php;
    location / {
        try_files $uri /index.php$is_args$args;
    }
    location /protected/ {
        internal;
        alias /opt/origin_shield/cache/;
    }
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php7.2-fpm.sock;
    }
    location ~ /\.ht {
        deny all;
    }
}
```

> Note: The trailing slash `/` in the `alias` is mandatory.

### HA Proxy (SSL Termination) Sample Configuration

```
listen varnish_http
    bind ipv4@:80
    bind ipv6@:80
    mode tcp
    balance roundrobin
    maxconn 2000000
    timeout connect 5s
    timeout client 30s
    timeout server 30s
    server localhost 127.0.0.1:6081 check

listen varnish_proxy
    bind ipv4@:443 ssl crt /etc/ssl/certs/example.com.pem
    bind ipv6@:443 ssl crt /etc/ssl/certs/example.com.pem
    mode tcp
    balance roundrobin
    maxconn 2000000
    timeout connect 5s
    timeout client 30s
    timeout server 30s
    server localhost 127.0.0.1:6086 check send-proxy-v2
```

### Varnish Sample Configuration

#### /etc/systemd/system/varnish.service.d/service.conf

```
[Service]
Type=simple
TasksMax=infinity
LimitNOFILE=10000000
LimitNPROC=500000
LimitMEMLOCK=82000
ExecStart=
ExecStart=/usr/sbin/varnishd -j unix,user=vcache -F -a :6081 -a :6086,PROXY -T 127.0.0.1:6082 -f /etc/varnish/default.vcl -S /etc/varnish/secret -s malloc,250G -p listen_depth=2048 -p thread_pool_min=250 -p cli_limit=128k
```

> Note: The first empty `ExecStart=` is intended and not a mistake.

#### /etc/varnish/conf/origin_shield.vcl

```
probe origin_shield_health {
  .url = "/";
  .interval = 5s;
  .timeout = 3s;
  .window = 5;
  .threshold =3;
}

backend origin_shield_1 {
  .host = "127.0.0.1";
  .port = "8080";
  .probe = origin_shield_health;
}

sub vcl_init {
  new origin_shield = directors.round_robin();
  origin_shield.add_backend(origin_shield_1);
}

sub vcl_recv {
  if (req.http.host == "origin-shield.example.com") {
    set req.backend_hint = origin_shield.backend();
    unset req.http.cookie;
    unset req.http.cache-control;
    unset req.http.pragma;
    unset req.http.expires;
    return (hash);
  }
}

sub vcl_backend_response {
  if (bereq.url == "origin-shield.example.com") {
    set beresp.ttl = 30d;
    return (deliver);
  }
}
```

> Disclaimer: The sample VCL is basic, **very basic**, you will need to optimize this
> based on your use-case!

### CDN Configuration

Assuming you have a working pull zone for `https://kitties.ca-central-1.amazonaws.com`, all you
need to do is change the URL to `https://origin-shield.example.com/?pz=kitties.ca-central-1.amazonaws.com`

If you have issues with your Origin Shield, you can quickly reconfigure the CDN to pull from S3
again directly.

### Example Hardware

I deployed this app on [Hetzner SX](https://www.hetzner.com/dedicated-rootserver/matrix-sx),
[Scaleway DediBox](https://www.scaleway.com/en/dedibox/), and [OVH Dedicated](https://www.ovh.ie/dedicated_servers/)

An example would be a Hetzner SX292, 256GB ram, and 150TB raw storage; after RAID6 and the filesystem, there
is 120TB of usable storage capacity and 250GB for Varnish hot-cache.

## Collaboration

The GitHub repository is used to keep track of all the bugs and feature
requests; I prefer to work exclusively via GitHib and Twitter.

If you have a patch to contribute:

  * Fork this repository on GitHub.
  * Create a feature branch for your set of patches.
  * Commit your changes to Git and push them to GitHub.
  * Submit a pull request.

Shout to [@GeertHauwaerts](https://twitter.com/GeertHauwaerts) on Twitter at
any time :)
