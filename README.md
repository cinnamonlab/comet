
# usage with nginx proxy

make the nginx like: 
http://marcjschmidt.de/blog/2014/02/08/php-high-performance.html

upstream backend  {
    server 127.0.0.1:5501;
    server 127.0.0.1:5502;
    server 127.0.0.1:5503;
    server 127.0.0.1:5504;
    server 127.0.0.1:5505;
    server 127.0.0.1:5506;
}

server {
    root /path/to/symfony/web/;
    server_name servername.com
    location / {
        if (!-f $request_filename) {
            proxy_pass http://backend;
            break;
        }
    }
}

