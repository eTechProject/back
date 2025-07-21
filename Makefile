build:
	sudo docker compose build

up:
	sudo docker compose up -d --build

down:
	sudo docker compose down

restart: down up

logs:
	sudo docker compose logs -f

bash:
	sudo docker compose exec php bash
