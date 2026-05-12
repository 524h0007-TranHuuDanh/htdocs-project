cấu túc thư mục
HTDOCS
|
api - auth_helper.php
|   |
|   - change_color.php
|   |
|   - delete_image.php
|   |
|   - delete_note.php
|   |
|   - get_note_images.php
|   |
|   - get_note_labels.php
|   |
|   - get_notes.php
|   |
|   - get_shares.php
|   |
|   - lock_note.php
|   |
|   - manage_labels.php
|   |
|   - pin_note.php
|   |
|   - restore_note.php
|   |
|   - revoke_share.php
|   |
|   - save_note.php
|   |
|   - search.php
|   |
|   - set_note_label.php
|   |
|   - share_note.php
|   |
|   - update_profile.php
|   |
|   - upload_image.php
|   |
|   - verify_note.php
|
App - NoteWebSocket.php
|
assets - css - style.css
|      |
|      - js - app.js
uploads - avatars
|
vendor // đã cài đặt
|
websocket - server.php
|
activate.php
|
composer.json
|
composer.lock
|
config.php
|
database.php
|
database.sql
|
index.php
|
login.php
|
logout.php
|
mail_config.php
|
manifest.json
|
register.php
|
reset_password.php
|
Rubrik.docx
|
service-worker.js

code từng file
activate.php
<?php
session_start();
require_once 'database.php';

$message = '';
$token = $_GET['token'] ?? '';

if (!empty($token)) {
    $stmt = $pdo->prepare("SELECT id, display_name FROM users WHERE activation_token = ? LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $update = $pdo->prepare("UPDATE users SET is_activated = 1, activation_token = NULL WHERE id = ?");
        $update->execute([$user['id']]);

        // Tự động đăng nhập sau khi kích hoạt
        $_SESSION['user_id']      = $user['id'];
        $_SESSION['display_name'] = $user['display_name'];
        $_SESSION['is_activated'] = 1;

        $message = "Kích hoạt tài khoản thành công!";
        header("Location: index.php?activated=1");
        exit;
    } else {
        $message = "Link kích hoạt không hợp lệ hoặc đã hết hạn!";
    }
} else {
    $message = "Token không hợp lệ!";
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Kích hoạt tài khoản</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body text-center">
                    <h3>Kích hoạt tài khoản</h3>
                    <div class="alert alert-info"><?= htmlspecialchars($message) ?></div>
                    <a href="login.php" class="btn btn-primary">Đăng nhập</a>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
composer.json
{
    "name": "noteapp/pro",
    "require": {
        "cboden/ratchet": "^0.4.4"
    },
    "autoload": {
        "psr-4": {
            "App\\": "App/"
        }
    }
}
composer.lock
{
    "_readme": [
        "This file locks the dependencies of your project to a known state",
        "Read more about it at https://getcomposer.org/doc/01-basic-usage.md#installing-dependencies",
        "This file is @generated automatically"
    ],
    "content-hash": "5d52e477307bf204bf133801c3d7ba55",
    "packages": [
        {
            "name": "cboden/ratchet",
            "version": "v0.4.4",
            "source": {
                "type": "git",
                "url": "https://github.com/ratchetphp/Ratchet.git",
                "reference": "5012dc954541b40c5599d286fd40653f5716a38f"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/ratchetphp/Ratchet/zipball/5012dc954541b40c5599d286fd40653f5716a38f",
                "reference": "5012dc954541b40c5599d286fd40653f5716a38f",
                "shasum": ""
            },
            "require": {
                "guzzlehttp/psr7": "^1.7|^2.0",
                "php": ">=5.4.2",
                "ratchet/rfc6455": "^0.3.1",
                "react/event-loop": ">=0.4",
                "react/socket": "^1.0 || ^0.8 || ^0.7 || ^0.6 || ^0.5",
                "symfony/http-foundation": "^2.6|^3.0|^4.0|^5.0|^6.0",
                "symfony/routing": "^2.6|^3.0|^4.0|^5.0|^6.0"
            },
            "require-dev": {
                "phpunit/phpunit": "~4.8"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "Ratchet\\": "src/Ratchet"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Chris Boden",
                    "email": "cboden@gmail.com",
                    "role": "Developer"
                },
                {
                    "name": "Matt Bonneau",
                    "role": "Developer"
                }
            ],
            "description": "PHP WebSocket library",
            "homepage": "http://socketo.me",
            "keywords": [
                "Ratchet",
                "WebSockets",
                "server",
                "sockets",
                "websocket"
            ],
            "support": {
                "chat": "https://gitter.im/reactphp/reactphp",
                "issues": "https://github.com/ratchetphp/Ratchet/issues",
                "source": "https://github.com/ratchetphp/Ratchet/tree/v0.4.4"
            },
            "time": "2021-12-14T00:20:41+00:00"
        },
        {
            "name": "evenement/evenement",
            "version": "v3.0.2",
            "source": {
                "type": "git",
                "url": "https://github.com/igorw/evenement.git",
                "reference": "0a16b0d71ab13284339abb99d9d2bd813640efbc"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/igorw/evenement/zipball/0a16b0d71ab13284339abb99d9d2bd813640efbc",
                "reference": "0a16b0d71ab13284339abb99d9d2bd813640efbc",
                "shasum": ""
            },
            "require": {
                "php": ">=7.0"
            },
            "require-dev": {
                "phpunit/phpunit": "^9 || ^6"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "Evenement\\": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Igor Wiedler",
                    "email": "igor@wiedler.ch"
                }
            ],
            "description": "Événement is a very simple event dispatching library for PHP",
            "keywords": [
                "event-dispatcher",
                "event-emitter"
            ],
            "support": {
                "issues": "https://github.com/igorw/evenement/issues",
                "source": "https://github.com/igorw/evenement/tree/v3.0.2"
            },
            "time": "2023-08-08T05:53:35+00:00"
        },
        {
            "name": "guzzlehttp/psr7",
            "version": "2.9.0",
            "source": {
                "type": "git",
                "url": "https://github.com/guzzle/psr7.git",
                "reference": "7d0ed42f28e42d61352a7a79de682e5e67fec884"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/guzzle/psr7/zipball/7d0ed42f28e42d61352a7a79de682e5e67fec884",
                "reference": "7d0ed42f28e42d61352a7a79de682e5e67fec884",
                "shasum": ""
            },
            "require": {
                "php": "^7.2.5 || ^8.0",
                "psr/http-factory": "^1.0",
                "psr/http-message": "^1.1 || ^2.0",
                "ralouphie/getallheaders": "^3.0"
            },
            "provide": {
                "psr/http-factory-implementation": "1.0",
                "psr/http-message-implementation": "1.0"
            },
            "require-dev": {
                "bamarni/composer-bin-plugin": "^1.8.2",
                "http-interop/http-factory-tests": "0.9.0",
                "jshttp/mime-db": "1.54.0.1",
                "phpunit/phpunit": "^8.5.44 || ^9.6.25"
            },
            "suggest": {
                "laminas/laminas-httphandlerrunner": "Emit PSR-7 responses"
            },
            "type": "library",
            "extra": {
                "bamarni-bin": {
                    "bin-links": true,
                    "forward-command": false
                }
            },
            "autoload": {
                "psr-4": {
                    "GuzzleHttp\\Psr7\\": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Graham Campbell",
                    "email": "hello@gjcampbell.co.uk",
                    "homepage": "https://github.com/GrahamCampbell"
                },
                {
                    "name": "Michael Dowling",
                    "email": "mtdowling@gmail.com",
                    "homepage": "https://github.com/mtdowling"
                },
                {
                    "name": "George Mponos",
                    "email": "gmponos@gmail.com",
                    "homepage": "https://github.com/gmponos"
                },
                {
                    "name": "Tobias Nyholm",
                    "email": "tobias.nyholm@gmail.com",
                    "homepage": "https://github.com/Nyholm"
                },
                {
                    "name": "Márk Sági-Kazár",
                    "email": "mark.sagikazar@gmail.com",
                    "homepage": "https://github.com/sagikazarmark"
                },
                {
                    "name": "Tobias Schultze",
                    "email": "webmaster@tubo-world.de",
                    "homepage": "https://github.com/Tobion"
                },
                {
                    "name": "Márk Sági-Kazár",
                    "email": "mark.sagikazar@gmail.com",
                    "homepage": "https://sagikazarmark.hu"
                }
            ],
            "description": "PSR-7 message implementation that also provides common utility methods",
            "keywords": [
                "http",
                "message",
                "psr-7",
                "request",
                "response",
                "stream",
                "uri",
                "url"
            ],
            "support": {
                "issues": "https://github.com/guzzle/psr7/issues",
                "source": "https://github.com/guzzle/psr7/tree/2.9.0"
            },
            "funding": [
                {
                    "url": "https://github.com/GrahamCampbell",
                    "type": "github"
                },
                {
                    "url": "https://github.com/Nyholm",
                    "type": "github"
                },
                {
                    "url": "https://tidelift.com/funding/github/packagist/guzzlehttp/psr7",
                    "type": "tidelift"
                }
            ],
            "time": "2026-03-10T16:41:02+00:00"
        },
        {
            "name": "phpmailer/phpmailer",
            "version": "v7.0.2",
            "source": {
                "type": "git",
                "url": "https://github.com/PHPMailer/PHPMailer.git",
                "reference": "ebf1655bd5b99b3f97e1a3ec0a69e5f4cd7ea088"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/PHPMailer/PHPMailer/zipball/ebf1655bd5b99b3f97e1a3ec0a69e5f4cd7ea088",
                "reference": "ebf1655bd5b99b3f97e1a3ec0a69e5f4cd7ea088",
                "shasum": ""
            },
            "require": {
                "ext-ctype": "*",
                "ext-filter": "*",
                "ext-hash": "*",
                "php": ">=5.5.0"
            },
            "require-dev": {
                "dealerdirect/phpcodesniffer-composer-installer": "^1.0",
                "doctrine/annotations": "^1.2.6 || ^1.13.3",
                "php-parallel-lint/php-console-highlighter": "^1.0.0",
                "php-parallel-lint/php-parallel-lint": "^1.3.2",
                "phpcompatibility/php-compatibility": "^10.0.0@dev",
                "squizlabs/php_codesniffer": "^3.13.5",
                "yoast/phpunit-polyfills": "^1.0.4"
            },
            "suggest": {
                "decomplexity/SendOauth2": "Adapter for using XOAUTH2 authentication",
                "directorytree/imapengine": "For uploading sent messages via IMAP, see gmail example",
                "ext-imap": "Needed to support advanced email address parsing according to RFC822",
                "ext-mbstring": "Needed to send email in multibyte encoding charset or decode encoded addresses",
                "ext-openssl": "Needed for secure SMTP sending and DKIM signing",
                "greew/oauth2-azure-provider": "Needed for Microsoft Azure XOAUTH2 authentication",
                "hayageek/oauth2-yahoo": "Needed for Yahoo XOAUTH2 authentication",
                "league/oauth2-google": "Needed for Google XOAUTH2 authentication",
                "psr/log": "For optional PSR-3 debug logging",
                "symfony/polyfill-mbstring": "To support UTF-8 if the Mbstring PHP extension is not enabled (^1.2)",
                "thenetworg/oauth2-azure": "Needed for Microsoft XOAUTH2 authentication"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "PHPMailer\\PHPMailer\\": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "LGPL-2.1-only"
            ],
            "authors": [
                {
                    "name": "Marcus Bointon",
                    "email": "phpmailer@synchromedia.co.uk"
                },
                {
                    "name": "Jim Jagielski",
                    "email": "jimjag@gmail.com"
                },
                {
                    "name": "Andy Prevost",
                    "email": "codeworxtech@users.sourceforge.net"
                },
                {
                    "name": "Brent R. Matzelle"
                }
            ],
            "description": "PHPMailer is a full-featured email creation and transfer class for PHP",
            "support": {
                "issues": "https://github.com/PHPMailer/PHPMailer/issues",
                "source": "https://github.com/PHPMailer/PHPMailer/tree/v7.0.2"
            },
            "funding": [
                {
                    "url": "https://github.com/Synchro",
                    "type": "github"
                }
            ],
            "time": "2026-01-09T18:02:33+00:00"
        },
        {
            "name": "psr/http-factory",
            "version": "1.1.0",
            "source": {
                "type": "git",
                "url": "https://github.com/php-fig/http-factory.git",
                "reference": "2b4765fddfe3b508ac62f829e852b1501d3f6e8a"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/php-fig/http-factory/zipball/2b4765fddfe3b508ac62f829e852b1501d3f6e8a",
                "reference": "2b4765fddfe3b508ac62f829e852b1501d3f6e8a",
                "shasum": ""
            },
            "require": {
                "php": ">=7.1",
                "psr/http-message": "^1.0 || ^2.0"
            },
            "type": "library",
            "extra": {
                "branch-alias": {
                    "dev-master": "1.0.x-dev"
                }
            },
            "autoload": {
                "psr-4": {
                    "Psr\\Http\\Message\\": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "PHP-FIG",
                    "homepage": "https://www.php-fig.org/"
                }
            ],
            "description": "PSR-17: Common interfaces for PSR-7 HTTP message factories",
            "keywords": [
                "factory",
                "http",
                "message",
                "psr",
                "psr-17",
                "psr-7",
                "request",
                "response"
            ],
            "support": {
                "source": "https://github.com/php-fig/http-factory"
            },
            "time": "2024-04-15T12:06:14+00:00"
        },
        {
            "name": "psr/http-message",
            "version": "2.0",
            "source": {
                "type": "git",
                "url": "https://github.com/php-fig/http-message.git",
                "reference": "402d35bcb92c70c026d1a6a9883f06b2ead23d71"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/php-fig/http-message/zipball/402d35bcb92c70c026d1a6a9883f06b2ead23d71",
                "reference": "402d35bcb92c70c026d1a6a9883f06b2ead23d71",
                "shasum": ""
            },
            "require": {
                "php": "^7.2 || ^8.0"
            },
            "type": "library",
            "extra": {
                "branch-alias": {
                    "dev-master": "2.0.x-dev"
                }
            },
            "autoload": {
                "psr-4": {
                    "Psr\\Http\\Message\\": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "PHP-FIG",
                    "homepage": "https://www.php-fig.org/"
                }
            ],
            "description": "Common interface for HTTP messages",
            "homepage": "https://github.com/php-fig/http-message",
            "keywords": [
                "http",
                "http-message",
                "psr",
                "psr-7",
                "request",
                "response"
            ],
            "support": {
                "source": "https://github.com/php-fig/http-message/tree/2.0"
            },
            "time": "2023-04-04T09:54:51+00:00"
        },
        {
            "name": "ralouphie/getallheaders",
            "version": "3.0.3",
            "source": {
                "type": "git",
                "url": "https://github.com/ralouphie/getallheaders.git",
                "reference": "120b605dfeb996808c31b6477290a714d356e822"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/ralouphie/getallheaders/zipball/120b605dfeb996808c31b6477290a714d356e822",
                "reference": "120b605dfeb996808c31b6477290a714d356e822",
                "shasum": ""
            },
            "require": {
                "php": ">=5.6"
            },
            "require-dev": {
                "php-coveralls/php-coveralls": "^2.1",
                "phpunit/phpunit": "^5 || ^6.5"
            },
            "type": "library",
            "autoload": {
                "files": [
                    "src/getallheaders.php"
                ]
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Ralph Khattar",
                    "email": "ralph.khattar@gmail.com"
                }
            ],
            "description": "A polyfill for getallheaders.",
            "support": {
                "issues": "https://github.com/ralouphie/getallheaders/issues",
                "source": "https://github.com/ralouphie/getallheaders/tree/develop"
            },
            "time": "2019-03-08T08:55:37+00:00"
        },
        {
            "name": "ratchet/rfc6455",
            "version": "v0.3.1",
            "source": {
                "type": "git",
                "url": "https://github.com/ratchetphp/RFC6455.git",
                "reference": "7c964514e93456a52a99a20fcfa0de242a43ccdb"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/ratchetphp/RFC6455/zipball/7c964514e93456a52a99a20fcfa0de242a43ccdb",
                "reference": "7c964514e93456a52a99a20fcfa0de242a43ccdb",
                "shasum": ""
            },
            "require": {
                "guzzlehttp/psr7": "^2 || ^1.7",
                "php": ">=5.4.2"
            },
            "require-dev": {
                "phpunit/phpunit": "^5.7",
                "react/socket": "^1.3"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "Ratchet\\RFC6455\\": "src"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Chris Boden",
                    "email": "cboden@gmail.com",
                    "role": "Developer"
                },
                {
                    "name": "Matt Bonneau",
                    "role": "Developer"
                }
            ],
            "description": "RFC6455 WebSocket protocol handler",
            "homepage": "http://socketo.me",
            "keywords": [
                "WebSockets",
                "rfc6455",
                "websocket"
            ],
            "support": {
                "chat": "https://gitter.im/reactphp/reactphp",
                "issues": "https://github.com/ratchetphp/RFC6455/issues",
                "source": "https://github.com/ratchetphp/RFC6455/tree/v0.3.1"
            },
            "time": "2021-12-09T23:20:49+00:00"
        },
        {
            "name": "react/cache",
            "version": "v1.2.0",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/cache.git",
                "reference": "d47c472b64aa5608225f47965a484b75c7817d5b"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/cache/zipball/d47c472b64aa5608225f47965a484b75c7817d5b",
                "reference": "d47c472b64aa5608225f47965a484b75c7817d5b",
                "shasum": ""
            },
            "require": {
                "php": ">=5.3.0",
                "react/promise": "^3.0 || ^2.0 || ^1.1"
            },
            "require-dev": {
                "phpunit/phpunit": "^9.5 || ^5.7 || ^4.8.35"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "React\\Cache\\": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Christian Lück",
                    "email": "christian@clue.engineering",
                    "homepage": "https://clue.engineering/"
                },
                {
                    "name": "Cees-Jan Kiewiet",
                    "email": "reactphp@ceesjankiewiet.nl",
                    "homepage": "https://wyrihaximus.net/"
                },
                {
                    "name": "Jan Sorgalla",
                    "email": "jsorgalla@gmail.com",
                    "homepage": "https://sorgalla.com/"
                },
                {
                    "name": "Chris Boden",
                    "email": "cboden@gmail.com",
                    "homepage": "https://cboden.dev/"
                }
            ],
            "description": "Async, Promise-based cache interface for ReactPHP",
            "keywords": [
                "cache",
                "caching",
                "promise",
                "reactphp"
            ],
            "support": {
                "issues": "https://github.com/reactphp/cache/issues",
                "source": "https://github.com/reactphp/cache/tree/v1.2.0"
            },
            "funding": [
                {
                    "url": "https://opencollective.com/reactphp",
                    "type": "open_collective"
                }
            ],
            "time": "2022-11-30T15:59:55+00:00"
        },
        {
            "name": "react/dns",
            "version": "v1.14.0",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/dns.git",
                "reference": "7562c05391f42701c1fccf189c8225fece1cd7c3"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/dns/zipball/7562c05391f42701c1fccf189c8225fece1cd7c3",
                "reference": "7562c05391f42701c1fccf189c8225fece1cd7c3",
                "shasum": ""
            },
            "require": {
                "php": ">=5.3.0",
                "react/cache": "^1.0 || ^0.6 || ^0.5",
                "react/event-loop": "^1.2",
                "react/promise": "^3.2 || ^2.7 || ^1.2.1"
            },
            "require-dev": {
                "phpunit/phpunit": "^9.6 || ^5.7 || ^4.8.36",
                "react/async": "^4.3 || ^3 || ^2",
                "react/promise-timer": "^1.11"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "React\\Dns\\": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Christian Lück",
                    "email": "christian@clue.engineering",
                    "homepage": "https://clue.engineering/"
                },
                {
                    "name": "Cees-Jan Kiewiet",
                    "email": "reactphp@ceesjankiewiet.nl",
                    "homepage": "https://wyrihaximus.net/"
                },
                {
                    "name": "Jan Sorgalla",
                    "email": "jsorgalla@gmail.com",
                    "homepage": "https://sorgalla.com/"
                },
                {
                    "name": "Chris Boden",
                    "email": "cboden@gmail.com",
                    "homepage": "https://cboden.dev/"
                }
            ],
            "description": "Async DNS resolver for ReactPHP",
            "keywords": [
                "async",
                "dns",
                "dns-resolver",
                "reactphp"
            ],
            "support": {
                "issues": "https://github.com/reactphp/dns/issues",
                "source": "https://github.com/reactphp/dns/tree/v1.14.0"
            },
            "funding": [
                {
                    "url": "https://opencollective.com/reactphp",
                    "type": "open_collective"
                }
            ],
            "time": "2025-11-18T19:34:28+00:00"
        },
        {
            "name": "react/event-loop",
            "version": "v1.6.0",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/event-loop.git",
                "reference": "ba276bda6083df7e0050fd9b33f66ad7a4ac747a"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/event-loop/zipball/ba276bda6083df7e0050fd9b33f66ad7a4ac747a",
                "reference": "ba276bda6083df7e0050fd9b33f66ad7a4ac747a",
                "shasum": ""
            },
            "require": {
                "php": ">=5.3.0"
            },
            "require-dev": {
                "phpunit/phpunit": "^9.6 || ^5.7 || ^4.8.36"
            },
            "suggest": {
                "ext-pcntl": "For signal handling support when using the StreamSelectLoop"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "React\\EventLoop\\": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Christian Lück",
                    "email": "christian@clue.engineering",
                    "homepage": "https://clue.engineering/"
                },
                {
                    "name": "Cees-Jan Kiewiet",
                    "email": "reactphp@ceesjankiewiet.nl",
                    "homepage": "https://wyrihaximus.net/"
                },
                {
                    "name": "Jan Sorgalla",
                    "email": "jsorgalla@gmail.com",
                    "homepage": "https://sorgalla.com/"
                },
                {
                    "name": "Chris Boden",
                    "email": "cboden@gmail.com",
                    "homepage": "https://cboden.dev/"
                }
            ],
            "description": "ReactPHP's core reactor event loop that libraries can use for evented I/O.",
            "keywords": [
                "asynchronous",
                "event-loop"
            ],
            "support": {
                "issues": "https://github.com/reactphp/event-loop/issues",
                "source": "https://github.com/reactphp/event-loop/tree/v1.6.0"
            },
            "funding": [
                {
                    "url": "https://opencollective.com/reactphp",
                    "type": "open_collective"
                }
            ],
            "time": "2025-11-17T20:46:25+00:00"
        },
        {
            "name": "react/promise",
            "version": "v3.3.0",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/promise.git",
                "reference": "23444f53a813a3296c1368bb104793ce8d88f04a"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/promise/zipball/23444f53a813a3296c1368bb104793ce8d88f04a",
                "reference": "23444f53a813a3296c1368bb104793ce8d88f04a",
                "shasum": ""
            },
            "require": {
                "php": ">=7.1.0"
            },
            "require-dev": {
                "phpstan/phpstan": "1.12.28 || 1.4.10",
                "phpunit/phpunit": "^9.6 || ^7.5"
            },
            "type": "library",
            "autoload": {
                "files": [
                    "src/functions_include.php"
                ],
                "psr-4": {
                    "React\\Promise\\": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Jan Sorgalla",
                    "email": "jsorgalla@gmail.com",
                    "homepage": "https://sorgalla.com/"
                },
                {
                    "name": "Christian Lück",
                    "email": "christian@clue.engineering",
                    "homepage": "https://clue.engineering/"
                },
                {
                    "name": "Cees-Jan Kiewiet",
                    "email": "reactphp@ceesjankiewiet.nl",
                    "homepage": "https://wyrihaximus.net/"
                },
                {
                    "name": "Chris Boden",
                    "email": "cboden@gmail.com",
                    "homepage": "https://cboden.dev/"
                }
            ],
            "description": "A lightweight implementation of CommonJS Promises/A for PHP",
            "keywords": [
                "promise",
                "promises"
            ],
            "support": {
                "issues": "https://github.com/reactphp/promise/issues",
                "source": "https://github.com/reactphp/promise/tree/v3.3.0"
            },
            "funding": [
                {
                    "url": "https://opencollective.com/reactphp",
                    "type": "open_collective"
                }
            ],
            "time": "2025-08-19T18:57:03+00:00"
        },
        {
            "name": "react/socket",
            "version": "v1.17.0",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/socket.git",
                "reference": "ef5b17b81f6f60504c539313f94f2d826c5faa08"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/socket/zipball/ef5b17b81f6f60504c539313f94f2d826c5faa08",
                "reference": "ef5b17b81f6f60504c539313f94f2d826c5faa08",
                "shasum": ""
            },
            "require": {
                "evenement/evenement": "^3.0 || ^2.0 || ^1.0",
                "php": ">=5.3.0",
                "react/dns": "^1.13",
                "react/event-loop": "^1.2",
                "react/promise": "^3.2 || ^2.6 || ^1.2.1",
                "react/stream": "^1.4"
            },
            "require-dev": {
                "phpunit/phpunit": "^9.6 || ^5.7 || ^4.8.36",
                "react/async": "^4.3 || ^3.3 || ^2",
                "react/promise-stream": "^1.4",
                "react/promise-timer": "^1.11"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "React\\Socket\\": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Christian Lück",
                    "email": "christian@clue.engineering",
                    "homepage": "https://clue.engineering/"
                },
                {
                    "name": "Cees-Jan Kiewiet",
                    "email": "reactphp@ceesjankiewiet.nl",
                    "homepage": "https://wyrihaximus.net/"
                },
                {
                    "name": "Jan Sorgalla",
                    "email": "jsorgalla@gmail.com",
                    "homepage": "https://sorgalla.com/"
                },
                {
                    "name": "Chris Boden",
                    "email": "cboden@gmail.com",
                    "homepage": "https://cboden.dev/"
                }
            ],
            "description": "Async, streaming plaintext TCP/IP and secure TLS socket server and client connections for ReactPHP",
            "keywords": [
                "Connection",
                "Socket",
                "async",
                "reactphp",
                "stream"
            ],
            "support": {
                "issues": "https://github.com/reactphp/socket/issues",
                "source": "https://github.com/reactphp/socket/tree/v1.17.0"
            },
            "funding": [
                {
                    "url": "https://opencollective.com/reactphp",
                    "type": "open_collective"
                }
            ],
            "time": "2025-11-19T20:47:34+00:00"
        },
        {
            "name": "react/stream",
            "version": "v1.4.0",
            "source": {
                "type": "git",
                "url": "https://github.com/reactphp/stream.git",
                "reference": "1e5b0acb8fe55143b5b426817155190eb6f5b18d"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/reactphp/stream/zipball/1e5b0acb8fe55143b5b426817155190eb6f5b18d",
                "reference": "1e5b0acb8fe55143b5b426817155190eb6f5b18d",
                "shasum": ""
            },
            "require": {
                "evenement/evenement": "^3.0 || ^2.0 || ^1.0",
                "php": ">=5.3.8",
                "react/event-loop": "^1.2"
            },
            "require-dev": {
                "clue/stream-filter": "~1.2",
                "phpunit/phpunit": "^9.6 || ^5.7 || ^4.8.36"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "React\\Stream\\": "src/"
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Christian Lück",
                    "email": "christian@clue.engineering",
                    "homepage": "https://clue.engineering/"
                },
                {
                    "name": "Cees-Jan Kiewiet",
                    "email": "reactphp@ceesjankiewiet.nl",
                    "homepage": "https://wyrihaximus.net/"
                },
                {
                    "name": "Jan Sorgalla",
                    "email": "jsorgalla@gmail.com",
                    "homepage": "https://sorgalla.com/"
                },
                {
                    "name": "Chris Boden",
                    "email": "cboden@gmail.com",
                    "homepage": "https://cboden.dev/"
                }
            ],
            "description": "Event-driven readable and writable streams for non-blocking I/O in ReactPHP",
            "keywords": [
                "event-driven",
                "io",
                "non-blocking",
                "pipe",
                "reactphp",
                "readable",
                "stream",
                "writable"
            ],
            "support": {
                "issues": "https://github.com/reactphp/stream/issues",
                "source": "https://github.com/reactphp/stream/tree/v1.4.0"
            },
            "funding": [
                {
                    "url": "https://opencollective.com/reactphp",
                    "type": "open_collective"
                }
            ],
            "time": "2024-06-11T12:45:25+00:00"
        },
        {
            "name": "symfony/deprecation-contracts",
            "version": "v3.7.0",
            "source": {
                "type": "git",
                "url": "https://github.com/symfony/deprecation-contracts.git",
                "reference": "50f59d1f3ca46d41ac911f97a78626b6756af35b"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/symfony/deprecation-contracts/zipball/50f59d1f3ca46d41ac911f97a78626b6756af35b",
                "reference": "50f59d1f3ca46d41ac911f97a78626b6756af35b",
                "shasum": ""
            },
            "require": {
                "php": ">=8.1"
            },
            "type": "library",
            "extra": {
                "thanks": {
                    "url": "https://github.com/symfony/contracts",
                    "name": "symfony/contracts"
                },
                "branch-alias": {
                    "dev-main": "3.7-dev"
                }
            },
            "autoload": {
                "files": [
                    "function.php"
                ]
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Nicolas Grekas",
                    "email": "p@tchwork.com"
                },
                {
                    "name": "Symfony Community",
                    "homepage": "https://symfony.com/contributors"
                }
            ],
            "description": "A generic function and convention to trigger deprecation notices",
            "homepage": "https://symfony.com",
            "support": {
                "source": "https://github.com/symfony/deprecation-contracts/tree/v3.7.0"
            },
            "funding": [
                {
                    "url": "https://symfony.com/sponsor",
                    "type": "custom"
                },
                {
                    "url": "https://github.com/fabpot",
                    "type": "github"
                },
                {
                    "url": "https://github.com/nicolas-grekas",
                    "type": "github"
                },
                {
                    "url": "https://tidelift.com/funding/github/packagist/symfony/symfony",
                    "type": "tidelift"
                }
            ],
            "time": "2026-04-13T15:52:40+00:00"
        },
        {
            "name": "symfony/http-foundation",
            "version": "v6.4.35",
            "source": {
                "type": "git",
                "url": "https://github.com/symfony/http-foundation.git",
                "reference": "cffffd0a2c037117b742b4f8b379a22a2a33f6d2"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/symfony/http-foundation/zipball/cffffd0a2c037117b742b4f8b379a22a2a33f6d2",
                "reference": "cffffd0a2c037117b742b4f8b379a22a2a33f6d2",
                "shasum": ""
            },
            "require": {
                "php": ">=8.1",
                "symfony/deprecation-contracts": "^2.5|^3",
                "symfony/polyfill-mbstring": "~1.1",
                "symfony/polyfill-php83": "^1.27"
            },
            "conflict": {
                "symfony/cache": "<6.4.12|>=7.0,<7.1.5"
            },
            "require-dev": {
                "doctrine/dbal": "^2.13.1|^3|^4",
                "predis/predis": "^1.1|^2.0",
                "symfony/cache": "^6.4.12|^7.1.5",
                "symfony/dependency-injection": "^5.4|^6.0|^7.0",
                "symfony/expression-language": "^5.4|^6.0|^7.0",
                "symfony/http-kernel": "^5.4.12|^6.0.12|^6.1.4|^7.0",
                "symfony/mime": "^5.4|^6.0|^7.0",
                "symfony/rate-limiter": "^5.4|^6.0|^7.0"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "Symfony\\Component\\HttpFoundation\\": ""
                },
                "exclude-from-classmap": [
                    "/Tests/"
                ]
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Fabien Potencier",
                    "email": "fabien@symfony.com"
                },
                {
                    "name": "Symfony Community",
                    "homepage": "https://symfony.com/contributors"
                }
            ],
            "description": "Defines an object-oriented layer for the HTTP specification",
            "homepage": "https://symfony.com",
            "support": {
                "source": "https://github.com/symfony/http-foundation/tree/v6.4.35"
            },
            "funding": [
                {
                    "url": "https://symfony.com/sponsor",
                    "type": "custom"
                },
                {
                    "url": "https://github.com/fabpot",
                    "type": "github"
                },
                {
                    "url": "https://github.com/nicolas-grekas",
                    "type": "github"
                },
                {
                    "url": "https://tidelift.com/funding/github/packagist/symfony/symfony",
                    "type": "tidelift"
                }
            ],
            "time": "2026-03-06T11:15:58+00:00"
        },
        {
            "name": "symfony/polyfill-mbstring",
            "version": "v1.37.0",
            "source": {
                "type": "git",
                "url": "https://github.com/symfony/polyfill-mbstring.git",
                "reference": "6a21eb99c6973357967f6ce3708cd55a6bec6315"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/symfony/polyfill-mbstring/zipball/6a21eb99c6973357967f6ce3708cd55a6bec6315",
                "reference": "6a21eb99c6973357967f6ce3708cd55a6bec6315",
                "shasum": ""
            },
            "require": {
                "ext-iconv": "*",
                "php": ">=7.2"
            },
            "provide": {
                "ext-mbstring": "*"
            },
            "suggest": {
                "ext-mbstring": "For best performance"
            },
            "type": "library",
            "extra": {
                "thanks": {
                    "url": "https://github.com/symfony/polyfill",
                    "name": "symfony/polyfill"
                }
            },
            "autoload": {
                "files": [
                    "bootstrap.php"
                ],
                "psr-4": {
                    "Symfony\\Polyfill\\Mbstring\\": ""
                }
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Nicolas Grekas",
                    "email": "p@tchwork.com"
                },
                {
                    "name": "Symfony Community",
                    "homepage": "https://symfony.com/contributors"
                }
            ],
            "description": "Symfony polyfill for the Mbstring extension",
            "homepage": "https://symfony.com",
            "keywords": [
                "compatibility",
                "mbstring",
                "polyfill",
                "portable",
                "shim"
            ],
            "support": {
                "source": "https://github.com/symfony/polyfill-mbstring/tree/v1.37.0"
            },
            "funding": [
                {
                    "url": "https://symfony.com/sponsor",
                    "type": "custom"
                },
                {
                    "url": "https://github.com/fabpot",
                    "type": "github"
                },
                {
                    "url": "https://github.com/nicolas-grekas",
                    "type": "github"
                },
                {
                    "url": "https://tidelift.com/funding/github/packagist/symfony/symfony",
                    "type": "tidelift"
                }
            ],
            "time": "2026-04-10T17:25:58+00:00"
        },
        {
            "name": "symfony/polyfill-php83",
            "version": "v1.37.0",
            "source": {
                "type": "git",
                "url": "https://github.com/symfony/polyfill-php83.git",
                "reference": "3600c2cb22399e25bb226e4a135ce91eeb2a6149"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/symfony/polyfill-php83/zipball/3600c2cb22399e25bb226e4a135ce91eeb2a6149",
                "reference": "3600c2cb22399e25bb226e4a135ce91eeb2a6149",
                "shasum": ""
            },
            "require": {
                "php": ">=7.2"
            },
            "type": "library",
            "extra": {
                "thanks": {
                    "url": "https://github.com/symfony/polyfill",
                    "name": "symfony/polyfill"
                }
            },
            "autoload": {
                "files": [
                    "bootstrap.php"
                ],
                "psr-4": {
                    "Symfony\\Polyfill\\Php83\\": ""
                },
                "classmap": [
                    "Resources/stubs"
                ]
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Nicolas Grekas",
                    "email": "p@tchwork.com"
                },
                {
                    "name": "Symfony Community",
                    "homepage": "https://symfony.com/contributors"
                }
            ],
            "description": "Symfony polyfill backporting some PHP 8.3+ features to lower PHP versions",
            "homepage": "https://symfony.com",
            "keywords": [
                "compatibility",
                "polyfill",
                "portable",
                "shim"
            ],
            "support": {
                "source": "https://github.com/symfony/polyfill-php83/tree/v1.37.0"
            },
            "funding": [
                {
                    "url": "https://symfony.com/sponsor",
                    "type": "custom"
                },
                {
                    "url": "https://github.com/fabpot",
                    "type": "github"
                },
                {
                    "url": "https://github.com/nicolas-grekas",
                    "type": "github"
                },
                {
                    "url": "https://tidelift.com/funding/github/packagist/symfony/symfony",
                    "type": "tidelift"
                }
            ],
            "time": "2026-04-10T17:25:58+00:00"
        },
        {
            "name": "symfony/routing",
            "version": "v6.4.37",
            "source": {
                "type": "git",
                "url": "https://github.com/symfony/routing.git",
                "reference": "48035d186798d27d375d95aad37db8fe097e4048"
            },
            "dist": {
                "type": "zip",
                "url": "https://api.github.com/repos/symfony/routing/zipball/48035d186798d27d375d95aad37db8fe097e4048",
                "reference": "48035d186798d27d375d95aad37db8fe097e4048",
                "shasum": ""
            },
            "require": {
                "php": ">=8.1",
                "symfony/deprecation-contracts": "^2.5|^3"
            },
            "conflict": {
                "doctrine/annotations": "<1.12",
                "symfony/config": "<6.2",
                "symfony/dependency-injection": "<5.4",
                "symfony/yaml": "<5.4"
            },
            "require-dev": {
                "doctrine/annotations": "^1.12|^2",
                "psr/log": "^1|^2|^3",
                "symfony/config": "^6.2|^7.0",
                "symfony/dependency-injection": "^5.4|^6.0|^7.0",
                "symfony/expression-language": "^5.4|^6.0|^7.0",
                "symfony/http-foundation": "^5.4|^6.0|^7.0",
                "symfony/yaml": "^5.4|^6.0|^7.0"
            },
            "type": "library",
            "autoload": {
                "psr-4": {
                    "Symfony\\Component\\Routing\\": ""
                },
                "exclude-from-classmap": [
                    "/Tests/"
                ]
            },
            "notification-url": "https://packagist.org/downloads/",
            "license": [
                "MIT"
            ],
            "authors": [
                {
                    "name": "Fabien Potencier",
                    "email": "fabien@symfony.com"
                },
                {
                    "name": "Symfony Community",
                    "homepage": "https://symfony.com/contributors"
                }
            ],
            "description": "Maps an HTTP request to a set of configuration variables",
            "homepage": "https://symfony.com",
            "keywords": [
                "router",
                "routing",
                "uri",
                "url"
            ],
            "support": {
                "source": "https://github.com/symfony/routing/tree/v6.4.37"
            },
            "funding": [
                {
                    "url": "https://symfony.com/sponsor",
                    "type": "custom"
                },
                {
                    "url": "https://github.com/fabpot",
                    "type": "github"
                },
                {
                    "url": "https://github.com/nicolas-grekas",
                    "type": "github"
                },
                {
                    "url": "https://tidelift.com/funding/github/packagist/symfony/symfony",
                    "type": "tidelift"
                }
            ],
            "time": "2026-04-18T13:45:55+00:00"
        }
    ],
    "packages-dev": [],
    "aliases": [],
    "minimum-stability": "stable",
    "stability-flags": {},
    "prefer-stable": false,
    "prefer-lowest": false,
    "platform": {},
    "platform-dev": {},
    "plugin-api-version": "2.9.0"
}
config.php
<?php
// Đảm bảo không sử dụng hardcoded ports hay hostnames trong source code 
define('BASE_URL', '/'); // Vì project đặt trực tiếp trong htdocs theo yêu cầu [cite: 118, 119]

