FROM php:8.2-cli

WORKDIR /app

# Install extensions used by this project.
RUN docker-php-ext-install pdo pdo_mysql

# Copy repository
COPY . /app

EXPOSE 10000

# Render provides PORT env var; fall back to 10000 locally.
CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} -t hospital_portal"]
