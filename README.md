# naisdevice Jita

Web application that enables Just in time access to sensitive gateways.

## Local development

Install dependencies:

```bash
composer install
```

Start database:

```bash
docker compose up -d
```

Copy default environment varibles and modify when necessary:

```bash
cp .env.example .env
```

Run the application:

```bash
composer run dev
```

Point your browser to http://localhost:8080.