build:
	sudo docker compose -f ../compose.yaml build

up:
	sudo docker compose -f ../compose.yaml up -d

down:
	sudo docker compose -f ../compose.yaml down

restart: down up

logs:
	sudo docker compose -f ../compose.yaml logs -f

bash:
	sudo docker compose -f ../compose.yaml exec backend bash
