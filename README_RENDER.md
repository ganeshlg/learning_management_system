Render deployment instructions

1) Sign up at https://dashboard.render.com and connect your Git repository.

2) If Render did not auto-detect `render.yaml`, create the services manually.

### Manual service creation
Create three web services from your repo:

- **api-gateway**
  - Environment: Docker
  - Dockerfile path: `api-gateway/Dockerfile`
  - Start command: `php -S 0.0.0.0:8080 -t public public/index.php`
  - Instance type / plan: Free

- **user-service**
  - Environment: Docker
  - Dockerfile path: `services/user-service/Dockerfile`
  - Start command: `php -S 0.0.0.0:8080 -t public public/index.php`
  - Instance type / plan: Free

- **video-service**
  - Environment: Docker
  - Dockerfile path: `services/video-service/Dockerfile`
  - Start command: `php -S 0.0.0.0:8080 -t public public/index.php`
  - Instance type / plan: Free

3) Create a managed database in Render if available, or use an external MySQL instance.

4) Configure environment variables.

- For `user-service` and `video-service`:
  - `DB_HOST` = your database host
  - `DB_DATABASE` = `lms`
  - `DB_USERNAME` = your DB username
  - `DB_PASSWORD` = your DB password
  - `DB_DRIVER` = `pgsql` (set to `pgsql` for PostgreSQL)
  - `DB_PORT` = `5432` (optional, default 5432 for Postgres)

- For `api-gateway`:
  - `USER_SERVICE_URL` = `https://<your-user-service-render-url>`
  - `VIDEO_SERVICE_URL` = `https://<your-video-service-render-url>`

5) Deploy each service. Once deployed, Render will give you service URLs.

6) Test the gateway endpoint:

```
curl -I https://<your-api-gateway-url>/api/videos/civil.mp4
```

### If you use Render managed DB
- Use the `lms-db` service from `render.yaml` if available.
- Otherwise use an external MySQL host and set the DB env vars manually.

### Troubleshooting
- If the gateway cannot reach the services, confirm `USER_SERVICE_URL` and `VIDEO_SERVICE_URL` are the exact public URLs.
- If the DB connection fails, confirm `DB_HOST` points to the rendered database host and the credentials are correct.
- `render.yaml` is optional if you create services manually.