// Cấu hình Mail (Dành cho Phase 2: Kích hoạt & Reset password) [cite: 21, 25]
// Bạn sẽ cần thư viện PHPMailer hoặc tương tự ở Phase sau.
define('MAIL_HOST', 'smtp.gmail.com');
define('MAIL_USER', 'your-email@gmail.com');
define('MAIL_PASS', 'your-app-password');
?>
database.php
<?php
// Cấu hình Database
$host = 'localhost';
$db   = 'note_management';
$user = 'root'; // Thay đổi theo máy của bạn (thường là root trên XAMPP)
$pass = '';     // Thay đổi theo máy của bạn (thường để trống trên XAMPP)
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
     // Lưu ý: Trong môi trường production không nên hiển thị lỗi chi tiết thế này
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}
?>
database.sql
-- ============================================================
-- NoteApp Pro - Database Schema (ĐÃ SỬA LỖI)
-- Sửa: thêm các cột bị thiếu trong notes, chuẩn hóa tên cột users
-- ============================================================

CREATE DATABASE IF NOT EXISTS note_management CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE note_management;

-- ============================================================
-- Bảng Users
-- SỬA: đổi 'theme' -> 'theme_color', 'font_size' default -> '16px'
-- ============================================================
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    activation_token VARCHAR(100),
    is_activated TINYINT(1) DEFAULT 0,
    reset_token VARCHAR(100) DEFAULT NULL,
    reset_token_expiry DATETIME DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT 'default-avatar.png',
    font_size VARCHAR(10) DEFAULT '16px',       -- SỬA: đổi 'medium' -> '16px'
    theme_color VARCHAR(10) DEFAULT 'light',     -- SỬA: đổi tên cột 'theme' -> 'theme_color'
    note_color VARCHAR(20) DEFAULT '#ffffff',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- Bảng Notes
