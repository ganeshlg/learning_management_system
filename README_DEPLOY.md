# Deploy notes — minimal steps to build and deploy

1. Build & push images (replace YOUR_PROJECT_ID):

```
gcloud auth login
gcloud config set project YOUR_PROJECT_ID
gcloud builds submit --tag us-central1-docker.pkg.dev/YOUR_PROJECT_ID/php-services/user-service:latest services/user-service
gcloud builds submit --tag us-central1-docker.pkg.dev/YOUR_PROJECT_ID/php-services/video-service:latest services/video-service
gcloud builds submit --tag us-central1-docker.pkg.dev/YOUR_PROJECT_ID/php-services/api-gateway:latest api-gateway
```

2. Deploy to Cloud Run:

```
gcloud run deploy user-service --image us-central1-docker.pkg.dev/YOUR_PROJECT_ID/php-services/user-service:latest --region us-central1 --allow-unauthenticated --platform managed --port 8080
gcloud run deploy video-service --image us-central1-docker.pkg.dev/YOUR_PROJECT_ID/php-services/video-service:latest --region us-central1 --allow-unauthenticated --platform managed --port 8080
gcloud run deploy api-gateway --image us-central1-docker.pkg.dev/YOUR_PROJECT_ID/php-services/api-gateway:latest --region us-central1 --allow-unauthenticated --platform managed --port 8080
```

3. Configure Firebase Hosting (already contains `firebase.json`) and deploy:

```
npm install -g firebase-tools
firebase login
firebase init hosting
firebase deploy --only hosting
```

Notes:
- Cloud Run and Artifact Registry require billing (Blaze) enabled on the project.
- For free/no-billing alternatives see Render or Railway.
