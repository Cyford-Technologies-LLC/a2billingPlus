FROM ubuntu:24.04

RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        asterisk \
        asterisk-core-sounds-en \
        asterisk-doc \
        ca-certificates \
        curl \
        odbc-mariadb \
        php-bcmath \
        php-cli \
        php-curl \
        php-gd \
        php-intl \
        php-mbstring \
        php-mysql \
        php-pgsql \
        php-soap \
        php-sqlite3 \
        php-xml \
        php-zip \
        unixodbc \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

COPY docker/asterisk/config/ /opt/a2bp-asterisk-defaults/
COPY docker/asterisk/config/ /etc/asterisk/
COPY docker/asterisk/entrypoint.sh /usr/local/bin/a2bp-asterisk-entrypoint

RUN chmod +x /usr/local/bin/a2bp-asterisk-entrypoint \
    && mkdir -p /var/run/asterisk /var/log/asterisk /var/spool/asterisk /etc/cyford/asterisk \
    && chown -R asterisk:asterisk /var/run/asterisk /var/log/asterisk /var/spool/asterisk /etc/asterisk /etc/cyford/asterisk /opt/a2bp-asterisk-defaults

EXPOSE 5038 8088 5060/udp 10000-10020/udp

ENTRYPOINT ["a2bp-asterisk-entrypoint"]
CMD ["asterisk", "-f", "-vvv"]