-- SỬA: thêm is_trashed, color, password_hash, pinned_at
-- ============================================================
CREATE TABLE notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(255) NOT NULL DEFAULT '',
    content TEXT,
    is_pinned TINYINT(1) DEFAULT 0,
    pinned_at DATETIME DEFAULT NULL,            -- THÊM MỚI
    is_trashed TINYINT(1) DEFAULT 0,            -- THÊM MỚI: soft-delete
    color VARCHAR(20) DEFAULT NULL,             -- THÊM MỚI: màu nền ghi chú
    password_hash VARCHAR(255) DEFAULT NULL,    -- THÊM MỚI: thay note_password
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- Bảng Note Images
-- ============================================================
CREATE TABLE note_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    file_path VARCHAR(255) NOT NULL,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE
);

-- ============================================================
-- Bảng Labels
-- ============================================================
CREATE TABLE labels (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(50) NOT NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- Bảng Note_Labels (N-N)
-- ============================================================
CREATE TABLE note_labels (
    note_id INT NOT NULL,
    label_id INT NOT NULL,
    PRIMARY KEY (note_id, label_id),
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (label_id) REFERENCES labels(id) ON DELETE CASCADE
);

-- ============================================================
-- Bảng Shared Notes
-- ============================================================
CREATE TABLE shared_notes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    note_id INT NOT NULL,
    owner_id INT NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    permission ENUM('read', 'edit') DEFAULT 'read',
    shared_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (note_id) REFERENCES notes(id) ON DELETE CASCADE,
    FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ============================================================
-- Script ALTER TABLE nếu database đã tồn tại (chạy thay vì tạo mới)
-- ============================================================
-- ALTER TABLE users
--   CHANGE COLUMN theme theme_color VARCHAR(10) DEFAULT 'light',
--   MODIFY COLUMN font_size VARCHAR(10) DEFAULT '16px';

-- ALTER TABLE notes
--   ADD COLUMN IF NOT EXISTS is_trashed TINYINT(1) DEFAULT 0,
--   ADD COLUMN IF NOT EXISTS color VARCHAR(20) DEFAULT NULL,
--   ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) DEFAULT NULL,
--   ADD COLUMN IF NOT EXISTS pinned_at DATETIME DEFAULT NULL;

-- UPDATE notes SET is_trashed = 0 WHERE is_trashed IS NULL;
index.php
<?php
require_once 'api/auth_helper.php';
check_login();

// Avatar mặc định
$default_avatar = 'uploads/avatars/default-avatar.png';

$user_font_size  = $_SESSION['font_size']   ?? '16px';
$user_theme      = $_SESSION['theme_color'] ?? 'light';
$user_note_color = $_SESSION['note_color']  ?? '#ffffff';

$user_avatar = !empty($_SESSION['avatar'])
    ? $_SESSION['avatar']
    : $default_avatar;
?>
<!DOCTYPE html>
<html lang="vi" data-bs-theme="<?= htmlspecialchars($user_theme) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NoteApp Pro</title>

    <script>
        (function() {
            const saved = localStorage.getItem('noteapp_theme');
            const phpTheme = "<?= htmlspecialchars($user_theme) ?>";
            document.documentElement.setAttribute('data-bs-theme', saved || phpTheme);
        })();
    </script>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">

    <style>
        body { font-size: <?= htmlspecialchars($user_font_size) ?> !important; }
    </style>
</head>
<body class="bg-body text-body">

<nav class="navbar navbar-expand-lg shadow-sm">
    <div class="container">
        <a class="navbar-brand fw-bold" href="#">📝 NoteApp</a>
        <form class="d-flex mx-auto w-50" onsubmit="return false;">
            <input class="form-control me-2" type="search" id="searchInput" placeholder="Tìm kiếm ghi chú..." oninput="liveSearch()">
        </form>
        <div class="d-flex align-items-center gap-3">
            <span class="small d-none d-md-inline">Chào, <?= htmlspecialchars($_SESSION['display_name'] ?? 'Bạn') ?>!</span>
            <img src="<?= htmlspecialchars($user_avatar) ?>?v=<?= time() ?>" 
                 class="nav-avatar rounded-circle" 
                 onclick="new bootstrap.Modal(document.getElementById('profileModal')).show()" 
                 title="Cài đặt tài khoản"
                 style="width:32px;height:32px;object-fit:cover;cursor:pointer;">
            <a href="logout.php" class="btn btn-danger btn-sm">Thoát</a>
        </div>
    </div>
</nav>

<?php if (isset($_SESSION['is_activated']) && $_SESSION['is_activated'] == 0): ?>
<div class="alert alert-warning alert-dismissible fade show mx-3 mt-3 shadow-sm" role="alert">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <strong>Tài khoản chưa được xác minh!</strong>
    Vui lòng kiểm tra email và click vào link kích hoạt.
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<div class="container mt-4">
    <div class="d-flex flex-wrap align-items-center justify-content-between bg-body-tertiary p-3 rounded mb-4 shadow-sm border">
        <div id="labelFilterBar" class="d-flex flex-wrap gap-2 align-items-center"></div>
        <div class="d-flex gap-2 mt-2 mt-md-0">
            <div class="input-group input-group-sm w-auto" id="addLabelGroup">
                <input type="text" id="newLabelName" class="form-control" placeholder="Tên nhãn mới...">
                <button class="btn btn-primary" onclick="addNewLabel()">Tạo</button>
            </div>
            <button id="btnViewShared" class="btn btn-sm btn-outline-info" onclick="setViewMode('shared')">
                <i class="bi bi-people"></i> Được chia sẻ
            </button>
            <button id="btnViewTrash" class="btn btn-sm btn-outline-danger" onclick="setViewMode('trash')">
                <i class="bi bi-trash3"></i> Thùng rác
            </button>
            <button id="btnViewMyNotes" class="btn btn-sm btn-primary" onclick="setViewMode('my_notes')" style="display:none;">
                <i class="bi bi-house"></i> Ghi chú của tôi
            </button>
        </div>
    </div>

    <div class="d-flex justify-content-between mb-4">
        <button id="btnCreateNote" class="btn btn-primary shadow-sm px-4 fw-bold" onclick="openNoteModal()">
            <i class="bi bi-plus-lg"></i> Tạo ghi chú mới
        </button>
        <h4 id="viewTitle" class="text-secondary fw-bold m-0 align-self-center" style="display:none;"></h4>
        <div class="btn-group shadow-sm">
            <button class="btn btn-outline-secondary" onclick="setView('grid')"><i class="bi bi-grid"></i></button>
            <button class="btn btn-outline-secondary" onclick="setView('list')"><i class="bi bi-list"></i></button>
        </div>
    </div>

    <div id="notesContainer" class="note-grid-view pb-5"></div>
</div>

<!-- ==================== MODALS ==================== -->
<?php include 'modals.php'; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- APP CONFIG -->
<script>
    window.APP_CONFIG = {
        userId:   <?= (int)($_SESSION['user_id'] ?? 0) ?>,
        userName: "<?= addslashes($_SESSION['display_name'] ?? 'User') ?>"
    };
</script>

<!-- Main JS -->
<script src="assets/js/app.js"></script>

<!-- Service Worker -->
<script>
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('/service-worker.js')
            .then(() => console.log('SW registered'))
            .catch(err => console.log('SW failed:', err));
    });
}
</script>

<!-- Floating Button -->
<button class="floating-create btn btn-primary btn-lg rounded-circle shadow" 
        onclick="openNoteModal()" 
        style="position:fixed; bottom:25px; right:25px; width:65px; height:65px; z-index:1050;">
    <i class="bi bi-plus-lg fs-3"></i>
</button>

</body>
</html>

modals.php
<!-- Modal ghi chú chính -->
<div class="modal fade" id="noteModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content shadow-lg" id="modalContentWrapper">
            <div class="modal-header border-0 pb-0">
                <input type="text" id="noteTitle" class="form-control border-0 fs-3 fw-bold bg-transparent" placeholder="Tiêu đề..." oninput="autoSave()">
                <span id="wsStatusBadge" class="badge bg-secondary ms-2 small" style="display:none;"></span>
                <button type="button" class="btn-close" onclick="closeAndReload()"></button>
            </div>
            <div class="modal-body pt-2">
                <div id="sharedNotice" class="alert alert-info py-2 small" style="display:none;"></div>
                <div id="wsPresenceBar" class="alert alert-success py-1 small mb-2" style="min-height:0;"></div>
                <div id="wsTypingIndicator" class="text-muted small fst-italic mb-2" style="display:none;"></div>

                <input type="hidden" id="noteId" value="">
                <textarea id="noteContent" class="form-control border-0 bg-transparent mb-3" rows="10" placeholder="Bạn đang nghĩ gì?..." oninput="autoSave()"></textarea>
                <div id="imagePreviewContainer" class="d-flex flex-wrap gap-2 mb-3"></div>

                <!-- Color -->
                <div id="colorSection" class="mb-3" style="display:none;">
                    <span class="small text-muted me-2"><i class="bi bi-palette"></i> Màu:</span>
                    <span class="color-btn" style="background:#ffffff" onclick="changeColor('')"></span>
                    <span class="color-btn" style="background:#f28b82" onclick="changeColor('#f28b82')"></span>
                    <span class="color-btn" style="background:#fbbc04" onclick="changeColor('#fbbc04')"></span>
                    <span class="color-btn" style="background:#fff475" onclick="changeColor('#fff475')"></span>
                    <span class="color-btn" style="background:#ccff90" onclick="changeColor('#ccff90')"></span>
                    <span class="color-btn" style="background:#a7ffeb" onclick="changeColor('#a7ffeb')"></span>
                    <span class="color-btn" style="background:#cbf0f8" onclick="changeColor('#cbf0f8')"></span>
                    <span class="color-btn" style="background:#d7aefb" onclick="changeColor('#d7aefb')"></span>
                </div>

                <!-- Share -->
                <div class="p-3 bg-body-secondary rounded border mb-3" id="shareManagerSection" style="display:none;">
                    <h6 class="fw-bold mb-3"><i class="bi bi-person-plus"></i> Chia sẻ ghi chú này</h6>
                    <div class="input-group input-group-sm mb-2">
                        <input type="text" id="share_input" class="form-control" placeholder="Nhập email (cách nhau bởi dấu phẩy)...">
                        <select id="sharePermission" class="form-select" style="max-width: 140px;">
                            <option value="read">Chỉ xem</option>
                            <option value="edit">Cho phép sửa</option>
                        </select>
                        <button class="btn btn-success" onclick="shareNote()">Chia sẻ</button>
                    </div>
                    <small class="text-muted">Ví dụ: user1@gmail.com, user2@gmail.com</small>
                    <ul id="sharedUsersList" class="list-group list-group-flush small mt-3"></ul>
                </div>

                <!-- Tools -->
                <div class="p-3 bg-body-tertiary rounded border" id="toolsSection" style="display:none;">
                    <div class="row g-2">
                        <div class="col-md-4">
                            <label class="btn btn-outline-primary btn-sm w-100">
                                <i class="bi bi-image"></i> Ảnh
                                <input type="file" id="imageInput" hidden accept="image/*" onchange="uploadImage()">
                            </label>
                        </div>
                        <div class="col-md-4">
                            <button class="btn btn-outline-warning btn-sm w-100" id="btnLock" onclick="toggleLock()">
                                <i class="bi bi-lock"></i> Khóa
                            </button>
                        </div>
                        <div class="col-md-4">
                            <select id="labelSelector" class="form-select form-select-sm" onchange="addLabelToNote()">
                                <option value="">+ Nhãn</option>
                            </select>
                        </div>
                    </div>
                    <div id="noteLabelsContainer" class="mt-3 d-flex flex-wrap gap-2"></div>
                </div>
            </div>

            <div class="modal-footer border-0 d-flex justify-content-between align-items-center">
                <div>
                    <button class="btn btn-outline-danger btn-sm" id="btnTrashNote" onclick="deleteNote('trash')" style="display:none;">
                        <i class="bi bi-trash"></i> Xóa (Vào thùng rác)
                    </button>
                    <button class="btn btn-success btn-sm" id="btnRestoreNote" onclick="restoreNote()" style="display:none;">
                        <i class="bi bi-arrow-counterclockwise"></i> Khôi phục
                    </button>
                    <button class="btn btn-danger btn-sm ms-2" id="btnDeletePermanent" onclick="deleteNote('permanent')" style="display:none;">
                        <i class="bi bi-x-octagon"></i> Xóa vĩnh viễn
                    </button>
                </div>
                <span id="saveStatus" class="text-muted small fst-italic"></span>
            </div>
        </div>
    </div>
</div>

<!-- Modal Profile -->
<div class="modal fade" id="profileModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content shadow">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-gear"></i> Cài đặt tài khoản</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="previewAvatar" src="<?= htmlspecialchars($user_avatar) ?>" class="rounded-circle mb-3 border" style="width:120px;height:120px;object-fit:cover;">
                <div class="mb-4">
                    <label class="btn btn-outline-primary btn-sm rounded-pill px-3">
                        <i class="bi bi-camera"></i> Đổi ảnh đại diện
                        <input type="file" id="inputAvatar" hidden accept="image/*" onchange="previewImage(this)">
                    </label>
                </div>
                <hr>
                <div class="row text-start g-3 mt-2">
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Kích thước chữ</label>
                        <select id="settingFontSize" class="form-select">
                            <option value="14px">Nhỏ</option>
                            <option value="16px" selected>Vừa</option>
                            <option value="18px">Lớn</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Giao diện</label>
                        <select id="settingTheme" class="form-select">
                            <option value="light">Sáng</option>
                            <option value="dark">Tối</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold small text-muted">Màu ghi chú</label>
                        <input type="color" id="settingNoteColor" class="form-control form-control-color w-100" value="<?= htmlspecialchars($user_note_color) ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer bg-body-tertiary">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" class="btn btn-success px-4" onclick="saveProfile()">
                    <i class="bi bi-check2"></i> Lưu thay đổi
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Nhập Mật Khẩu -->
<div class="modal fade" id="passwordModal" tabindex="-1" data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="passwordModalTitle">🔒 Nhập mật khẩu ghi chú</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="password" id="notePasswordInput" class="form-control" placeholder="Nhập mật khẩu..." autocomplete="current-password">
                <div id="passwordError" class="text-danger mt-2 small" style="display:none;"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                <button type="button" id="passwordModalConfirmBtn" class="btn btn-primary" onclick="submitNotePassword()">Xác nhận</button>
            </div>
        </div>
    </div>
</div>

