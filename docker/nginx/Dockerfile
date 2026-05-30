FROM nginx:alpine

# Copier la configuration
COPY default.conf /etc/nginx/conf.d/default.conf

# Créer l'utilisateur www
RUN addgroup -g 1000 -S www && \
    adduser -u 1000 -S -G www -s /bin/bash www

WORKDIR /var/www

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]