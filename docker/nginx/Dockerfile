# syntax=docker/dockerfile:1

# Stage 1: build the Angular app
FROM node:22-alpine AS frontend
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci --no-audit --no-fund
COPY frontend/ ./
RUN npx ng build --configuration production

# Stage 2: nginx serving the SPA, API docs and proxying /api to php-fpm
FROM nginx:1.27-alpine
COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf
COPY docs/ /srv/docs/
COPY --from=frontend /app/dist/frontend/browser/ /usr/share/nginx/html/