login.php
<?php
session_start();
require_once 'database.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($email) || empty($password)) {
        $error = "Vui lòng nhập đầy đủ thông tin!";
    } else {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password_hash'])) {
                $error = "Email hoặc mật khẩu không chính xác!";
            } else {
                // === CHO PHÉP ĐĂNG NHẬP DÙ CHƯA KÍCH HOẠT ===
                $_SESSION['user_id']      = $user['id'];
                $_SESSION['display_name'] = $user['display_name'];
                $_SESSION['avatar']       = $user['avatar'] ?? 'default-avatar.png';
                $_SESSION['font_size']    = $user['font_size'] ?? '16px';
                $_SESSION['theme_color']  = $user['theme_color'] ?? 'light';
                $_SESSION['note_color']   = $user['note_color'] ?? '#ffffff';
                $_SESSION['is_activated'] = (int)$user['is_activated'];

                header("Location: index.php");
                exit;
            }
        } catch (PDOException $e) {
            $error = "Lỗi hệ thống!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng nhập - Note App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background:#f4f7f6; height:100vh; display:flex; align-items:center; }
        .login-card { border:none; border-radius:15px; box-shadow:0 10px 30px rgba(0,0,0,0.1); }
    </style>
</head>
<body>
<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-4">
            <div class="card login-card p-4">
                <h2 class="text-center mb-4 fw-bold text-primary">Đăng Nhập</h2>

                <?php if ($error): ?>
                    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                <?php endif; ?>

                <?php if (isset($_GET['activated'])): ?>
                    <div class="alert alert-success">Kích hoạt tài khoản thành công! Hãy đăng nhập.</div>
                <?php endif; ?>

                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mật khẩu</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Đăng nhập</button>
                </form>

                <div class="mt-4 text-center">
                    <a href="reset_password.php" class="text-decoration-none small">Quên mật khẩu?</a>
                    <hr>
                    <p>Chưa có tài khoản? <a href="register.php">Đăng ký ngay</a></p>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
logout.php
<?php
session_start();
session_destroy();
header("Location: login.php");
exit();
?>
mail_config.php
<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/vendor/autoload.php';

function sendActivationEmail($toEmail, $displayName, $token)
{
    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'trandanh020906@gmail.com';
        $mail->Password = 'jwlpqzuycmtidnli';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->CharSet = 'UTF-8';
        $mail->setFrom('trandanh020906@gmail.com', 'Note App');
        $mail->addAddress($toEmail, $displayName);

        $activationLink = "http://localhost/activate.php?token=" . $token;

        $mail->isHTML(true);
        $mail->Subject = 'Kích hoạt tài khoản Note App';

        $mail->Body = "
            <div style='font-family:Arial;line-height:1.6; max-width:600px;margin:auto;border:1px solid #eee;padding:20px;'>
                <h2 style='color:#0d6efd;'>Xin chào {$displayName}</h2>
                <p>Cảm ơn bạn đã đăng ký Note App.</p>
                <p>Click nút bên dưới để kích hoạt tài khoản:</p>
                <div style='text-align:center;margin:30px 0;'>
                    <a href='{$activationLink}' style='display:inline-block;padding:12px 30px;background:#0d6efd;color:#fff;text-decoration:none;border-radius:5px;font-weight:bold;'>Kích hoạt ngay</a>
                </div>
            </div>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Activation Email Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Gửi OTP khôi phục mật khẩu
 */
function sendResetOTPEmail($toEmail, $displayName, $otp)
{
    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'trandanh020906@gmail.com';
        $mail->Password = 'jwlpqzuycmtidnli';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->SMTPOptions = array(
            'ssl' => array('verify_peer' => false, 'verify_peer_name' => false, 'allow_self_signed' => true)
        );

        $mail->CharSet = 'UTF-8';
        $mail->setFrom('trandanh020906@gmail.com', 'Note App');
        $mail->addAddress($toEmail, $displayName);

        $mail->isHTML(true);
        $mail->Subject = 'Mã OTP khôi phục mật khẩu';

        $mail->Body = "
            <div style='font-family:Arial,sans-serif;max-width:600px;margin:auto;padding:20px;border:1px solid #ddd;border-radius:8px;'>
                <h2 style='color:#0d6efd;'>Xin chào {$displayName}</h2>
                <p>Mã OTP của bạn là:</p>
                <h1 style='text-align:center;color:#0d6efd;letter-spacing:8px;'>{$otp}</h1>
                <p>Mã này có hiệu lực trong 15 phút.</p>
            </div>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Reset OTP Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Gửi Link Reset Password
 */
function sendResetLinkEmail($toEmail, $displayName, $reset_token)
{
    $mail = new PHPMailer(true);

    try {
        $mail->SMTPDebug = 0;
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'trandanh020906@gmail.com';
        $mail->Password = 'jwlpqzuycmtidnli';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );

        $mail->CharSet = 'UTF-8';
        $mail->setFrom('trandanh020906@gmail.com', 'Note App');
        $mail->addAddress($toEmail, $displayName);

        $resetLink = "http://localhost/reset_password.php?token=" . $reset_token;

        $mail->isHTML(true);
        $mail->Subject = 'Đặt lại mật khẩu - Note App';

        $mail->Body = "
            <div style='font-family:Arial,sans-serif; max-width:600px; margin:auto; padding:25px; border:1px solid #ddd; border-radius:10px;'>
                <h2 style='color:#0d6efd;'>Xin chào {$displayName},</h2>
                <p>Bạn đã yêu cầu đặt lại mật khẩu cho tài khoản Note App.</p>
                <p>Vui lòng click vào nút bên dưới để đặt lại mật khẩu:</p>
                
                <div style='text-align:center; margin:35px 0;'>
                    <a href='{$resetLink}' 
                       style='display:inline-block; padding:14px 32px; background:#0d6efd; color:white; 
                              text-decoration:none; border-radius:6px; font-weight:bold; font-size:16px;'>
                        ĐẶT LẠI MẬT KHẨU
                    </a>
                </div>
                
                <p style='color:#666; font-size:14px;'>
                    Link này có hiệu lực trong <strong>15 phút</strong>.<br>
                    Nếu bạn không yêu cầu, vui lòng bỏ qua email này.
                </p>
            </div>
        ";

        $mail->send();
        return true;

    } catch (Exception $e) {
        error_log("Reset Link Email Error: " . $e->getMessage());
        return false;
    }
}
manifest.json
{
    "name": "NoteApp Pro",
    "short_name": "NoteApp",
    "description": "Ứng dụng ghi chú thông minh",
    "start_url": "/index.php",
    "display": "standalone",
    "background_color": "#f8fafc",
    "theme_color": "#6ea8ff",
    "orientation": "portrait-primary",
    "icons": [
        {
            "src": "/uploads/avatars/default-avatar.png",
            "sizes": "192x192",
            "type": "image/png"
        },
        {
            "src": "/uploads/avatars/default-avatar.png",
            "sizes": "512x512",
            "type": "image/png"
        }
    ]
}

register.php
<?php
require_once 'database.php';
require_once 'mail_config.php';

session_start(); // Thêm session_start

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email            = trim($_POST['email'] ?? '');
    $display_name     = trim($_POST['display_name'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($email) || empty($display_name) || empty($password) || empty($confirm_password)) {
        $error = "Vui lòng nhập đầy đủ thông tin!";
    } elseif ($password !== $confirm_password) {
        $error = "Mật khẩu xác nhận không khớp!";
    } elseif (strlen($password) < 6) {
        $error = "Mật khẩu phải có ít nhất 6 ký tự!";
    } else {
        try {
            $activation_token = bin2hex(random_bytes(32));
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            $stmt = $pdo->prepare("
                INSERT INTO users (email, display_name, password_hash, activation_token, is_activated)
                VALUES (?, ?, ?, ?, 0)
            ");

            $stmt->execute([$email, $display_name, $hashed_password, $activation_token]);

            $user_id = $pdo->lastInsertId();

            // === TỰ ĐỘNG ĐĂNG NHẬP SAU KHI ĐĂNG KÝ ===
            $_SESSION['user_id']       = $user_id;
            $_SESSION['display_name']  = $display_name;
            $_SESSION['is_activated']  = 0;
            $_SESSION['avatar']        = 'default-avatar.png';
            $_SESSION['font_size']     = '16px';
            $_SESSION['theme_color']   = 'light';
            $_SESSION['note_color']    = '#ffffff';

            // Gửi email kích hoạt
            $mailSent = sendActivationEmail($email, $display_name, $activation_token);

            if ($mailSent) {
                header("Location: index.php?registered=1");
                exit;
            } else {
                $error = "Đăng ký thành công nhưng không gửi được email kích hoạt.";
            }
        } catch (PDOException $e) {
            $error = "Email này đã được sử dụng!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Đăng ký - Note App</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container mt-5">
    <div class="row justify-content-center">
        <div class="col-md-5">
            <div class="card shadow">
                <div class="card-body">
                    <h3 class="card-title text-center mb-4">Đăng ký tài khoản</h3>
                    <?php if ($error): ?>
                        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
                    <?php endif; ?>

                    <form method="POST">
                        <div class="mb-3">
                            <label>Email</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Tên hiển thị</label>
                            <input type="text" name="display_name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Mật khẩu</label>
                            <input type="password" name="password" class="form-control" minlength="6" required>
                        </div>
                        <div class="mb-3">
                            <label>Xác nhận mật khẩu</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">Đăng ký ngay</button>
                    </form>
                    <div class="mt-3 text-center">
                        Đã có tài khoản? <a href="login.php">Đăng nhập</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>

reset_password.php
<?php
session_start();
date_default_timezone_set('Asia/Ho_Chi_Minh');

require_once 'database.php';
require_once 'mail_config.php';

$step = 1;
$message = '';
$token_verified = false;

// XỬ LÝ TOKEN TỪ LINK EMAIL
$token = $_GET['token'] ?? '';

if (!empty($token)) {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE reset_token = ? AND reset_token_expiry > NOW() LIMIT 1");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if ($user) {
        $_SESSION['reset_user_id'] = $user['id'];
        $step = 2;
        $token_verified = true;
    } else {
        $message = "<div class='alert alert-danger'>Link đặt lại mật khẩu không hợp lệ hoặc đã hết hạn!</div>";
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $action = $_POST['action'] ?? '';

    if ($action === 'send_reset') {

        $email = trim($_POST['email'] ?? '');
        $type  = $_POST['type'] ?? 'otp';

        $stmt = $pdo->prepare("SELECT id, display_name FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {

            $reset_token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

            $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE id = ?")
                ->execute([$reset_token, $expiry, $user['id']]);

            if ($type === 'link') {

                $sent = sendResetLinkEmail($email, $user['display_name'], $reset_token);
                $msg = "Link đặt lại mật khẩu đã được gửi đến email của bạn!";

            } else {

                $otp = rand(100000, 999999);

                $pdo->prepare("UPDATE users SET reset_token = ? WHERE id = ?")
                    ->execute([$otp, $user['id']]);

                $sent = sendResetOTPEmail($email, $user['display_name'], $otp);
                $msg = "Mã OTP đã được gửi đến email của bạn!";
            }

            if ($sent) {

                $_SESSION['reset_user_id'] = $user['id'];
                $step = 2;

                $message = "<div class='alert alert-success'>$msg</div>";

            } else {

                $message = "<div class='alert alert-danger'>Không thể gửi email. Vui lòng thử lại sau.</div>";
            }

        } else {

            $message = "<div class='alert alert-danger'>Email không tồn tại!</div>";
        }

    } elseif ($action === 'reset_password') {

        $input = trim($_POST['otp'] ?? '');
        $new_pass = $_POST['new_password'] ?? '';
        $user_id = $_SESSION['reset_user_id'] ?? 0;

        if (strlen($new_pass) < 6) {

            $message = "<div class='alert alert-danger'>Mật khẩu phải có ít nhất 6 ký tự!</div>";
            $step = 2;

        } elseif ($user_id == 0) {

            $message = "<div class='alert alert-danger'>Phiên hết hạn!</div>";
            $step = 1;

        } else {

            $stmt = $pdo->prepare("SELECT reset_token, reset_token_expiry FROM users WHERE id = ?");
            $stmt->execute([$user_id]);

            $user = $stmt->fetch();

            $is_valid = $token_verified ||
                (
                    $user &&
                    $user['reset_token'] == $input &&
                    date('Y-m-d H:i:s') <= $user['reset_token_expiry']
                );

            if ($is_valid) {

                $hashed = password_hash($new_pass, PASSWORD_BCRYPT);

                $pdo->prepare("
                    UPDATE users 
                    SET password_hash = ?, 
                        reset_token = NULL, 
                        reset_token_expiry = NULL 
                    WHERE id = ?
                ")->execute([$hashed, $user_id]);

                unset($_SESSION['reset_user_id']);

                header("Location: login.php?reset=success");
                exit;

            } else {

                $message = "<div class='alert alert-danger'>Mã không đúng hoặc đã hết hạn!</div>";
                $step = 2;
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Khôi phục mật khẩu</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <style>
        body {
            background: #f4f7f6;
            display: flex;
            align-items: center;
            min-height: 100vh;
        }

        .card {
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
    </style>
</head>

<body>

<div class="container">

    <div class="row justify-content-center">

        <div class="col-md-5">

            <div class="card p-4">

                <h3 class="text-center mb-4 text-primary">
                    Khôi phục mật khẩu
                </h3>

                <?= $message ?>

                <?php if ($step == 1): ?>

                    <form method="POST">

                        <input type="hidden" name="action" value="send_reset">

                        <div class="mb-3">
                            <label class="form-label">
                                Nhập email tài khoản
                            </label>

                            <input type="email"
                                   name="email"
                                   class="form-control"
                                   required>
                        </div>

                        <div class="mb-3">

                            <label>Chọn phương thức:</label><br>

                            <input type="radio"
                                   name="type"
                                   value="otp"
                                   checked> OTP<br>

                            <input type="radio"
                                   name="type"
                                   value="link"> Link Reset
                        </div>

                        <button type="submit"
                                class="btn btn-primary w-100">
                            Gửi
                        </button>

                    </form>

                <?php endif; ?>

                <?php if ($step == 2): ?>

                    <form method="POST">

                        <input type="hidden"
                               name="action"
                               value="reset_password">

                        <?php if (!$token_verified): ?>

                            <div class="mb-3">

                                <label class="form-label">
                                    Nhập mã OTP hoặc Token
                                </label>

                                <input type="text"
                                       name="otp"
                                       class="form-control"
                                       required>

                            </div>

                        <?php else: ?>

                            <input type="hidden"
                                   name="otp"
                                   value="verified_via_link">

                        <?php endif; ?>

                        <div class="mb-3">

                            <label class="form-label">
                                Mật khẩu mới
                            </label>

                            <input type="password"
                                   name="new_password"
                                   class="form-control"
                                   minlength="6"
                                   required>

                        </div>

                        <button type="submit"
                                class="btn btn-success w-100">
                            Đổi mật khẩu
                        </button>

                    </form>

                <?php endif; ?>

            </div>

        </div>

    </div>

</div>

</body>
</html>
Rubrik.docx
//rỗng
service-worker.js
// ====================== SERVICE WORKER - NOTEAPP PRO ======================
const CACHE_NAME = 'noteapp-v1.3'; // Tăng version khi có thay đổi lớn

// Cài đặt Service Worker
self.addEventListener('install', event => {
    console.log('[SW] Service Worker installing...');
    self.skipWaiting(); // Kích hoạt ngay lập tức
});

// Kích hoạt và dọn dẹp cache cũ
self.addEventListener('activate', event => {
    console.log('[SW] Service Worker activating...');
    event.waitUntil(
        caches.keys().then(cacheNames => {
            return Promise.all(
                cacheNames.map(cacheName => {
                    if (cacheName !== CACHE_NAME) {
                        console.log('[SW] Deleting old cache:', cacheName);
                        return caches.delete(cacheName);
                    }
                })
            );
        })
    );
    self.clients.claim();
});

// Xử lý Fetch requests
self.addEventListener('fetch', event => {
    const url = new URL(event.request.url);

    // Bỏ qua WebSocket
    if (url.protocol === 'ws:' || url.protocol === 'wss:') {
        return;
    }

    // Luôn fetch mới cho API và các file PHP (không cache)
    if (
        url.pathname.startsWith('/api/') || 
        url.pathname.endsWith('.php') ||
        url.pathname === '/'
    ) {
        event.respondWith(fetch(event.request));
        return;
    }

    // POST requests luôn fetch trực tiếp (không cache)
    if (event.request.method !== 'GET') {
        event.respondWith(fetch(event.request));
        return;
    }

    // ==================== CACHE STRATEGY CHO STATIC ASSETS ====================
    const isCacheable = 
        url.pathname.startsWith('/assets/') ||
        url.pathname.startsWith('/uploads/avatars/') ||
        url.pathname.endsWith('.css') ||
        url.pathname.endsWith('.js') ||
        url.pathname.endsWith('.png') ||
        url.pathname.endsWith('.jpg') ||
        url.pathname.endsWith('.jpeg') ||
        url.pathname.endsWith('.webp') ||
        url.pathname.endsWith('.gif') ||
        url.pathname.endsWith('.svg') ||
        url.pathname.endsWith('.json') ||
        url.pathname.endsWith('.manifest');

    if (isCacheable) {
        event.respondWith(
            caches.open(CACHE_NAME).then(cache => {
                return cache.match(event.request).then(cachedResponse => {
                    // Network First + Cache Fallback
                    return fetch(event.request)
                        .then(networkResponse => {
                            // Chỉ cache response hợp lệ
                            if (networkResponse && networkResponse.status === 200) {
                                const responseClone = networkResponse.clone();
                                cache.put(event.request, responseClone);
                            }
                            return networkResponse;
                        })
                        .catch(() => {
                            // Offline → trả về từ cache
                            if (cachedResponse) {
                                return cachedResponse;
                            }
                            // Fallback nếu không có cache
                            return new Response('Offline', { 
                                status: 503, 
                                statusText: 'Service Unavailable' 
                            });
                        });
                });
            })
        );
    } else {
        // Các tài nguyên khác: Network Only
        event.respondWith(fetch(event.request));
    }
});

// Optional: Background Sync (nếu cần sau này)
self.addEventListener('sync', event => {
    if (event.tag === 'sync-notes') {
        console.log('[SW] Background sync triggered');
        // Xử lý đồng bộ offline nếu có
    }
});

console.log('[SW] NoteApp Service Worker loaded successfully!');

API
auth_helper.php
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function is_logged_in() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function check_login() {
    if (!is_logged_in()) {
        // Nếu là request AJAX thì trả JSON
        if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Chưa đăng nhập']);
            exit();
        }
        
        // Request bình thường → redirect về login
        header("Location: login.php");
        exit();
    }
}

change_color.php
<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

$id = $_POST['id'] ?? 0;
$color = $_POST['color'] ?? '';

$stmt = $pdo->prepare("UPDATE notes SET color = ? WHERE id = ? AND user_id = ?");
$stmt->execute([$color, $id, $_SESSION['user_id']]);

echo json_encode(['success' => true]);
?>

delete_image.php
<?php
// api/delete_image.php
// SỬA WARN: Thêm kiểm tra quyền sở hữu trước khi xóa ảnh
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$id = intval($_POST['id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID ảnh không hợp lệ.']);
    exit;
}

try {
    // SỬA: Kiểm tra ảnh này có thuộc về ghi chú của user hiện tại không
    $stmt = $pdo->prepare(
        "SELECT ni.id, ni.file_path
         FROM note_images ni
         JOIN notes n ON ni.note_id = n.id
         WHERE ni.id = ? AND n.user_id = ?"
    );
    $stmt->execute([$id, $user_id]);
    $img = $stmt->fetch();

    if (!$img) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền xóa ảnh này.']);
        exit;
    }

    // Xóa file vật lý
    $physical_path = '../' . $img['file_path'];
    if (file_exists($physical_path)) {
        unlink($physical_path);
    }

    // Xóa record trong database
    $stmt = $pdo->prepare("DELETE FROM note_images WHERE id = ?");
    $stmt->execute([$id]);

    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

delete_note.php
<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$id       = intval($_POST['id']       ?? 0);
$action   = $_POST['action']          ?? 'trash'; // 'trash' hoặc 'permanent'
$password = $_POST['delete_password'] ?? '';      // mật khẩu xác nhận xóa (nếu note bị khóa)
$user_id  = $_SESSION['user_id'];

if ($id <= 0) {
    echo json_encode(['success' => false, 'error' => 'ID không hợp lệ']);
    exit;
}

try {
    // Lấy thông tin note: kiểm tra chủ sở hữu và trạng thái khóa
    $stmt = $pdo->prepare("SELECT password_hash FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $user_id]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$note) {
        echo json_encode(['success' => false, 'error' => 'Không tìm thấy ghi chú hoặc bạn không có quyền!']);
        exit;
    }

    // Nếu note có mật khẩu → bắt buộc phải xác thực trước khi xóa
    if (!empty($note['password_hash'])) {
        if (empty($password)) {
            echo json_encode([
                'success'       => false,
                'require_password' => true,
                'message'       => 'Ghi chú này được bảo vệ bằng mật khẩu. Vui lòng nhập mật khẩu để xác nhận xóa!'
            ]);
            exit;
        }

        if (!password_verify($password, $note['password_hash'])) {
            echo json_encode([
                'success'       => false,
                'require_password' => true,
                'message'       => 'Mật khẩu không đúng!'
            ]);
            exit;
        }
    }

    // Thực hiện xóa
    if ($action === 'trash') {
        $stmt = $pdo->prepare("UPDATE notes SET is_trashed = 1, is_pinned = 0 WHERE id = ? AND user_id = ?");
    } else {
        $stmt = $pdo->prepare("DELETE FROM notes WHERE id = ? AND user_id = ?");
    }

    $stmt->execute([$id, $user_id]);
    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

get_note_images.php
<?php
// api/get_note_images.php
// SỬA CRIT 2: Cho phép người được chia sẻ xem ảnh của ghi chú
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$note_id = intval($_GET['note_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($note_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    // Lấy email của user hiện tại để kiểm tra shared_notes
    $meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $meStmt->execute([$user_id]);
    $my_email = $meStmt->fetchColumn();

    // SỬA: Truy vấn kiểm tra cả chủ sở hữu VÀ người được chia sẻ
    $sql = "SELECT ni.id, ni.file_path
            FROM note_images ni
            JOIN notes n ON ni.note_id = n.id
            WHERE ni.note_id = ?
              AND (
                n.user_id = ?
                OR EXISTS (
                    SELECT 1 FROM shared_notes
                    WHERE note_id = n.id
                      AND recipient_email = ?
                )
              )";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$note_id, $user_id, $my_email]);
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo json_encode($images);
} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

get_note_labels.php
<?php
// api/get_note_labels.php
// SỬA WARN: Kiểm tra note thuộc về user hoặc được chia sẻ trước khi trả nhãn
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$note_id = intval($_GET['note_id'] ?? 0);
$user_id = $_SESSION['user_id'];

if ($note_id <= 0) {
    echo json_encode([]);
    exit;
}

try {
    $meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $meStmt->execute([$user_id]);
    $my_email = $meStmt->fetchColumn();

    // SỬA: Kiểm tra quyền truy cập vào note trước
    $accessCheck = $pdo->prepare(
        "SELECT id FROM notes WHERE id = ? AND user_id = ?
         UNION
         SELECT n.id FROM notes n
         JOIN shared_notes sn ON sn.note_id = n.id
         WHERE n.id = ? AND sn.recipient_email = ?"
    );
    $accessCheck->execute([$note_id, $user_id, $note_id, $my_email]);

    if (!$accessCheck->fetch()) {
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT l.* FROM labels l
         JOIN note_labels nl ON l.id = nl.label_id
         WHERE nl.note_id = ?"
    );
    $stmt->execute([$note_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (PDOException $e) {
    echo json_encode([]);
}

get_notes.php
<?php
session_start();
require_once '../database.php';
header('Content-Type: application/json');

$my_id = $_SESSION['user_id'] ?? 0;
if (!$my_id) {
    echo json_encode([]);
    exit;
}

$view = $_GET['view'] ?? 'all';

try {
    if ($view === 'shared') {
        $meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $meStmt->execute([$my_id]);
        $my_email = $meStmt->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT n.*, u.display_name AS owner_name, sn.permission
             FROM shared_notes sn
             JOIN notes n ON sn.note_id = n.id
             JOIN users u ON n.user_id = u.id
             WHERE sn.recipient_email = :my_email
               AND n.user_id != :my_id
               AND n.is_trashed = 0"
        );
        $stmt->execute(['my_email' => $my_email, 'my_id' => $my_id]);

    } elseif ($view === 'trash') {
        $stmt = $pdo->prepare(
            "SELECT *, 'owner' AS role FROM notes WHERE user_id = :my_id AND is_trashed = 1"
        );
        $stmt->execute(['my_id' => $my_id]);

    } else {
        $stmt = $pdo->prepare(
            "SELECT *, 'owner' AS role FROM notes WHERE user_id = :my_id AND is_trashed = 0"
        );
        $stmt->execute(['my_id' => $my_id]);
    }

    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (PDOException $e) {
    echo json_encode([]);
}

get_shares.php
<?php
// api/get_shares.php
// SỬA CRIT 3: Dùng auth_helper thay vì session_start() + check thủ công
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$note_id = intval($_GET['note_id'] ?? 0);

try {
    // Kiểm tra note thuộc về user hiện tại (chỉ chủ mới xem được danh sách share)
    $noteCheck = $pdo->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
    $noteCheck->execute([$note_id, $user_id]);
    if (!$noteCheck->fetch()) {
        echo json_encode([]);
        exit;
    }

    $stmt = $pdo->prepare(
        "SELECT
            sn.id              AS share_id,
            sn.recipient_email AS email,
            COALESCE(u.display_name, sn.recipient_email) AS display_name,
            sn.permission
         FROM shared_notes sn
         LEFT JOIN users u ON u.email = sn.recipient_email
         WHERE sn.note_id = ?"
    );
    $stmt->execute([$note_id]);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));

} catch (PDOException $e) {
    echo json_encode([]);
}

lock_note.php
<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$note_id      = intval($_POST['note_id']      ?? 0);
$action       = $_POST['action']              ?? 'lock'; // 'lock' | 'unlock' | 'change'
$password     = $_POST['password']            ?? '';     // mật khẩu mới (lock / change)
$old_password = $_POST['old_password']        ?? '';     // mật khẩu cũ (unlock / change)
$user_id      = $_SESSION['user_id'];

if ($note_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'ID không hợp lệ']);
    exit;
}

try {
    // Lấy password_hash hiện tại của note
    $stmt = $pdo->prepare("SELECT password_hash FROM notes WHERE id = ? AND user_id = ?");
    $stmt->execute([$note_id, $user_id]);
    $note = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$note) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy ghi chú!']);
        exit;
    }

    $current_hash = $note['password_hash'];

    // ── ĐẶT KHÓA MỚI ────────────────────────────────────────────────────────
    if ($action === 'lock') {
        if (strlen($password) < 4) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu phải có ít nhất 4 ký tự!']);
            exit;
        }
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE notes SET password_hash = ? WHERE id = ? AND user_id = ?")
            ->execute([$hash, $note_id, $user_id]);
        echo json_encode(['success' => true]);

    // ── GỠ KHÓA ─────────────────────────────────────────────────────────────
    } elseif ($action === 'unlock') {
        // Bắt buộc xác thực mật khẩu cũ
        if (empty($current_hash)) {
            echo json_encode(['success' => false, 'message' => 'Ghi chú này chưa được khóa!']);
            exit;
        }
        if (empty($old_password) || !password_verify($old_password, $current_hash)) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu không đúng!']);
            exit;
        }
        $pdo->prepare("UPDATE notes SET password_hash = NULL WHERE id = ? AND user_id = ?")
            ->execute([$note_id, $user_id]);
        echo json_encode(['success' => true]);

    // ── ĐỔI MẬT KHẨU ────────────────────────────────────────────────────────
    } elseif ($action === 'change') {
        if (empty($current_hash)) {
            echo json_encode(['success' => false, 'message' => 'Ghi chú này chưa được khóa!']);
            exit;
        }
        if (empty($old_password) || !password_verify($old_password, $current_hash)) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu cũ không đúng!']);
            exit;
        }
        if (strlen($password) < 4) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu mới phải có ít nhất 4 ký tự!']);
            exit;
        }
        $new_hash = password_hash($password, PASSWORD_DEFAULT);
        $pdo->prepare("UPDATE notes SET password_hash = ? WHERE id = ? AND user_id = ?")
            ->execute([$new_hash, $note_id, $user_id]);
        echo json_encode(['success' => true]);

    // ── XÁC THỰC MẬT KHẨU CŨ (dùng trước bước đổi mật khẩu) ──────────────────
    } elseif ($action === 'verify') {
        if (empty($current_hash)) {
            echo json_encode(['success' => false, 'message' => 'Ghi chú này chưa được khóa!']);
            exit;
        }
        if (empty($old_password) || !password_verify($old_password, $current_hash)) {
            echo json_encode(['success' => false, 'message' => 'Mật khẩu không đúng!']);
            exit;
        }
        echo json_encode(['success' => true]);

    } else {
        echo json_encode(['success' => false, 'message' => 'Action không hợp lệ!']);
    }

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
?>

manage_labels.php
<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

$user_id = $_SESSION['user_id'];
$action = $_REQUEST['action'] ?? 'list';

header('Content-Type: application/json');

try {
    if ($action == 'list') {
        $stmt = $pdo->prepare("SELECT * FROM labels WHERE user_id = ? ORDER BY name");
        $stmt->execute([$user_id]);
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
    } 
    elseif ($action == 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name) {
            $stmt = $pdo->prepare("INSERT INTO labels (user_id, name) VALUES (?, ?)");
            $stmt->execute([$user_id, $name]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Tên nhãn không được để trống']);
        }
    }
    elseif ($action == 'rename') {
        $id = intval($_POST['id'] ?? 0);
        $new_name = trim($_POST['name'] ?? '');

        if ($id <= 0 || empty($new_name)) {
            echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ']);
            exit;
        }

        $stmt = $pdo->prepare("UPDATE labels SET name = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$new_name, $id, $user_id]);

        echo json_encode(['success' => true, 'message' => 'Đổi tên nhãn thành công']);
    }
    elseif ($action == 'delete') {
        $id = intval($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM labels WHERE id = ? AND user_id = ?");
        $stmt->execute([$id, $user_id]);
        echo json_encode(['success' => true]);
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi database']);
}
?>

pin_note.php
<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

$user_id = $_SESSION['user_id'];
$id = $_POST['id'] ?? '';
$is_pinned = $_POST['is_pinned'] ?? 0;

if (empty($id)) {
    echo json_encode(['success' => false]);
    exit();
}

try {
    $pinned_at = $is_pinned ? date('Y-m-d H:i:s') : null;
    $stmt = $pdo->prepare("UPDATE notes SET is_pinned = ?, pinned_at = ? WHERE id = ? AND user_id = ?");
    $stmt->execute([$is_pinned, $pinned_at, $id, $user_id]);
    echo json_encode(['success' => true]);
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>

restore_note.php
<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

$id = $_POST['id'] ?? 0;
$stmt = $pdo->prepare("UPDATE notes SET is_trashed = 0 WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $_SESSION['user_id']]);

echo json_encode(['success' => true]);
?>

revoke_share.php
<?php
// api/revoke_share.php
// SỬA CRIT 3: Dùng auth_helper thay vì session_start() + check thủ công
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$user_id  = $_SESSION['user_id'];
$share_id = intval($_POST['share_id'] ?? 0);

try {
    // Chỉ chủ ghi chú mới được thu hồi quyền
    $stmt = $pdo->prepare(
        "DELETE sn FROM shared_notes sn
         JOIN notes n ON sn.note_id = n.id
         WHERE sn.id = ? AND n.user_id = ?"
    );
    $stmt->execute([$share_id, $user_id]);

    echo json_encode(['success' => true]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

save_note.php
<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$user_id = $_SESSION['user_id'];
$id      = intval($_POST['id'] ?? 0);
$title   = trim($_POST['title'] ?? '');
$content = $_POST['content'] ?? '';
$client_version = intval($_POST['version'] ?? 0);

try {
    if ($id <= 0) {
        // Tạo mới
        $stmt = $pdo->prepare("INSERT INTO notes (user_id, title, content, version) VALUES (?, ?, ?, 1)");
        $stmt->execute([$user_id, $title, $content]);
        $new_id = $pdo->lastInsertId();

        broadcastNoteUpdate($new_id, $title, $content, $_SESSION['display_name'] ?? 'Người dùng');
        echo json_encode(['success' => true, 'note_id' => $new_id, 'version' => 1]);
        exit;
    }

    // Kiểm tra quyền
    $meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $meStmt->execute([$user_id]);
    $my_email = $meStmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT n.version, n.user_id,
               (SELECT permission FROM shared_notes WHERE note_id = n.id AND recipient_email = ? LIMIT 1) AS permission
        FROM notes n WHERE n.id = ?
    ");
    $stmt->execute([$my_email, $id]);
    $note = $stmt->fetch();

    if (!$note || !($note['user_id'] == $user_id || $note['permission'] === 'edit')) {
        echo json_encode(['success' => false, 'error' => 'Không có quyền chỉnh sửa']);
        exit;
    }

    // === SOFT CONFLICT CHECK (Chỉ conflict khi chênh lệch lớn) ===
    if ($client_version > 0 && $client_version < $note['version'] - 3) {
        echo json_encode([
            'success' => false,
            'conflict' => true,
            'message' => 'Có xung đột phiên bản. Đang tải lại ghi chú...'
        ]);
        exit;
    }

    $new_version = $note['version'] + 1;

    $stmt = $pdo->prepare("UPDATE notes SET title = ?, content = ?, version = ?, updated_at = NOW() WHERE id = ?");
    $stmt->execute([$title, $content, $new_version, $id]);

    broadcastNoteUpdate($id, $title, $content, $_SESSION['display_name'] ?? 'Người dùng');

    echo json_encode([
        'success' => true,
        'note_id' => $id,
        'version' => $new_version
    ]);

} catch (Exception $e) {
    error_log('Save Note Error: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Lỗi hệ thống']);
}

function broadcastNoteUpdate($note_id, $title, $content, $user_name) {
    // WebSocket sẽ xử lý
}
?>

search.php
<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$user_id   = $_SESSION['user_id'];
$keyword   = $_GET['q'] ?? '';
$label_id  = $_GET['label_id'] ?? null;
$view_mode = $_GET['view'] ?? 'my_notes';

$params = [];
$searchTerm = "%$keyword%";

try {

    if ($view_mode === 'shared') {

        $meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
        $meStmt->execute([$user_id]);
        $my_email = $meStmt->fetchColumn();

        $sql = "
            SELECT 
                n.*,
                sn.permission,
                u.display_name AS owner_name,
                sn.shared_at,
                n.version
            FROM shared_notes sn
            JOIN notes n ON sn.note_id = n.id
            JOIN users u ON n.user_id = u.id
            WHERE sn.recipient_email = ? 
              AND n.user_id != ?
              AND n.is_trashed = 0
        ";

        $params[] = $my_email;
        $params[] = $user_id;

    } elseif ($view_mode === 'trash') {

        $sql = "
            SELECT n.*, 'owner' AS role, n.version 
            FROM notes n 
            WHERE n.user_id = ? AND n.is_trashed = 1
        ";
        $params[] = $user_id;

    } else {

        $sql = "
            SELECT n.*, 'owner' AS role, n.version 
            FROM notes n 
            WHERE n.user_id = ? AND n.is_trashed = 0
        ";
        $params[] = $user_id;

        if ($label_id && $label_id !== 'null') {
            $sql .= " AND n.id IN (SELECT note_id FROM note_labels WHERE label_id = ?)";
            $params[] = intval($label_id);
        }
    }

    $sql .= " AND (n.title LIKE ? OR n.content LIKE ?)";
    $params[] = $searchTerm;
    $params[] = $searchTerm;

    if ($view_mode === 'shared') {
        $sql .= " ORDER BY sn.shared_at DESC, n.updated_at DESC";
    } else {
        $sql .= " ORDER BY n.is_pinned DESC, n.pinned_at DESC, n.updated_at DESC";
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $notes = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Hide locked content
    foreach ($notes as &$note) {
        if (!empty($note['password_hash'])) {
            $note['is_locked'] = 1;
            $note['title'] = '🔒 Ghi chú bí mật';
            $note['content'] = 'Nhập mật khẩu để xem...';
        } else {
            $note['is_locked'] = 0;
        }
        unset($note['password_hash']);
    }

    echo json_encode($notes);

} catch (PDOException $e) {
    error_log($e->getMessage());
    echo json_encode([]);
}


set_note_label.php
<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

$note_id = $_POST['note_id'] ?? 0;
$label_id = $_POST['label_id'] ?? 0;
$action = $_POST['action'] ?? 'add'; // 'add' hoặc 'remove'

if ($action == 'add') {
    $stmt = $pdo->prepare("INSERT IGNORE INTO note_labels (note_id, label_id) VALUES (?, ?)");
    $stmt->execute([$note_id, $label_id]);
} else {
    $stmt = $pdo->prepare("DELETE FROM note_labels WHERE note_id = ? AND label_id = ?");
    $stmt->execute([$note_id, $label_id]);
}
echo json_encode(['success' => true]);

share_note.php
<?php
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$sender_id  = $_SESSION['user_id'];
$note_id    = intval($_POST['note_id'] ?? 0);
$share_with = trim($_POST['share_with'] ?? '');
$permission = $_POST['permission'] ?? 'read';

if ($note_id <= 0 || empty($share_with)) {
    echo json_encode(['success' => false, 'message' => 'Dữ liệu không hợp lệ!']);
    exit;
}

if (!in_array($permission, ['read', 'edit'])) {
    $permission = 'read';
}

try {
    // Kiểm tra ghi chú thuộc về owner
    $noteCheck = $pdo->prepare("SELECT id FROM notes WHERE id = ? AND user_id = ?");
    $noteCheck->execute([$note_id, $sender_id]);
    if (!$noteCheck->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Bạn không có quyền chia sẻ ghi chú này!']);
        exit;
    }

    $emails = array_unique(array_map('trim', explode(',', $share_with)));
    $successCount = 0;
    $messages = [];

    foreach ($emails as $shareWith) {
        if (empty($shareWith)) continue;

        // Tìm người nhận theo email hoặc display_name
        $stmt = $pdo->prepare("SELECT id, email, display_name FROM users 
                              WHERE email = ? OR display_name = ? LIMIT 1");
        $stmt->execute([$shareWith, $shareWith]);
        $receiver = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$receiver) {
            $messages[] = "Không tìm thấy: " . htmlspecialchars($shareWith);
            continue;
        }

        if ($receiver['id'] == $sender_id) {
            $messages[] = "Không thể chia sẻ cho chính mình";
            continue;
        }

        $recipient_email = $receiver['email'];

        // Kiểm tra đã chia sẻ chưa
        $check = $pdo->prepare("SELECT id FROM shared_notes WHERE note_id = ? AND recipient_email = ?");
        $check->execute([$note_id, $recipient_email]);

        if ($check->fetch()) {
            // Cập nhật quyền
            $update = $pdo->prepare("UPDATE shared_notes SET permission = ? WHERE note_id = ? AND recipient_email = ?");
            $update->execute([$permission, $note_id, $recipient_email]);
            $messages[] = "Đã cập nhật quyền cho " . htmlspecialchars($receiver['display_name']);
        } else {
            // Thêm mới
            $insert = $pdo->prepare("INSERT INTO shared_notes (note_id, owner_id, recipient_email, permission) 
                                    VALUES (?, ?, ?, ?)");
            $insert->execute([$note_id, $sender_id, $recipient_email, $permission]);
            $messages[] = "Đã chia sẻ thành công cho " . htmlspecialchars($receiver['display_name']);
        }
        $successCount++;
    }

    $finalMessage = $successCount > 0 
        ? "Đã chia sẻ cho $successCount người.\n" . implode("\n", $messages)
        : "Không chia sẻ được cho ai.";

    echo json_encode([
        'success' => $successCount > 0,
        'message' => $finalMessage
    ]);

} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi database: ' . $e->getMessage()]);
}
?>

update_profile.php
<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();

header('Content-Type: application/json');

require_once __DIR__ . '/../database.php';

// =========================
// CHECK LOGIN
// =========================
$user_id = $_SESSION['user_id'] ?? 0;

if (!$user_id) {
    echo json_encode([
        'success' => false,
        'message' => 'Chưa đăng nhập'
    ]);
    exit;
}

// =========================
// DEFAULT AVATAR
// =========================
$default_avatar = 'uploads/avatars/default-avatar.png';

// =========================
// INPUT
// =========================
$font_size   = $_POST['font_size'] ?? '16px';
$theme_color = $_POST['theme_color'] ?? 'light';
$note_color  = $_POST['note_color'] ?? '#ffffff';

// =========================
// VALIDATE
// =========================
$allowed_fonts  = ['14px', '16px', '18px'];
$allowed_themes = ['light', 'dark'];

if (!in_array($font_size, $allowed_fonts)) {
    $font_size = '16px';
}

if (!in_array($theme_color, $allowed_themes)) {
    $theme_color = 'light';
}

if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $note_color)) {
    $note_color = '#ffffff';
}

try {

    // =========================
    // CURRENT AVATAR
    // =========================
    $avatar_path = !empty($_SESSION['avatar'])
        ? $_SESSION['avatar']
        : $default_avatar;

    // =========================
    // UPLOAD NEW AVATAR
    // =========================
    if (
        isset($_FILES['avatar']) &&
        $_FILES['avatar']['error'] === UPLOAD_ERR_OK
    ) {

        $upload_dir = __DIR__ . '/../uploads/avatars/';

        // Tạo folder nếu chưa có
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }

        // Extension
        $ext = strtolower(
            pathinfo($_FILES['avatar']['name'], PATHINFO_EXTENSION)
        );

        // Cho phép
        $allowed_ext = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($ext, $allowed_ext)) {
            throw new Exception('File ảnh không hợp lệ');
        }

        // Tên file mới
        $new_name = 'avatar_' . $user_id . '_' . time() . '.' . $ext;

        $target = $upload_dir . $new_name;

        // Upload
        if (!move_uploaded_file($_FILES['avatar']['tmp_name'], $target)) {
            throw new Exception('Upload avatar thất bại');
        }

        // =========================
        // DELETE OLD AVATAR
        // =========================
        if (
            !empty($_SESSION['avatar']) &&
            $_SESSION['avatar'] !== $default_avatar
        ) {

            $old_file = __DIR__ . '/../' . $_SESSION['avatar'];

            if (file_exists($old_file)) {
                unlink($old_file);
            }
        }

        // Avatar mới
        $avatar_path = 'uploads/avatars/' . $new_name;
    }

    // =========================
    // UPDATE DB
    // =========================
    $stmt = $pdo->prepare("
        UPDATE users
        SET
            font_size = ?,
            theme_color = ?,
            note_color = ?,
            avatar = ?
        WHERE id = ?
    ");

    $stmt->execute([
        $font_size,
        $theme_color,
        $note_color,
        $avatar_path,
        $user_id
    ]);

    // =========================
    // UPDATE SESSION
    // =========================
    $_SESSION['font_size']   = $font_size;
    $_SESSION['theme_color'] = $theme_color;
    $_SESSION['note_color']  = $note_color;
    $_SESSION['avatar']      = $avatar_path;

    // =========================
    // SUCCESS
    // =========================
    echo json_encode([
        'success' => true,
        'message' => 'Cập nhật thành công',
        'avatar' => $avatar_path
    ]);

} catch (Exception $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

upload_image.php
<?php
// api/upload_image.php
// SỬA WARN: Dùng finfo_file() kiểm tra MIME server-side thay vì tin $_FILES['type']
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_FILES['image']) || !isset($_POST['note_id'])) {
    echo json_encode(['success' => false, 'message' => 'Yêu cầu không hợp lệ.']);
    exit;
}

$note_id = intval($_POST['note_id']);
$user_id = $_SESSION['user_id'];
$file    = $_FILES['image'];

// Kiểm tra lỗi upload
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'Lỗi upload file (code: ' . $file['error'] . ').']);
    exit;
}

// Kiểm tra note_id có thuộc về user này không (hoặc được share với quyền edit)
$meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
$meStmt->execute([$user_id]);
$my_email = $meStmt->fetchColumn();

$noteCheck = $pdo->prepare(
    "SELECT id FROM notes WHERE id = ? AND user_id = ?
     UNION
     SELECT n.id FROM notes n
     JOIN shared_notes sn ON sn.note_id = n.id
     WHERE n.id = ? AND sn.recipient_email = ? AND sn.permission = 'edit'"
);
$noteCheck->execute([$note_id, $user_id, $note_id, $my_email]);
if (!$noteCheck->fetch()) {
    echo json_encode(['success' => false, 'message' => 'Bạn không có quyền thêm ảnh vào ghi chú này.']);
    exit;
}

// Giới hạn kích thước file: 5MB
$max_size = 5 * 1024 * 1024;
if ($file['size'] > $max_size) {
    echo json_encode(['success' => false, 'message' => 'File quá lớn. Tối đa 5MB.']);
    exit;
}

// SỬA: Kiểm tra MIME type phía server bằng finfo (không tin client)
$finfo     = finfo_open(FILEINFO_MIME_TYPE);
$mime_type = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

$allowed_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mime_type, $allowed_mimes)) {
    echo json_encode(['success' => false, 'message' => 'Chỉ cho phép file ảnh (jpg, png, gif, webp).']);
    exit;
}

// Map MIME -> extension an toàn
$ext_map   = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
$extension = $ext_map[$mime_type];

// Tạo tên file duy nhất
$new_filename = uniqid('img_', true) . '.' . $extension;
$upload_dir   = '../uploads/';
$upload_path  = $upload_dir . $new_filename;
$db_path      = 'uploads/' . $new_filename;

// Tạo thư mục nếu chưa có
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

if (move_uploaded_file($file['tmp_name'], $upload_path)) {
    $stmt = $pdo->prepare("INSERT INTO note_images (note_id, file_path) VALUES (?, ?)");
    $stmt->execute([$note_id, $db_path]);

    echo json_encode([
        'success'  => true,
        'file_path' => $db_path,
        'image_id' => $pdo->lastInsertId()
    ]);
} else {
    echo json_encode(['success' => false, 'message' => 'Không thể lưu file vào thư mục uploads.']);
}

verify_note.php
<?php
// api/verify_note.php
// SỬA CRIT 3: Dùng auth_helper thay vì session_start() + check thủ công
require_once 'auth_helper.php';
require_once '../database.php';
check_login();

header('Content-Type: application/json');

$user_id  = $_SESSION['user_id'];
$note_id  = intval($_POST['note_id']  ?? 0);
$password = $_POST['password'] ?? '';

try {
    $meStmt = $pdo->prepare("SELECT email FROM users WHERE id = ?");
    $meStmt->execute([$user_id]);
    $my_email = $meStmt->fetchColumn();

    // Tìm ghi chú: chủ sở hữu HOẶC được share qua email
    $stmt = $pdo->prepare("
        SELECT n.title, n.content, n.password_hash, n.color, n.user_id,
               (SELECT permission FROM shared_notes
                WHERE note_id = n.id AND recipient_email = ? LIMIT 1) AS permission
        FROM notes n
        WHERE n.id = ?
          AND (
            n.user_id = ?
            OR EXISTS (SELECT 1 FROM shared_notes WHERE note_id = n.id AND recipient_email = ?)
          )
    ");
    $stmt->execute([$my_email, $note_id, $user_id, $my_email]);
    $note = $stmt->fetch();

    if (!$note) {
        echo json_encode(['success' => false, 'message' => 'Không tìm thấy ghi chú!']);
        exit;
    }

    if (!password_verify($password, $note['password_hash'])) {
        echo json_encode(['success' => false, 'message' => 'Mật khẩu không đúng!']);
        exit;
    }

    $perm = ($note['user_id'] == $user_id) ? 'owner' : $note['permission'];
    echo json_encode([
        'success'    => true,
        'title'      => $note['title'],
        'content'    => $note['content'],
        'color'      => $note['color'],
        'permission' => $perm
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Lỗi: ' . $e->getMessage()]);
}

App
NoteWebSocket.php
<?php
namespace App;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;

class NoteWebSocket implements MessageComponentInterface {
    protected $clients;
    protected $noteSubscriptions = []; // note_id => [resourceId => ['conn' => conn, 'user_name' => str]]

    public function __construct() {
        $this->clients = new \SplObjectStorage;
    }

    public function onOpen(ConnectionInterface $conn) {
        $this->clients->attach($conn);
        $conn->user_id   = 0;
        $conn->user_name = 'Unknown';
        echo "[WS] New connection: {$conn->resourceId}\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
        $data = json_decode($msg, true);
        if (!$data || !isset($data['type'])) return;

        switch ($data['type']) {

            case 'auth':
                $uid  = intval($data['user_id'] ?? 0);
                $name = $data['user_name'] ?? 'User';
                if ($uid > 0) {
                    $from->user_id   = $uid;
                    $from->user_name = $name;
                    $from->send(json_encode(['type' => 'auth_success']));
                    echo "[WS] User $uid ($name) authenticated\n";
                }
                break;

            case 'join_note':
                $note_id = intval($data['note_id'] ?? 0);
                if ($note_id && $from->user_id > 0) {
                    // Thêm vào phòng
                    $this->noteSubscriptions[$note_id][$from->resourceId] = [
                        'conn'      => $from,
                        'user_name' => $from->user_name
                    ];
                    echo "[WS] User {$from->user_id} joined note $note_id\n";

                    // Broadcast danh sách người đang xem
                    $this->broadcastPresence($note_id);
                }
                break;

            case 'leave_note':
                $note_id = intval($data['note_id'] ?? 0);
                $this->removeFromNote($from, $note_id);
                break;

            case 'update':
                $note_id = intval($data['note_id'] ?? 0);
                if ($note_id && isset($this->noteSubscriptions[$note_id])) {

                    $broadcastData = [
                        'type'       => 'update',
                        'note_id'    => $note_id,
                        'user_name'  => $from->user_name,
                        'title'      => $data['title'] ?? null,
                        'content'    => $data['content'] ?? null,
                        'sender_id'  => $from->resourceId,
                        'timestamp'  => time()
                    ];

                    // Broadcast cho tất cả người KHÁC trong note (không echo lại người gửi)
                    foreach ($this->noteSubscriptions[$note_id] as $resourceId => $info) {
                        if ($info['conn'] !== $from) {
                            $info['conn']->send(json_encode($broadcastData));
                        }
                    }
                }
                break;
        }
    }

    /**
     * Broadcast danh sách người đang xem ghi chú
     */
    private function broadcastPresence(int $note_id) {
        if (!isset($this->noteSubscriptions[$note_id])) return;

        $users = array_values(array_map(
            fn($info) => $info['user_name'],
            $this->noteSubscriptions[$note_id]
        ));

        $payload = json_encode([
            'type'     => 'presence',
            'note_id'  => $note_id,
            'users'    => $users
        ]);

        foreach ($this->noteSubscriptions[$note_id] as $info) {
            $info['conn']->send($payload);
        }
    }

    private function removeFromNote(ConnectionInterface $conn, int $note_id) {
        if ($note_id && isset($this->noteSubscriptions[$note_id])) {
            unset($this->noteSubscriptions[$note_id][$conn->resourceId]);

            if (empty($this->noteSubscriptions[$note_id])) {
                unset($this->noteSubscriptions[$note_id]);
            } else {
                $this->broadcastPresence($note_id);
            }
        }
    }

    public function onClose(ConnectionInterface $conn) {
        // Xóa khỏi tất cả các note đang tham gia
        foreach (array_keys($this->noteSubscriptions) as $note_id) {
            $this->removeFromNote($conn, (int)$note_id);
        }
        $this->clients->detach($conn);
        echo "[WS] Connection closed: {$conn->resourceId}\n";
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "[WS] Error: {$e->getMessage()}\n";
        $conn->close();
    }
}
assets
css
style.css
/* =========================================================
   LIQUID GLASS PREMIUM UI
   ========================================================= */

:root{

    /* ===== COLORS ===== */

    --primary:#6ea8ff;
    --primary-2:#8b5cf6;

    --light-bg:#edf3ff;
    --light-bg-2:#dbe8ff;

    --dark-bg:#0b1220;
    --dark-bg-2:#111827;

    --text-light:#111827;
    --text-dark:#f8fafc;

    /* ===== GLASS ===== */

    --glass-bg:
        linear-gradient(
            135deg,
            rgba(255,255,255,0.38),
            rgba(255,255,255,0.12)
        );

    --glass-dark:
        linear-gradient(
            135deg,
            rgba(22,28,45,0.72),
            rgba(17,24,39,0.38)
        );

    --glass-border:
        rgba(255,255,255,0.30);

    --glass-border-dark:
        rgba(255,255,255,0.08);

    --glass-shadow:
        0 10px 40px rgba(15,23,42,0.10);

    --glass-shadow-dark:
        0 10px 40px rgba(0,0,0,0.35);

    --blur: blur(26px);

    /* ===== RADIUS ===== */

    --radius-xl:32px;
    --radius-lg:26px;
    --radius-md:20px;
}

/* =========================================================
   BODY
   ========================================================= */

body{

    font-family:'Inter',sans-serif;

    min-height:100vh;

    overflow-x:hidden;

    color:var(--text-light);

    background:
        radial-gradient(circle at top left,
            rgba(123,92,255,0.22),
            transparent 30%
        ),

        radial-gradient(circle at top right,
            rgba(110,168,255,0.18),
            transparent 28%
        ),

        radial-gradient(circle at bottom right,
            rgba(255,255,255,0.55),
            transparent 40%
        ),

        linear-gradient(
            145deg,
            #dce8ff,
            #edf4ff,
            #d8e4ff
        );

    background-attachment:fixed;
}

/* floating glow */

body::before{
    content:'';

    position:fixed;

    width:420px;
    height:420px;

    top:-120px;
    right:-120px;

    border-radius:50%;

    background:
        radial-gradient(
            circle,
            rgba(110,168,255,0.30),
            transparent 70%
        );

    filter:blur(50px);

    z-index:-1;

    animation:floatGlow 10s ease-in-out infinite;
}

body::after{
    content:'';

    position:fixed;

    width:380px;
    height:380px;

    bottom:-140px;
    left:-100px;

    border-radius:50%;

    background:
        radial-gradient(
            circle,
            rgba(139,92,246,0.24),
            transparent 70%
        );

    filter:blur(60px);

    z-index:-1;

    animation:floatGlow2 12s ease-in-out infinite;
}

@keyframes floatGlow{

    0%{
        transform:translateY(0px) translateX(0px);
    }

    50%{
        transform:translateY(25px) translateX(-20px);
    }

    100%{
        transform:translateY(0px) translateX(0px);
    }
}

@keyframes floatGlow2{

    0%{
        transform:translateY(0px);
    }

    50%{
        transform:translateY(-30px);
    }

    100%{
        transform:translateY(0px);
    }
}

/* =========================================================
   GLASS
   ========================================================= */

.glass{

    background:var(--glass-bg);

    backdrop-filter:var(--blur);
    -webkit-backdrop-filter:var(--blur);

    border:1px solid var(--glass-border);

    box-shadow:var(--glass-shadow);

    position:relative;

    overflow:hidden;
}

.glass::before{

    content:'';

    position:absolute;

    inset:0;

    background:
        linear-gradient(
            135deg,
            rgba(255,255,255,0.45),
            transparent 35%
        );

    pointer-events:none;
}

/* =========================================================
   NAVBAR
   ========================================================= */

.navbar{

    margin:18px;

    border-radius:34px !important;

    background:
        linear-gradient(
            135deg,
            rgba(255,255,255,0.34),
            rgba(255,255,255,0.12)
        ) !important;

    backdrop-filter:blur(28px);
    -webkit-backdrop-filter:blur(28px);

    border:1px solid rgba(255,255,255,0.28);

    box-shadow:
        0 10px 35px rgba(15,23,42,0.10);

    overflow:hidden;
}

/* shine */

.navbar::before{

    content:'';

    position:absolute;

    inset:0;

    background:
        linear-gradient(
            135deg,
            rgba(255,255,255,0.40),
            transparent 30%
        );

    pointer-events:none;
}

/* =========================================================
   BRAND
   ========================================================= */

.navbar-brand{

    font-weight:700 !important;

    font-size:1.3rem;

    letter-spacing:.4px;

    color:#0f172a !important;
}

/* user name */

.navbar .small{

    color:#0f172a !important;

    font-weight:600;

    opacity:.92;
}

/* =========================================================
   SEARCH
   ========================================================= */

#searchInput{

    border:none !important;

    border-radius:22px !important;

    background:
        rgba(255,255,255,0.50);

    backdrop-filter:blur(18px);

    padding:14px 20px;

    box-shadow:
        inset 0 1px 2px rgba(255,255,255,0.7),
        0 5px 18px rgba(15,23,42,0.06);

    transition:all .28s ease;
}

#searchInput:focus{

    transform:translateY(-1px);

    box-shadow:
        0 10px 30px rgba(110,168,255,0.18);

    background:rgba(255,255,255,0.72);
}

/* =========================================================
   TOOLBAR
   ========================================================= */

.bg-body-tertiary{

    background:
        linear-gradient(
            135deg,
            rgba(255,255,255,0.30),
            rgba(255,255,255,0.14)
        ) !important;

    border:1px solid rgba(255,255,255,0.24) !important;

    backdrop-filter:blur(24px);

    border-radius:28px !important;

    box-shadow:
        0 10px 35px rgba(15,23,42,0.08);
}

/* =========================================================
   NOTES GRID
   ========================================================= */

.note-grid-view{

    display:grid;

    grid-template-columns:
        repeat(auto-fill,minmax(290px,1fr));

    gap:28px;
}

.note-list-view{

    display:flex;

    flex-direction:column;

    gap:20px;
}

/* =========================================================
   NOTE CARD
   ========================================================= */

.note-card{

    position:relative;

    border-radius:32px !important;

    overflow:hidden;

    border:
        1px solid rgba(255,255,255,0.30) !important;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,0.22),
            rgba(255,255,255,0.06)
        );

    backdrop-filter:blur(26px);
    -webkit-backdrop-filter:blur(26px);

    box-shadow:
        0 14px 35px rgba(15,23,42,0.08);

    transition:
        transform .45s cubic-bezier(.2,.8,.2,1),
        box-shadow .4s ease,
        border .3s ease;

    cursor:pointer;

    isolation:isolate;

    animation:cardFade .55s ease;
}

/* glass border glow */

.note-card::before{

    content:'';

    position:absolute;

    inset:0;

    padding:1px;

    border-radius:inherit;

    background:
        linear-gradient(
            135deg,
            rgba(255,255,255,0.65),
            rgba(255,255,255,0.08),
            rgba(255,255,255,0.28)
        );

    -webkit-mask:
        linear-gradient(#fff 0 0) content-box,
        linear-gradient(#fff 0 0);

    -webkit-mask-composite:xor;

    mask-composite:exclude;

    pointer-events:none;
}

/* shine */

.note-card::after{

    content:'';

    position:absolute;

    width:160%;

    height:100%;

    top:0;
    left:-130%;

    background:
        linear-gradient(
            115deg,
            transparent,
            rgba(255,255,255,0.30),
            transparent
        );

    transform:skewX(-20deg);

    transition:.8s;
}

/* hover */

.note-card:hover{

    transform:
        translateY(-10px)
        scale(1.02);

    box-shadow:
        0 22px 55px rgba(15,23,42,0.16),
        0 0 30px rgba(110,168,255,0.12);

    border:
        1px solid rgba(255,255,255,0.46) !important;
}

.note-card:hover::after{

    left:120%;
}

/* =========================================================
   CARD BODY
   ========================================================= */

.card-body{

    padding:24px !important;
}

.card-title{

    font-weight:700;

    font-size:1.15rem;

    color:#111827;
}

.card-text{

    line-height:1.7;

    color:#374151 !important;

    opacity:.88;
}

/* =========================================================
   BUTTONS
   ========================================================= */

.btn{

    border:none !important;

    border-radius:18px !important;

    padding:10px 18px;

    transition:all .25s ease;

    backdrop-filter:blur(10px);
}

.btn:hover{

    transform:translateY(-2px);
}

.btn-primary{

    background:
        linear-gradient(
            135deg,
            #6ea8ff,
            #8b5cf6
        ) !important;

    box-shadow:
        0 12px 28px rgba(110,168,255,0.30);
}

/* =========================================================
   FLOATING BUTTON
   ========================================================= */

.floating-create{

    position:fixed;

    right:30px;
    bottom:30px;

    width:72px;
    height:72px;

    border:none;

    border-radius:50%;

    z-index:999;

    color:white;

    font-size:30px;

    background:
        linear-gradient(
            135deg,
            #6ea8ff,
            #8b5cf6
        );

    box-shadow:
        0 20px 45px rgba(110,168,255,0.35);

    transition:
        transform .35s ease,
        box-shadow .35s ease;

    overflow:hidden;
}

.floating-create::before{

    content:'';

    position:absolute;

    inset:0;

    background:
        linear-gradient(
            135deg,
            rgba(255,255,255,0.45),
            transparent 40%
        );
}

.floating-create:hover{

    transform:
        scale(1.08)
        rotate(90deg);

    box-shadow:
        0 28px 60px rgba(110,168,255,0.42);
}

/* =========================================================
   MODAL
   ========================================================= */

.modal-content{

    border:none !important;

    border-radius:36px !important;

    background:
        linear-gradient(
            145deg,
            rgba(255,255,255,0.34),
            rgba(255,255,255,0.14)
        );

    backdrop-filter:blur(34px);

    border:
        1px solid rgba(255,255,255,0.28);

    box-shadow:
        0 25px 80px rgba(15,23,42,0.20);

    overflow:hidden;
}

.modal-content::before{

    content:'';

    position:absolute;

    inset:0;

    background:
        linear-gradient(
            135deg,
            rgba(255,255,255,0.38),
            transparent 38%
        );

    pointer-events:none;
}

.modal.fade .modal-dialog{

    transform:
        scale(.92)
        translateY(20px);

    transition:all .25s ease;
}

.modal.show .modal-dialog{

    transform:
        scale(1)
        translateY(0);
}

/* =========================================================
   INPUTS
   ========================================================= */

.form-control,
.form-select{

    border:none !important;

    border-radius:18px !important;

    background:
        rgba(255,255,255,0.45);

    backdrop-filter:blur(18px);

    box-shadow:
        inset 0 1px 1px rgba(255,255,255,0.6);

    color:#111827 !important;
}

.form-control:focus,
.form-select:focus{

    background:
        rgba(255,255,255,0.72);

    box-shadow:
        0 0 0 4px rgba(110,168,255,0.15) !important;
}

/* =========================================================
   AVATAR
   ========================================================= */

.nav-avatar{

    width:44px;
    height:44px;

    border-radius:50%;

    object-fit:cover;

    border:
        2px solid rgba(255,255,255,0.70);

    box-shadow:
        0 6px 16px rgba(15,23,42,0.12);

    transition:all .35s ease;
}

.nav-avatar:hover{

    transform:
        scale(1.12)
        rotate(5deg);

    box-shadow:
        0 10px 28px rgba(110,168,255,0.20);
}

/* =========================================================
   COLOR BUTTON
   ========================================================= */

.color-btn{

    width:28px;
    height:28px;

    border-radius:50%;

    border:2px solid rgba(255,255,255,0.70);

    cursor:pointer;

    transition:.25s;
}

.color-btn:hover{

    transform:
        scale(1.18)
        rotate(10deg);
}

/* =========================================================
   BADGE
   ========================================================= */

.badge{

    border-radius:999px;

    padding:8px 12px;

    font-weight:600;
}

/* =========================================================
   ANIMATION
   ========================================================= */

@keyframes cardFade{

    from{

        opacity:0;

        transform:
            translateY(20px)
            scale(.96);
    }

    to{

        opacity:1;

        transform:
            translateY(0)
            scale(1);
    }
}

/* =========================================================
   DARK MODE
   ========================================================= */

[data-bs-theme="dark"] body{

    color:var(--text-dark);

    background:
        radial-gradient(
            circle at top left,
            rgba(110,168,255,0.12),
            transparent 30%
        ),

        radial-gradient(
            circle at bottom right,
            rgba(139,92,246,0.12),
            transparent 30%
        ),

        linear-gradient(
            145deg,
            #07111f,
            #0b1220,
            #101827
        );
}

/* navbar */

[data-bs-theme="dark"] .navbar{

    background:var(--glass-dark) !important;

    border:
        1px solid var(--glass-border-dark);

    box-shadow:var(--glass-shadow-dark);
}

[data-bs-theme="dark"] .navbar-brand,
[data-bs-theme="dark"] .navbar .small{

    color:#f8fafc !important;
}

/* glass */

[data-bs-theme="dark"] .glass,
[data-bs-theme="dark"] .bg-body-tertiary,
[data-bs-theme="dark"] .modal-content,
[data-bs-theme="dark"] .note-card{

    background:var(--glass-dark) !important;

    border:
        1px solid rgba(255,255,255,0.08) !important;

    box-shadow:var(--glass-shadow-dark);
}

/* text */

[data-bs-theme="dark"] .card-title{

    color:#f8fafc;
}

[data-bs-theme="dark"] .card-text{

    color:#cbd5e1 !important;
}

/* search */

[data-bs-theme="dark"] #searchInput,
[data-bs-theme="dark"] .form-control,
[data-bs-theme="dark"] .form-select{

    background:
        rgba(15,23,42,0.55);

    color:#f8fafc !important;
}

/* modal */

[data-bs-theme="dark"] .modal-content{

    background:
        linear-gradient(
            145deg,
            rgba(17,24,39,0.72),
            rgba(15,23,42,0.44)
        ) !important;
}
/* =========================================================
   VIEW SWITCH ANIMATION
   ========================================================= */

.note-grid-view,
.note-list-view{

    animation:viewFade .35s ease;
}

.note-grid-view .note-card{

    animation:
        cardPop .45s ease;
}

.note-list-view .note-card{

    animation:
        listSlide .4s ease;
}

/* grid animation */

@keyframes cardPop{

    from{
        opacity:0;

        transform:
            scale(.92)
            translateY(20px);

        filter:blur(8px);
    }

    to{
        opacity:1;

        transform:
            scale(1)
            translateY(0);

        filter:blur(0);
    }
}

/* list animation */

@keyframes listSlide{

    from{
        opacity:0;

        transform:
            translateX(-30px);

        filter:blur(6px);
    }

    to{
        opacity:1;

        transform:
            translateX(0);

        filter:blur(0);
    }
}

/* container fade */

@keyframes viewFade{

    from{
        opacity:.4;
    }

    to{
        opacity:1;
    }
}
app.js
// ====================== BIẾN TOÀN CỤC ======================
// currentUserId và currentUserName được inject từ index.php qua window.APP_CONFIG
const noteModal = new bootstrap.Modal(document.getElementById('noteModal'));
let typingTimer, searchTimer;
let currentLabelId = null;
let currentViewMode = 'my_notes';
let currentPermission = 'owner';
let isLockedState = false;
let currentNoteId = null;
let passwordModalInstance = null;
let tempOpenData = null;
let autoRefreshInterval = null;

// Được inject bởi index.php:
// window.APP_CONFIG = { userId, userName, userTheme }
const currentUserId   = window.APP_CONFIG?.userId   ?? 0;
const currentUserName = window.APP_CONFIG?.userName  ?? 'User';

document.addEventListener('DOMContentLoaded', () => {
    passwordModalInstance = new bootstrap.Modal(document.getElementById('passwordModal'));
    setViewMode('my_notes');
    initIndexedDB();
    window.addEventListener('online', syncOfflineNotes);

    // Preview live khi user đổi dropdown theme
    const themeSelect = document.getElementById('settingTheme');
    if (themeSelect) {
        themeSelect.addEventListener('change', function () {
            applyTheme(this.value);
        });
    }

    // Preview live khi user đổi font size
    const fontSelect = document.getElementById('settingFontSize');
    if (fontSelect) {
        fontSelect.addEventListener('change', function () {
            document.body.style.fontSize = this.value;
        });
    }
});

// ====================== VIEW MODE & SEARCH ======================
function setViewMode(mode) {
    const container = document.getElementById('notesContainer');
    container.style.transition = 'all .25s ease';
    container.style.opacity = '0';
    container.style.transform = 'translateY(10px) scale(.98)';

    setTimeout(() => {
        currentViewMode = mode;
        currentLabelId = null;

        document.getElementById('btnViewShared').style.display  = mode === 'shared'   ? 'none' : 'block';
        document.getElementById('btnViewTrash').style.display   = mode === 'trash'    ? 'none' : 'block';
        document.getElementById('btnViewMyNotes').style.display = mode === 'my_notes' ? 'none' : 'block';

        const viewTitle     = document.getElementById('viewTitle');
        const btnCreate     = document.getElementById('btnCreateNote');
        const addLabelGroup = document.getElementById('addLabelGroup');

        if (mode === 'my_notes') {
            viewTitle.style.display = 'none';
            btnCreate.style.display = 'block';
            addLabelGroup.style.display = 'flex';
        } else if (mode === 'trash') {
            viewTitle.innerHTML = '🗑️ THÙNG RÁC';
            viewTitle.style.display = 'block';
            viewTitle.className = 'text-danger fw-bold m-0 align-self-center';
            btnCreate.style.display = 'none';
            addLabelGroup.style.display = 'none';
        } else if (mode === 'shared') {
            viewTitle.innerHTML = '🤝 ĐƯỢC CHIA SẺ VỚI TÔI';
            viewTitle.style.display = 'block';
            viewTitle.className = 'text-info fw-bold m-0 align-self-center';
            btnCreate.style.display = 'none';
            addLabelGroup.style.display = 'none';
        }

        loadFilterLabels(() => {
            liveSearch();
            setTimeout(() => {
                container.style.opacity = '1';
                container.style.transform = 'translateY(0) scale(1)';
            }, 100);
        });

        startAutoRefresh();
    }, 180);
}

function liveSearch() {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => {
        let url = `api/search.php?q=${encodeURIComponent(document.getElementById('searchInput').value)}&view=${currentViewMode}`;
        if (currentLabelId && currentViewMode === 'my_notes') url += `&label_id=${currentLabelId}`;
        fetch(url).then(res => res.json()).then(renderNotes).catch(() => renderNotes([]));
    }, 300);
}

// ====================== RENDER NOTES ======================
function renderNotes(notes) {
    const container = document.getElementById('notesContainer');
    if (!notes || notes.length === 0) {
        const msgs = {
            trash:    'Thùng rác trống.',
            shared:   'Chưa có ghi chú nào được chia sẻ.',
            my_notes: 'Chưa có ghi chú nào.'
        };
        container.innerHTML = `<div class="text-center w-100 p-5 text-muted border rounded">${msgs[currentViewMode] || msgs.my_notes}</div>`;
        return;
    }

    container.innerHTML = '';
    notes.forEach(n => {
        const pinClass   = n.is_pinned == 1 ? 'bi-pin-fill text-danger' : 'bi-pin text-muted';
        const bgColor    = n.color ? `background-color:${n.color} !important;` : '';
        const ownerName  = n.owner_name || '';
        const permission = n.permission || 'owner';

        let icons = '';
        if (n.is_locked == 1) icons += '<i class="bi bi-lock-fill text-warning me-1" title="Đã khóa"></i>';
        if (ownerName)        icons += '<i class="bi bi-people-fill text-info me-1" title="Được chia sẻ"></i>';
        if (n.is_pinned == 1) icons += '<i class="bi bi-pin-fill text-danger me-1" title="Đã ghim"></i>';

        const shareInfo = ownerName ? `
            <div class="position-absolute bottom-0 start-0 end-0 px-3 pb-2 d-flex justify-content-between align-items-center">
                <small class="text-muted"><i class="bi bi-person"></i> ${escapeHtml(ownerName)}</small>
                ${permission === 'edit'
                    ? `<span class="badge bg-success ms-1">✏️ Edit</span>`
                    : `<span class="badge bg-secondary ms-1">👁️ View</span>`}
            </div>` : '';

        const card = document.createElement('div');
        card.className = 'card note-card';
        card.style.cssText = bgColor;

        const body = document.createElement('div');
        body.className = 'card-body position-relative pb-4';
        body.dataset.id         = n.id;
        body.dataset.title      = n.title || '';
        body.dataset.content    = n.content || '';
        body.dataset.isLocked   = n.is_locked || 0;
        body.dataset.color      = n.color || '';
        body.dataset.permission = permission;
        body.dataset.ownerName  = ownerName;

        body.addEventListener('click', () => handleNoteOpen(
            parseInt(body.dataset.id), body.dataset.title, body.dataset.content,
            parseInt(body.dataset.isLocked), body.dataset.color,
            body.dataset.permission, body.dataset.ownerName
        ));

        body.innerHTML = `
            ${currentViewMode === 'my_notes'
                ? `<button class="btn btn-sm position-absolute top-0 end-0 m-2 border-0" onclick="event.stopPropagation(); togglePin(${n.id}, ${n.is_pinned == 1 ? 0 : 1})"><i class="bi ${pinClass} fs-5"></i></button>`
                : ''}
            <h5 class="card-title text-truncate d-flex align-items-center gap-1">${icons} ${escapeHtml(n.title) || 'Không tiêu đề'}</h5>
            <p class="card-text text-muted text-truncate" style="white-space:pre-wrap; display:-webkit-box; -webkit-line-clamp:3; -webkit-box-orient:vertical;">${escapeHtml(n.content) || 'Không có nội dung...'}</p>
            ${shareInfo}
        `;

        card.appendChild(body);
        container.appendChild(card);
    });
}

// ====================== MỞ GHI CHÚ & PASSWORD ======================
function handleNoteOpen(id, title, content, isLocked, color, permission, ownerName) {
    currentNoteId = id;
    currentPermission = permission;

    if (isLocked && currentViewMode !== 'trash') {
        document.getElementById('passwordModalTitle').textContent = '🔒 Ghi chú đã bị khóa';
        document.getElementById('notePasswordInput').value = '';
        document.getElementById('passwordError').style.display = 'none';
        passwordModalInstance.show();
        setTimeout(() => document.getElementById('notePasswordInput').focus(), 500);
        window.tempOpenData = { id, title, content, color, permission, ownerName };
    } else {
        openNoteModal(id, title, content, color, permission, ownerName);
    }
}

function submitNotePassword() {
    const password = document.getElementById('notePasswordInput').value.trim();
    const errorEl  = document.getElementById('passwordError');
    if (!password) {
        errorEl.textContent = 'Vui lòng nhập mật khẩu!';
        errorEl.style.display = 'block';
        return;
    }

    const fd = new FormData();
    fd.append('note_id', currentNoteId);
    fd.append('password', password);

    fetch('api/verify_note.php', { method: 'POST', body: fd })
        .then(res => res.json())
        .then(d => {
            if (d.success) {
                passwordModalInstance.hide();
                isLockedState = true;
                openNoteModal(window.tempOpenData.id, d.title, d.content, d.color, d.permission, window.tempOpenData.ownerName);
            } else {
                errorEl.textContent = d.message || 'Mật khẩu không đúng!';
                errorEl.style.display = 'block';
                document.getElementById('notePasswordInput').value = '';
                document.getElementById('notePasswordInput').focus();
            }
        });
}

function openNoteModal(id = '', title = '', content = '', color = '', permission = 'owner', ownerName = '') {
    currentPermission = permission;
    currentNoteId = id;

    document.getElementById('noteId').value      = id;
    document.getElementById('noteTitle').value   = title;
    document.getElementById('noteContent').value = content;

    const contentEl = document.getElementById('noteContent');
    contentEl.dataset.version = '1';

    document.getElementById('imagePreviewContainer').innerHTML = '';
    document.getElementById('noteLabelsContainer').innerHTML   = '';
    document.getElementById('saveStatus').innerText = '';
    document.getElementById('modalContentWrapper').style.backgroundColor = color || 'var(--bs-body-bg)';

    const isTrash  = currentViewMode === 'trash';
    const isShared = currentViewMode === 'shared';
    const notice   = document.getElementById('sharedNotice');
    const wsBadge  = document.getElementById('wsStatusBadge');

    ['toolsSection', 'colorSection', 'shareManagerSection', 'btnTrashNote', 'btnRestoreNote', 'btnDeletePermanent'].forEach(el => {
        const elem = document.getElementById(el);
        if (elem) elem.style.display = 'none';
    });

    if (isTrash) {
        notice.style.display = 'none';
        document.getElementById('noteTitle').readOnly   = true;
        document.getElementById('noteContent').readOnly = true;
        document.getElementById('btnRestoreNote').style.display    = 'block';
        document.getElementById('btnDeletePermanent').style.display = 'block';
        if (wsBadge) wsBadge.style.display = 'none';

    } else if (isShared) {
        notice.style.display = 'block';
        notice.innerHTML = `
            <strong>Được chia sẻ bởi:</strong> <b>${escapeHtml(ownerName)}</b><br>
            <strong>Quyền:</strong> <b>${permission === 'edit' ? '✅ Có thể chỉnh sửa' : '👁️ Chỉ xem'}</b>
        `;
        document.getElementById('noteTitle').readOnly   = permission === 'read';
        document.getElementById('noteContent').readOnly = permission === 'read';

        if (id) fetch(`api/get_note_images.php?note_id=${id}`).then(r => r.json()).then(imgs => imgs.forEach(img => renderImage(img.file_path, img.id, permission)));
        if (wsBadge) wsBadge.style.display = permission === 'edit' ? 'inline-flex' : 'none';

    } else {
        notice.style.display = 'none';
        document.getElementById('noteTitle').readOnly   = false;
        document.getElementById('noteContent').readOnly = false;

        if (id) {
            document.getElementById('toolsSection').style.display        = 'block';
            document.getElementById('colorSection').style.display        = 'block';
            document.getElementById('shareManagerSection').style.display = 'block';
            document.getElementById('btnTrashNote').style.display        = 'block';

            fetch(`api/get_note_images.php?note_id=${id}`).then(r => r.json()).then(imgs => imgs.forEach(img => renderImage(img.file_path, img.id, 'owner')));
            loadLabelsForNote(id);
            refreshLabelSelector();
            loadSharedUsers(id);
            if (wsBadge) wsBadge.style.display = 'inline-flex';
        }
    }

    if (id) {
        fetch(`api/get_notes.php?note_id=${id}`)
            .then(r => r.json())
            .then(note => { if (note && note.version) contentEl.dataset.version = note.version; })
            .catch(() => {});
    }

    if (id && (permission === 'edit' || permission === 'owner')) {
        startRealtimeForNote(id, permission);
    }

    noteModal.show();
}

// ====================== AUTO REFRESH ======================
function startAutoRefresh() {
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
    if (currentViewMode === 'shared' && currentPermission === 'edit') {
        autoRefreshInterval = setInterval(liveSearch, 4000);
    }
}

function closeAndReload() {
    if (autoRefreshInterval) clearInterval(autoRefreshInterval);
    noteModal.hide();
    liveSearch();
}

// ====================== AUTO SAVE ======================
function autoSave() {
    if (currentViewMode === 'trash' || currentPermission === 'read') return;
    document.getElementById('saveStatus').innerText = 'Đang lưu...';
    clearTimeout(typingTimer);
    typingTimer = setTimeout(() => {
        const id = document.getElementById('noteId').value;
        const t  = document.getElementById('noteTitle').value;
        const c  = document.getElementById('noteContent').value;
        if (!t.trim() && !c.trim()) return;
        fetch('api/save_note.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${encodeURIComponent(id)}&title=${encodeURIComponent(t)}&content=${encodeURIComponent(c)}`
        }).then(res => res.json()).then(d => {
            if (d.success && !id) {
                document.getElementById('noteId').value = d.note_id;
                document.getElementById('toolsSection').style.display        = 'block';
                document.getElementById('colorSection').style.display        = 'block';
                document.getElementById('shareManagerSection').style.display = 'block';
                document.getElementById('btnTrashNote').style.display        = 'block';
                refreshLabelSelector();
            }
            document.getElementById('saveStatus').innerText = d.success ? 'Đã lưu' : 'Lỗi lưu!';
            if (d.success) liveSearch();
        });
    }, 800);
}

// ====================== CHIA SẺ ======================
function shareNote() {
    const noteId = document.getElementById('noteId').value;
    const input  = document.getElementById('share_input').value.trim();
    const perm   = document.getElementById('sharePermission').value;
    if (!noteId) return alert('Vui lòng lưu ghi chú trước!');
    if (!input)  return alert('Vui lòng nhập email!');

    const emails = input.split(',').map(e => e.trim()).filter(Boolean);
    const fd = new FormData();
    fd.append('note_id', noteId);
    fd.append('permission', perm);
    fd.append('share_with', emails.join(','));

    fetch('api/share_note.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            alert(d.message);
            if (d.success) {
                document.getElementById('share_input').value = '';
                loadSharedUsers(noteId);
                liveSearch();
            }
        });
}

function loadSharedUsers(noteId) {
    fetch(`api/get_shares.php?note_id=${noteId}`).then(r => r.json()).then(users => {
        const list = document.getElementById('sharedUsersList');
        list.innerHTML = '';
        users.forEach(u => {
            list.innerHTML += `<li class="list-group-item d-flex justify-content-between align-items-center bg-transparent px-0 py-1">
                <span><i class="bi bi-person-check text-success"></i> ${escapeHtml(u.display_name)} <small>(${u.permission})</small></span>
                <button class="btn btn-sm btn-outline-danger py-0" onclick="revokeShare(${u.share_id})">Xóa</button>
            </li>`;
        });
    });
}

function revokeShare(shareId) {
    if (!confirm('Thu hồi quyền?')) return;
    const fd = new FormData();
    fd.append('share_id', shareId);
    fetch('api/revoke_share.php', { method: 'POST', body: fd })
        .then(() => loadSharedUsers(document.getElementById('noteId').value));
}

// ====================== KHÓA GHI CHÚ ======================
function toggleLock() {
    const id = document.getElementById('noteId').value;
    if (!id) return;
    if (isLockedState) {
        _showLockActionPicker(id);
    } else {
        _showLockSetModal(id);
    }
}

function _showLockSetModal(id) {
    _openPasswordModal({
        title: '🔒 Đặt mật khẩu cho ghi chú',
        fields: [
            { id: 'pm_new_pw',     placeholder: 'Mật khẩu mới (≥ 4 ký tự)', type: 'password' },
            { id: 'pm_confirm_pw', placeholder: 'Nhập lại mật khẩu',         type: 'password' }
        ],
        onConfirm(vals, showError) {
            const [pw, pw2] = vals;
            if (pw.length < 4) return showError('Mật khẩu phải có ít nhất 4 ký tự!');
            if (pw !== pw2)    return showError('Mật khẩu xác nhận không khớp!');
            return fetch('api/lock_note.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ note_id: id, action: 'lock', password: pw })
            }).then(r => r.json()).then(d => {
                if (d.success) {
                    isLockedState = true;
                    document.getElementById('btnLock').innerHTML = '<i class="bi bi-unlock"></i> Mở khóa';
                    liveSearch();
                    return true;
                }
                showError(d.message || 'Không thể đặt khóa!');
                return false;
            });
        }
    });
}

function _showLockActionPicker(id) {
    _openPasswordModal({
        title: '🔒 Ghi chú đang được khóa',
        fields: [
            { id: 'pm_old_pw', placeholder: 'Nhập mật khẩu hiện tại', type: 'password' }
        ],
        actions: [
            { label: 'Gỡ khóa',      style: 'btn-warning', value: 'unlock' },
            { label: 'Đổi mật khẩu', style: 'btn-primary', value: 'change' }
        ],
        onConfirm(vals, showError, actionValue) {
            const [oldPw] = vals;
            if (!oldPw) return showError('Vui lòng nhập mật khẩu hiện tại!');
            if (actionValue === 'unlock') {
                return _doUnlock(id, oldPw, showError);
            } else {
                return fetch('api/lock_note.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: new URLSearchParams({ note_id: id, action: 'verify', old_password: oldPw })
                }).then(r => r.json()).then(d => {
                    if (!d.success) { showError(d.message || 'Mật khẩu không đúng!'); return false; }
                    passwordModalInstance.hide();
                    setTimeout(() => _showChangePasswordModal(id, oldPw), 300);
                    return false;
                });
            }
        }
    });
}

