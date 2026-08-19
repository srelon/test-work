include .env
export

DOCKER_COMPOSE := $(shell docker compose version > /dev/null 2>&1 && echo "docker compose" || echo "docker-compose")

up:
	$(DOCKER_COMPOSE) -f docker-compose.yml -f docker-compose.override.yml up -d
	@echo ""
	@echo "  Site:         http://127.0.0.1:$(SITE_PORT)"
	@echo "  API:          http://127.0.0.1:$(SITE_PORT)/api/"
	@echo ""
	@echo "Waiting for app to start..."

prod:
	$(DOCKER_COMPOSE) -f docker-compose.yml -f docker-compose.prod.yml up -d
	@echo ""
	@echo "  Site: https://$(SSL_DOMAIN)"
	@echo ""

down:
	$(DOCKER_COMPOSE) --profile site --profile phpmyadmin down --remove-orphans

bash:
	docker exec -it dzencode_app bash

logs:
	$(DOCKER_COMPOSE) logs -f

logs-nginx:
	$(DOCKER_COMPOSE) logs -f nginx

logs-app:
	$(DOCKER_COMPOSE) logs -f app

logs-db:
	$(DOCKER_COMPOSE) logs -f db

logs-reverb:
	$(DOCKER_COMPOSE) logs -f reverb

logs-laravel:
	tail -f backend/storage/logs/laravel.log

reverb-keys:
	@NEW_ID=$$(openssl rand -hex 8); \
	NEW_KEY=$$(openssl rand -hex 16); \
	NEW_SECRET=$$(openssl rand -hex 32); \
	sed -i.bak "s#^REVERB_APP_ID=.*#REVERB_APP_ID=$$NEW_ID#" backend/.env; \
	sed -i.bak "s#^REVERB_APP_KEY=.*#REVERB_APP_KEY=$$NEW_KEY#" backend/.env; \
	sed -i.bak "s#^REVERB_APP_SECRET=.*#REVERB_APP_SECRET=$$NEW_SECRET#" backend/.env; \
	sed -i.bak "s#^VITE_REVERB_APP_KEY=.*#VITE_REVERB_APP_KEY=$$NEW_KEY#" frontend/.env; \
	rm -f backend/.env.bak frontend/.env.bak; \
	echo "Reverb app id/key/secret rotated in backend/.env and frontend/.env. Restart the reverb container and rebuild the frontend for it to take effect."

scheduler-logs:
	docker logs -f dzencode_scheduler

scheduler-restart:
	$(DOCKER_COMPOSE) restart scheduler

pma:
	$(DOCKER_COMPOSE) --profile phpmyadmin up -d
	@echo ""
	@echo "  phpMyAdmin:  http://127.0.0.1:8080"
	@echo ""

site:
	$(DOCKER_COMPOSE) --profile site up -d
	@echo ""
	@echo "  Site dev:  http://127.0.0.1:5173"
	@echo ""

test:
	docker exec -it dzencode_app bash -c "cd /var/www/backend && php artisan test"

format:
	docker exec -it dzencode_app bash -c "cd /var/www/backend && ./vendor/bin/pint"

fresh:
	docker exec -it dzencode_app bash -c "cd /var/www/backend && php artisan migrate:fresh --seed"
