# 베이스 이미지: PHP 8.1 + Apache
FROM php:8.1-apache

# 작업 디렉토리 설정
WORKDIR /var/www/html

# 필요한 패키지 설치
RUN apt-get update && apt-get install -y \
    zip \
    unzip \
    libzip-dev \
    && docker-php-ext-install zip

# Apache 모듈 활성화
RUN a2enmod rewrite

# 프로젝트 파일 복사
COPY . /var/www/html

# 권한 설정
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Render는 10000번 포트를 기본적으로 사용하므로 포트를 설정합니다.
EXPOSE 10000

# Apache를 Render가 사용하는 10000번 포트로 실행
CMD ["apache2-foreground"]