function _doUnlock(id, oldPw, showError) {
    return fetch('api/lock_note.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ note_id: id, action: 'unlock', old_password: oldPw })
    }).then(r => r.json()).then(d => {
        if (d.success) {
            isLockedState = false;
            document.getElementById('btnLock').innerHTML = '<i class="bi bi-lock"></i> Đặt mật khẩu';
            liveSearch();
            return true;
        }
        showError(d.message || 'Mật khẩu không đúng!');
        return false;
    });
}

function _showChangePasswordModal(id, oldPw) {
    _openPasswordModal({
        title: '🔑 Đặt mật khẩu mới',
        fields: [
            { id: 'pm_new_pw',     placeholder: 'Mật khẩu mới (≥ 4 ký tự)', type: 'password' },
            { id: 'pm_confirm_pw', placeholder: 'Nhập lại mật khẩu',         type: 'password' }
        ],
        onConfirm(vals, showError) {
            const [pw, pw2] = vals;
            if (pw.length < 4) return showError('Mật khẩu phải có ít nhất 4 ký tự!');
            if (pw !== pw2)    return showError('Mật khẩu xác nhận không khớp!');
            return fetch('api/lock_note.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ note_id: id, action: 'change', old_password: oldPw, password: pw })
            }).then(r => r.json()).then(d => {
                if (d.success) { liveSearch(); return true; }
                showError(d.message || 'Không thể đổi mật khẩu!');
                return false;
            });
        }
    });
}

