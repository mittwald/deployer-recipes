ARG ARCH=""

FROM ${ARCH}php:8.3-cli-alpine

ARG RECIPE_VERSION=dev-master

RUN wget https://raw.githubusercontent.com/composer/getcomposer.org/76a7060ccb93902cd7576b67264ad91c8a2700e2/web/installer -O - -q | php -- --quiet && \
    mv composer.phar /usr/local/bin/composer
RUN apk add -U rsync openssh-client bash

RUN composer global config minimum-stability dev && \
    composer global require deployer/deployer=^7.3.1 mittwald/deployer-recipes=${RECIPE_VERSION} && \
    ln -s /root/.composer/vendor/bin/dep /usr/local/bin/dep

ENTRYPOINT ["/usr/local/bin/dep"]