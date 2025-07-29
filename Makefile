build:
	sudo docker compose build

up:
	sudo docker compose up -d

down:
	sudo docker compose down

restart:
	make down
	make up

logs:
	sudo docker compose logs -f

bash:
	sudo docker compose exec php bash

psql:
	sudo docker exec -it symfony_postgres psql -U app -d guard