function _openPasswordModal(config) {
    const titleEl  = document.getElementById('passwordModalTitle');
    const bodyEl   = document.getElementById('passwordModal').querySelector('.modal-body');
    const footerEl = document.getElementById('passwordModal').querySelector('.modal-footer');

    titleEl.textContent = config.title;

    bodyEl.innerHTML = config.fields.map(f =>
        `<input id="${f.id}" type="${f.type}" class="form-control mb-2" placeholder="${f.placeholder}" autocomplete="off">`
    ).join('') + `<div id="pm_error" class="text-danger small mt-1" style="display:none;"></div>`;

    const actions = config.actions || [{ label: 'Xác nhận', style: 'btn-primary', value: 'confirm' }];
    footerEl.innerHTML = `<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>` +
        actions.map(a => `<button type="button" class="btn ${a.style} pm-action-btn" data-action="${a.value}">${a.label}</button>`).join('');

    const errorEl = document.getElementById('pm_error');

    function showError(msg) {
        errorEl.textContent = msg;
        errorEl.style.display = 'block';
        footerEl.querySelectorAll('.pm-action-btn').forEach(b => { b.disabled = false; b.textContent = b.dataset.origLabel; });
    }

    footerEl.querySelectorAll('.pm-action-btn').forEach(btn => {
        btn.dataset.origLabel = btn.textContent;
        btn.addEventListener('click', function () {
            errorEl.style.display = 'none';
            const vals = config.fields.map(f => document.getElementById(f.id).value.trim());
            footerEl.querySelectorAll('.pm-action-btn').forEach(b => { b.disabled = true; });
            btn.textContent = 'Đang xử lý...';
            Promise.resolve(config.onConfirm(vals, showError, btn.dataset.action))
                .then(shouldClose => { if (shouldClose === true) passwordModalInstance.hide(); })
                .catch(() => { showError('Lỗi kết nối, vui lòng thử lại!'); });
        });
    });

    document.getElementById('passwordModal').addEventListener('shown.bs.modal', function onShown() {
        document.getElementById(config.fields[0].id)?.focus();
        this.removeEventListener('shown.bs.modal', onShown);
    });

    passwordModalInstance.show();
}

