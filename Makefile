build:
	sudo docker compose -f docker-compose.yml build

up:
	sudo docker compose -f docker-compose.yml up -d

down:
	sudo docker compose -f docker-compose.yml down

restart: down up

logs:
	sudo docker compose -f docker-compose.yml logs -f

bash:
	sudo docker compose -f docker-compose.yml exec php bash
