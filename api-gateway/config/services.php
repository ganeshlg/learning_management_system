<?php
return [
  'user_service'  => getenv('USER_SERVICE_URL') ?: 'http://user-service:8080',
  'video_service' => getenv('VIDEO_SERVICE_URL') ?: 'http://video-service:8080'
];