## Config for Portainer Host UI ##

```nginx
# Handle CORS for Docker Stack Manager
location / {
    # Add CORS headers - Fixed origin
    add_header Access-Control-Allow-Origin "https://n8n.rrcommerce.nl" always;
    add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS" always;
    add_header Access-Control-Allow-Headers "Content-Type, Authorization, X-Requested-With" always;
    add_header Access-Control-Allow-Credentials true always;
    
    # Handle preflight OPTIONS requests
    if ($request_method = 'OPTIONS') {
        add_header Access-Control-Allow-Origin "https://n8n.rrcommerce.nl";
        add_header Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS";
        add_header Access-Control-Allow-Headers "Content-Type, Authorization, X-API-Key";
        add_header Access-Control-Allow-Credentials true;
        add_header Content-Length 0;
        add_header Content-Type text/plain;
        return 204;
    }
    
    # Additional headers for Cloudflare compatibility
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_set_header Host $host;
    
    # Proxy to Portainer
    proxy_pass $forward_scheme://$server:$port;
}
```



## Config for Docker Stack Manager ##

```nginx
location /docker-stack-manager/ {
    proxy_pass http://10.10.10.200:88/;
    proxy_http_version 1.1;
    proxy_set_header Upgrade $http_upgrade;
    proxy_set_header Connection 'upgrade';
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
    proxy_cache_bypass $http_upgrade;
}

location = /docker-stack-manager {
    return 301 /docker-stack-manager/;
}
```