// ====================== XÓA GHI CHÚ ======================
function deleteNote(action) {
    const id = document.getElementById('noteId').value;
    if (!id) return;
    const confirmMsg = action === 'trash' ? 'Chuyển vào thùng rác?' : 'Xóa vĩnh viễn? Hành động này không thể hoàn tác!';
    if (!confirm(confirmMsg)) return;
    if (isLockedState) {
        _deleteNoteWithPassword(id, action, null);
    } else {
        _doDeleteNote(id, action);
    }
}

function _doDeleteNote(id, action) {
    fetch('api/delete_note.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ id, action, delete_password: '' }).toString()
    })
    .then(res => res.json())
    .then(d => {
        if (d.success) {
            closeAndReload();
        } else if (d.require_password) {
            _deleteNoteWithPassword(id, action, d.message);
        } else {
            alert(d.error || 'Không thể xóa ghi chú!');
        }
    })
    .catch(() => alert('Lỗi kết nối khi xóa ghi chú!'));
}

function _deleteNoteWithPassword(id, action, errorMsg) {
    const titleEl    = document.getElementById('passwordModalTitle');
    const inputEl    = document.getElementById('notePasswordInput');
    const errorEl    = document.getElementById('passwordError');
    const confirmBtn = document.getElementById('passwordModalConfirmBtn');

    titleEl.textContent = '🔒 Nhập mật khẩu để xác nhận xóa';
    inputEl.value = '';

    if (errorMsg) { errorEl.textContent = errorMsg; errorEl.style.display = 'block'; }
    else          { errorEl.style.display = 'none'; }

    const newBtn = confirmBtn.cloneNode(true);
    confirmBtn.parentNode.replaceChild(newBtn, confirmBtn);

    newBtn.onclick = function () {
        const pw = inputEl.value.trim();
        if (!pw) { errorEl.textContent = 'Vui lòng nhập mật khẩu!'; errorEl.style.display = 'block'; inputEl.focus(); return; }
        newBtn.disabled = true;
        newBtn.textContent = 'Đang xác nhận...';
        errorEl.style.display = 'none';

        fetch('api/delete_note.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ id, action, delete_password: pw }).toString()
        })
        .then(res => res.json())
        .then(d => {
            if (d.success) {
                _restorePasswordModal(newBtn);
                passwordModalInstance.hide();
                closeAndReload();
            } else {
                errorEl.textContent = d.message || 'Mật khẩu không đúng!';
                errorEl.style.display = 'block';
                inputEl.value = '';
                inputEl.focus();
                newBtn.disabled = false;
                newBtn.textContent = 'Xác nhận';
            }
        })
        .catch(() => {
            errorEl.textContent = 'Lỗi kết nối, vui lòng thử lại!';
            errorEl.style.display = 'block';
            newBtn.disabled = false;
            newBtn.textContent = 'Xác nhận';
        });
    };

    passwordModalInstance.show();
    setTimeout(() => inputEl.focus(), 500);
}

