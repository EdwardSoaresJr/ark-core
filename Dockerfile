FROM php:8.4-fpm-bookworm

RUN apt-get update && apt-get install -y \
    nginx supervisor git unzip curl wget \
    libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libwebp-dev libonig-dev libicu-dev \
    libmagickwand-dev \
    ca-certificates fonts-dejavu-core fonts-dejavu-extra fonts-liberation fonts-noto-core \
    tesseract-ocr tesseract-ocr-eng poppler-utils \
    libasound2 libatk-bridge2.0-0 libatk1.0-0 \
    libc6 libcairo2 libcups2 libdbus-1-3 libexpat1 libfontconfig1 libgbm1 \
    libglib2.0-0 libgtk-3-0 libnspr4 libnss3 libpango-1.0-0 libpangocairo-1.0-0 \
    libstdc++6 libx11-6 libx11-xcb1 libxcb1 libxcomposite1 libxcursor1 libxdamage1 \
    libxext6 libxfixes3 libxi6 libxrandr2 libxrender1 libxss1 libxtst6 xdg-utils \
    && pecl install redis imagick \
    && docker-php-ext-enable redis imagick \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install pdo_mysql zip mbstring bcmath pcntl intl gd \
    && rm -rf /var/lib/apt/lists/*

# Documents allow 25 MB; keep PHP below nginx client_max_body_size (64M).
RUN printf '%s\n' \
    'memory_limit=256M' \
    'upload_max_filesize=32M' \
    'post_max_size=32M' \
    'max_execution_time=600' \
    > /usr/local/etc/php/conf.d/ark-uploads.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN curl -fsSL https://deb.nodesource.com/setup_22.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

WORKDIR /app

# Layer 1: PHP deps — reuse until composer.lock changes
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-autoloader --no-scripts --no-interaction

# Layer 2: npm toolchain — reuse until package-lock.json changes
COPY package.json package-lock.json .npmrc vite.config.js tailwind.config.js postcss.config.js ./
ENV PUPPETEER_CACHE_DIR=/app/puppeteer-cache
ENV PDF_NODE_BINARY=/usr/bin/node
ENV PDF_NPM_BINARY=/usr/bin/npm
ENV PDF_NO_SANDBOX=true
RUN npm ci --include=dev \
    && mkdir -p /app/puppeteer-cache \
    && npx puppeteer browsers install chrome-headless-shell \
    && chmod -R a+rX /app/puppeteer-cache

# Layer 3: Vite + Tailwind — must track resources/ and invalidate every deploy.
# GHA layer cache can reuse npm run build when COPY resources hits cache incorrectly;
# GIT_SHA forces a fresh asset build whenever application code ships.
COPY resources ./resources
COPY public ./public
ARG GIT_SHA=unknown
RUN echo "vite build ${GIT_SHA}" && npm run build \
    && npm prune --omit=dev

# Layer 4: Application code — changes often; refresh autoload only (no npm/composer re-download)
# NOTE: One COPY with multiple dirs flattens contents into /app/ (breaks bootstrap/app.php).
COPY app ./app
COPY bootstrap ./bootstrap
COPY config ./config
COPY database ./database
COPY routes ./routes
COPY rte ./rte
COPY artisan ./artisan
COPY infra ./infra
RUN CACHE_STORE=array APP_KEY=base64:AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA= composer install --no-dev --optimize-autoloader --no-interaction \
    && mkdir -p storage/app/public storage/app/private storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /app/infra/coolify/ark-post-deploy.sh

RUN rm -f /etc/nginx/sites-enabled/default /etc/nginx/sites-available/default \
    && printf '%s\n' \
    'server {' \
    '    listen 80 default_server;' \
    '    root /app/public;' \
    '    index index.php;' \
    '    client_max_body_size 64M;' \
    '    location ~ "^/app/[0-9a-zA-Z]{20,}$" {' \
    '        proxy_http_version 1.1;' \
    '        proxy_set_header Host $http_host;' \
    '        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;' \
    '        proxy_set_header X-Forwarded-Proto $http_x_forwarded_proto;' \
    '        proxy_set_header Upgrade $http_upgrade;' \
    '        proxy_set_header Connection "upgrade";' \
    '        proxy_pass http://127.0.0.1:9090;' \
    '    }' \
    '    location / { try_files $uri $uri/ /index.php?$query_string; }' \
    '    location ~ \.php$ {' \
    '        fastcgi_pass 127.0.0.1:9000;' \
    '        fastcgi_index index.php;' \
    '        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;' \
    '        include fastcgi_params;' \
    '        fastcgi_read_timeout 600s;' \
    '        fastcgi_send_timeout 600s;' \
    '    }' \
    '}' > /etc/nginx/conf.d/default.conf

COPY infra/coolify/supervisord.conf /etc/supervisor/conf.d/ark.conf
COPY infra/coolify/entrypoint.sh /usr/local/bin/ark-entrypoint.sh
COPY infra/coolify/php-fpm-www.conf /tmp/ark-php-fpm-profile.conf
RUN chmod +x /usr/local/bin/ark-entrypoint.sh \
    && sed -i \
        -e 's/^pm.max_children = .*/pm.max_children = 3/' \
        -e 's/^pm.start_servers = .*/pm.start_servers = 1/' \
        -e 's/^pm.max_spare_servers = .*/pm.max_spare_servers = 2/' \
        /usr/local/etc/php-fpm.d/www.conf \
    && grep -q '^pm.max_requests' /usr/local/etc/php-fpm.d/www.conf \
        && sed -i 's/^pm.max_requests = .*/pm.max_requests = 500/' /usr/local/etc/php-fpm.d/www.conf \
        || echo 'pm.max_requests = 500' >> /usr/local/etc/php-fpm.d/www.conf \
    && (grep -q '^clear_env' /usr/local/etc/php-fpm.d/www.conf \
        && sed -i 's/^;*clear_env.*/clear_env = no/' /usr/local/etc/php-fpm.d/www.conf \
        || echo 'clear_env = no' >> /usr/local/etc/php-fpm.d/www.conf)

EXPOSE 80
CMD ["/usr/local/bin/ark-entrypoint.sh"]
