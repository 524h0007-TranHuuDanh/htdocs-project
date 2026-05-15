<?php
return [
    'secret' => getenv('WS_SECRET') ?: 'noteapp-ws-local-dev-secret-min-length-32-chars!',
];