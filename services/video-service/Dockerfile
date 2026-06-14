FROM php:8.1-cli
WORKDIR /app
COPY services/video-service/ /app

RUN apt-get update && apt-get install -y unzip libzip-dev default-libmysqlclient-dev libpq-dev \
  && docker-php-ext-install pdo pdo_mysql pdo_pgsql

RUN if [ -f composer.json ]; then \
  curl -sS https://getcomposer.org/installer | php && php composer.phar install --no-dev --no-interaction; \
  fi

ENV PORT=8080
EXPOSE 8080
# Use public/index.php as router so all requests are handled by the app
CMD ["sh","-c","php -S 0.0.0.0:${PORT} -t public public/index.php"]