function _restorePasswordModal(btn) {
    const restored = btn.cloneNode(true);
    restored.textContent = 'Xác nhận';
    restored.disabled = false;
    restored.onclick = submitNotePassword;
    btn.parentNode.replaceChild(restored, btn);
}

function restoreNote() {
    const id = document.getElementById('noteId').value;
    fetch('api/restore_note.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}`
    }).then(() => { alert('Khôi phục thành công!'); closeAndReload(); });
}

function renameLabel(id, currentName) {
    const newName = prompt('Đổi tên nhãn:', currentName);
    if (!newName || newName.trim() === '' || newName === currentName) return;
    fetch('api/manage_labels.php?action=rename', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}&name=${encodeURIComponent(newName.trim())}`
    }).then(() => loadFilterLabels(() => liveSearch()));
}

// ====================== WEBSOCKET REALTIME ======================
let ws               = null;
let wsReconnectTimer = null;
let wsReady          = false;
let currentNoteIdForWS = null;

const WS_HOST = (location.protocol === 'https:' ? 'wss://' : 'ws://') + location.hostname + ':8080';

function connectWebSocket() {
    if (ws && (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING)) return;
    try {
        ws = new WebSocket(WS_HOST);
    } catch (e) {
        console.warn('WebSocket không khả dụng, dùng fallback polling.');
        _startFallbackPolling();
        return;
    }

    ws.onopen = () => {
        clearTimeout(wsReconnectTimer);
        ws.send(JSON.stringify({ type: 'auth', user_id: currentUserId, user_name: currentUserName }));
        _setWsStatus('connecting');
    };

    ws.onmessage = (event) => {
        try {
            const data = JSON.parse(event.data);

            if (data.type === 'auth_success') {
                wsReady = true;
                _setWsStatus('online');
                if (currentNoteIdForWS) _wsSend({ type: 'join_note', note_id: currentNoteIdForWS });
            }

            if (data.type === 'update' && data.note_id == currentNoteIdForWS) {
                const titleEl   = document.getElementById('noteTitle');
                const contentEl = document.getElementById('noteContent');

                const isEditingTitle   = document.activeElement === titleEl;
                const isEditingContent = document.activeElement === contentEl;

                if (data.title !== undefined && !isEditingTitle) {
                    titleEl.value = data.title;
                }

                if (data.content !== undefined) {
                    const currentContent  = contentEl.value || '';
                    const incomingContent = String(data.content);

                    if (!isEditingContent) {
                        contentEl.value = incomingContent;
                    } else {
                        const isDeleting   = incomingContent.length < currentContent.length;
                        const tooDifferent = Math.abs(incomingContent.length - currentContent.length) > 5;

                        if (isDeleting || tooDifferent) {
                            const cursorPos = contentEl.selectionStart;
                            contentEl.value = incomingContent;
                            try { contentEl.setSelectionRange(cursorPos, cursorPos); } catch (e) {}
                        }
                    }
                }

                _showTypingIndicator(data.user_name);
            }

            if (data.type === 'presence' && data.note_id == currentNoteIdForWS) {
                _renderPresence(data.users);
            }
        } catch (e) {
            console.error('WS parse error:', e);
        }
    };

    ws.onclose = () => {
        wsReady = false;
        _setWsStatus('offline');
        wsReconnectTimer = setTimeout(connectWebSocket, 3000);
    };

    ws.onerror = () => { _setWsStatus('offline'); };
}

function _wsSend(obj) {
    if (ws && ws.readyState === WebSocket.OPEN) ws.send(JSON.stringify(obj));
}

let _pollInterval = null;
function _startFallbackPolling() {
    if (_pollInterval) return;
    _setWsStatus('polling');
    _pollInterval = setInterval(() => {
        if (!currentNoteIdForWS) return;
        fetch(`api/get_notes.php?note_id=${currentNoteIdForWS}`)
            .then(r => r.ok ? r.json() : null)
            .then(note => {
                if (!note) return;
                const titleEl   = document.getElementById('noteTitle');
                const contentEl = document.getElementById('noteContent');
                if (document.activeElement !== titleEl)   titleEl.value   = note.title   ?? '';
                if (document.activeElement !== contentEl) contentEl.value = note.content ?? '';
            })
            .catch(() => {});
    }, 4000);
}

function _stopFallbackPolling() { clearInterval(_pollInterval); _pollInterval = null; }

function _setWsStatus(state) {
    const el = document.getElementById('wsStatusBadge');
    if (!el) return;
    const map = {
        online:     { text: '● Trực tuyến',      cls: 'bg-success'   },
        offline:    { text: '● Mất kết nối',      cls: 'bg-danger'    },
        connecting: { text: '● Đang kết nối…',   cls: 'bg-warning'   },
        polling:    { text: '● Chế độ dự phòng', cls: 'bg-secondary' }
    };
    const s = map[state] || map.offline;
    el.textContent = s.text;
    el.className   = `badge ${s.cls} ms-2 small`;
}

function _renderPresence(users) {
    const el = document.getElementById('wsPresenceBar');
    if (!el) return;
    const others = users.filter(u => u !== currentUserName);
    if (others.length === 0) { el.textContent = ''; return; }
    el.innerHTML = `<i class="bi bi-people-fill"></i> Đang xem cùng: <strong>${others.map(u => escapeHtml(u)).join(', ')}</strong>`;
}

let _typingTimer = null;
function _showTypingIndicator(userName) {
    const el = document.getElementById('wsTypingIndicator');
    if (!el || userName === currentUserName) return;
    el.textContent = `✏️ ${escapeHtml(userName)} đang chỉnh sửa…`;
    el.style.display = 'block';
    clearTimeout(_typingTimer);
    _typingTimer = setTimeout(() => { el.style.display = 'none'; }, 2500);
}

function startRealtimeForNote(noteId, permission) {
    if (permission !== 'edit' && permission !== 'owner') return;
    currentNoteIdForWS = noteId;
    connectWebSocket();
    if (wsReady) _wsSend({ type: 'join_note', note_id: noteId });
}

function stopRealtime() {
    if (currentNoteIdForWS) _wsSend({ type: 'leave_note', note_id: currentNoteIdForWS });
    currentNoteIdForWS = null;
    _stopFallbackPolling();
    _clearPresenceUI();
}

function _clearPresenceUI() {
    const p = document.getElementById('wsPresenceBar');
    const t = document.getElementById('wsTypingIndicator');
    if (p) p.textContent = '';
    if (t) { t.textContent = ''; t.style.display = 'none'; }
}

// Tối ưu broadcast realtime
let lastSentContent = {};

const _origAutoSave = autoSave;
autoSave = function () {
    _origAutoSave.call(this);
    if (!wsReady || !currentNoteIdForWS) return;

    const title   = document.getElementById('noteTitle').value || '';
    const content = document.getElementById('noteContent').value || '';
    const key     = currentNoteIdForWS;

    if (lastSentContent[key] !== content) {
        _wsSend({ type: 'update', note_id: currentNoteIdForWS, title, content, user_name: currentUserName });
        lastSentContent[key] = content;
    }
};

const _origOpenNoteModal = openNoteModal;
openNoteModal = function (id = '', title = '', content = '', color = '', permission = 'owner', ownerName = '') {
    _origOpenNoteModal(id, title, content, color, permission, ownerName);
    if (id) startRealtimeForNote(id, permission);
};

const _origCloseAndReload = closeAndReload;
closeAndReload = function () {
    stopRealtime();
    _origCloseAndReload();
};

// Force sync content khi focus out
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('noteContent').addEventListener('blur', function () {
        if (wsReady && currentNoteIdForWS) {
            _wsSend({
                type: 'update',
                note_id: currentNoteIdForWS,
                title:   document.getElementById('noteTitle').value || '',
                content: this.value || '',
                user_name: currentUserName
            });
        }
    });

    // Bảo vệ modal không tự đóng khi đang chỉnh sửa
    document.getElementById('noteModal').addEventListener('hide.bs.modal', function (event) {
        if (document.getElementById('noteId').value &&
            (document.getElementById('noteTitle')   === document.activeElement ||
             document.getElementById('noteContent') === document.activeElement)) {
            event.preventDefault();
        }
    });
});

// Realtime typing broadcast (80ms debounce)
let realtimeTypingTimer = null;
document.addEventListener('input', function (e) {
    if (!wsReady || !currentNoteIdForWS) return;
    if (e.target.id !== 'noteTitle' && e.target.id !== 'noteContent') return;

    clearTimeout(realtimeTypingTimer);
    realtimeTypingTimer = setTimeout(() => {
        _wsSend({
            type:      'update',
            note_id:   currentNoteIdForWS,
            title:     document.getElementById('noteTitle').value || '',
            content:   document.getElementById('noteContent').value || '',
            user_name: currentUserName
        });
    }, 80);
});

// ====================== ẢNH ======================
function uploadImage() {
    const nid = document.getElementById('noteId').value;
    const f   = document.getElementById('imageInput').files[0];
    if (!f) return;
    const fd = new FormData();
    fd.append('image', f);
    fd.append('note_id', nid);
    fetch('api/upload_image.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(d => {
            if (d.success) renderImage(d.file_path, d.image_id, 'owner');
            else alert(d.message);
            document.getElementById('imageInput').value = '';
        });
}

function renderImage(path, id, perm) {
    const del = perm === 'owner'
        ? `<button class="btn btn-danger btn-sm position-absolute top-0 end-0 p-0 rounded-circle" style="width:24px;height:24px;margin:-8px -8px 0 0;" onclick="deleteImage(${id},this)"><i class="bi bi-x"></i></button>`
        : '';
    document.getElementById('imagePreviewContainer').innerHTML +=
        `<div class="position-relative shadow-sm rounded"><img src="${path}" class="img-thumbnail" style="width:120px;height:120px;object-fit:cover;">${del}</div>`;
}

function deleteImage(id, btn) {
    if (confirm('Xóa ảnh?')) {
        fetch('api/delete_image.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}`
        }).then(r => r.json()).then(d => { if (d.success) btn.parentElement.remove(); });
    }
}

// ====================== LABEL ======================
function loadFilterLabels(callback) {
    fetch('api/manage_labels.php?action=list').then(r => r.json()).then(ls => {
        const bar = document.getElementById('labelFilterBar');
        bar.innerHTML = `<button class="btn btn-sm ${currentLabelId === null ? 'btn-dark' : 'btn-outline-secondary'}" onclick="filterLabel(null)">Tất cả</button>`;
        ls.forEach(l => {
            bar.innerHTML += `<div class="btn-group btn-group-sm">
                <button class="btn ${currentLabelId == l.id ? 'btn-dark' : 'btn-outline-secondary'}" onclick="filterLabel(${l.id})">${escapeHtml(l.name)}</button>
                <button class="btn btn-outline-secondary" onclick="event.stopPropagation();renameLabel(${l.id},'${escapeHtml(l.name).replace(/'/g, "\\'")}')"><i class="bi bi-pencil"></i></button>
                <button class="btn btn-outline-danger" onclick="event.stopPropagation();deleteLabel(${l.id})"><i class="bi bi-x"></i></button>
            </div>`;
        });
        if (callback) callback();
    });
}

function filterLabel(id) { currentLabelId = id; loadFilterLabels(() => liveSearch()); }

function deleteLabel(id) {
    if (confirm('Xóa nhãn?')) {
        fetch('api/manage_labels.php?action=delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `id=${id}`
        }).then(() => { if (currentLabelId == id) currentLabelId = null; loadFilterLabels(() => liveSearch()); });
    }
}

function addNewLabel() {
    const name = document.getElementById('newLabelName').value.trim();
    if (!name) return;
    fetch('api/manage_labels.php?action=add', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `name=${encodeURIComponent(name)}`
    }).then(() => { document.getElementById('newLabelName').value = ''; loadFilterLabels(); });
}

function refreshLabelSelector() {
    fetch('api/manage_labels.php?action=list').then(r => r.json()).then(ls => {
        const s = document.getElementById('labelSelector');
        s.innerHTML = '<option value="">+ Nhãn</option>';
        ls.forEach(l => s.innerHTML += `<option value="${l.id}">${escapeHtml(l.name)}</option>`);
    });
}

function loadLabelsForNote(nid) {
    fetch(`api/get_note_labels.php?note_id=${nid}`).then(r => r.json()).then(ls => {
        const c = document.getElementById('noteLabelsContainer');
        c.innerHTML = '';
        ls.forEach(l => c.innerHTML += `<span class="badge bg-secondary">${escapeHtml(l.name)} <i class="bi bi-x-circle-fill cp" onclick="removeLabel(${nid},${l.id})"></i></span>`);
    });
}

function addLabelToNote() {
    const nid = document.getElementById('noteId').value;
    const lid = document.getElementById('labelSelector').value;
    if (!lid) return;
    fetch('api/set_note_label.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `note_id=${nid}&label_id=${lid}&action=add`
    }).then(() => { loadLabelsForNote(nid); liveSearch(); document.getElementById('labelSelector').value = ''; });
}

function removeLabel(nid, lid) {
    fetch('api/set_note_label.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `note_id=${nid}&label_id=${lid}&action=remove`
    }).then(() => { loadLabelsForNote(nid); liveSearch(); });
}

// ====================== TIỆN ÍCH ======================
function setView(v) {
    document.getElementById('notesContainer').className = v === 'grid' ? 'note-grid-view pb-5' : 'note-list-view pb-5';
}

function escapeHtml(s) {
    if (!s) return '';
    return s.toString()
        .replace(/&/g,  '&amp;')
        .replace(/</g,  '&lt;')
        .replace(/>/g,  '&gt;')
        .replace(/"/g,  '&quot;')
        .replace(/'/g,  '&#39;');
}

function previewImage(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => document.getElementById('previewAvatar').src = e.target.result;
        reader.readAsDataURL(input.files[0]);
    }
}

function applyTheme(theme) {
    document.documentElement.setAttribute('data-bs-theme', theme);
    localStorage.setItem('noteapp_theme', theme);
}

function saveProfile() {
    const fd        = new FormData();
    const hasAvatar = document.getElementById('inputAvatar').files[0];
    if (hasAvatar) fd.append('avatar', hasAvatar);

    const fontSize  = document.getElementById('settingFontSize').value;
    const theme     = document.getElementById('settingTheme').value;
    const noteColor = document.getElementById('settingNoteColor').value;
    fd.append('font_size',   fontSize);
    fd.append('theme_color', theme);
    fd.append('note_color',  noteColor);

    applyTheme(theme);
    document.body.style.fontSize = fontSize;

    fetch('api/update_profile.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                if (hasAvatar) {
                    location.reload();
                } else {
                    bootstrap.Modal.getInstance(document.getElementById('profileModal'))?.hide();
                }
            } else {
                alert(data.message || 'Lỗi cập nhật!');
            }
        })
        .catch(() => alert('Lỗi kết nối!'));
}

function togglePin(id, state) {
    fetch('api/pin_note.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}&is_pinned=${state}`
    }).then(() => liveSearch());
}

function changeColor(color) {
    const id = document.getElementById('noteId').value;
    if (!id) { alert('Vui lòng lưu ghi chú trước khi đổi màu!'); return; }
    fetch('api/change_color.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `id=${id}&color=${encodeURIComponent(color)}`
    })
    .then(res => res.json())
    .then(d => {
        if (d.success) {
            document.getElementById('modalContentWrapper').style.backgroundColor = color || 'var(--bs-body-bg)';
            liveSearch();
        } else {
            alert('Không thể đổi màu ghi chú!');
        }
    })
    .catch(err => { console.error(err); alert('Lỗi kết nối khi đổi màu!'); });
}

function formatRelativeTime(datetime) {
    if (!datetime) return '';
    const date    = new Date(datetime);
    const diffMin = Math.floor((new Date() - date) / 60000);
    if (diffMin < 1)    return 'Vừa xong';
    if (diffMin < 60)   return diffMin + ' phút trước';
    if (diffMin < 1440) return Math.floor(diffMin / 60) + ' giờ trước';
    return date.toLocaleDateString('vi-VN', { day: 'numeric', month: 'short' });
}

// ====================== OFFLINE SUPPORT ======================
let db;

function initIndexedDB() {
    const request = indexedDB.open('NoteAppDB', 1);

    request.onupgradeneeded = function (event) {
        db = event.target.result;
        if (!db.objectStoreNames.contains('notes')) {
            db.createObjectStore('notes', { keyPath: 'id' });
        }
    };

    request.onsuccess = function (event) {
        db = event.target.result;
    };

    request.onerror = function (event) {
        console.error('IndexedDB error:', event.target.error);
    };
}

function saveNoteOffline(note) {
    if (!db) return;
    db.transaction(['notes'], 'readwrite').objectStore('notes').put(note);
}

function syncOfflineNotes() {
    if (!navigator.onLine || !db) return;
    const store   = db.transaction(['notes'], 'readonly').objectStore('notes');
    const request = store.getAll();

    request.onsuccess = function () {
        request.result.forEach(note => {
            fetch('api/save_note.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `id=${note.id}&title=${encodeURIComponent(note.title)}&content=${encodeURIComponent(note.content)}`
            });
        });
        db.transaction(['notes'], 'readwrite').objectStore('notes').clear();
    };
}

websocket
server.php
<?php
require __DIR__ . '/../vendor/autoload.php';

use Ratchet\Server\IoServer;
use Ratchet\Http\HttpServer;
use Ratchet\WebSocket\WsServer;
use App\NoteWebSocket;

$server = IoServer::factory(
    new HttpServer(
        new WsServer(
            new NoteWebSocket()
        )
    ),
    8080  // Port WebSocket - KHÔNG đổi nếu chưa biết
);

echo "🚀 WebSocket Server NoteApp đang chạy trên ws://localhost:8080\n";
echo "Nhấn Ctrl + C để dừng server...\n";

$server->run();