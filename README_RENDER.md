Render deployment instructions

1) Sign up at https://dashboard.render.com and connect your Git repository.

2) Deploy using `render.yaml`:

 - Replace placeholders in `render.yaml` with actual values:
   - `{{user-service-url}}`, `{{video-service-url}}` will be the public URLs Render assigns (you can leave them and set `USER_SERVICE_URL`/`VIDEO_SERVICE_URL` via the Render UI instead).
   - `{{db-host}}`, `{{db-user}}`, `{{db-pass}}` should be set from Render managed DB credentials.

3) Push your repository to GitHub/GitLab, then create services in Render by choosing "Deploy from repo" and selecting `render.yaml` (Render will create the services and DB automatically if supported in your account).

4) In each service's Environment settings, set any missing env vars (for `api-gateway` set `USER_SERVICE_URL` and `VIDEO_SERVICE_URL` to the service URLs Render provides). For `user-service` and `video-service` set DB env vars accordingly.

5) After deploy, test endpoints:

```
curl https://<your-api-gateway-url>/api/videos/civil.mp4 -I
```

Notes:
- Render free plan has limits; for storage-heavy video serving you may prefer a CDN or object storage.
- If Render's managed DB isn't available on free tier, create a small managed DB service or use an external MySQL and set `DB_HOST` accordingly.
