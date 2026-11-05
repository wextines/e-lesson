# Local Docker launch

Run the project from this directory:

```bash
docker compose up --build
```

Open http://localhost:8080/.

The database starts empty. The legacy bootstrap command is available only for a first local inspection:

```bash
docker compose exec web php /var/www/html/admin/php/db.php
```

The bootstrap schema is not yet a production migration and needs to be aligned with the question tables before using the test section.
