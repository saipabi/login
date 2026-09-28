<?php
/**
 * db.php
 * Unified database and session configuration.
 * Connects to:
 * 1. MySQL (User credentials & authentication using Prepared Statements)
 * 2. Redis (Session storage with TTL, replacing PHP sessions)
 * 3. MongoDB (User profile details: age, dob, contact, address, etc.)
 */

// Global response helper
function sendJsonResponse($status, $message = '', $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    
    $response = [
        'status' => $status,
        'message' => $message,
        'data' => $data
    ];

    if (is_array($data)) {
        if (isset($data['token'])) {
            $response['token'] = $data['token'];
        }
        if (isset($data['user'])) {
            $response['user'] = $data['user'];
        }
    }

    echo json_encode($response);
    exit;
}

// Configuration Constants (Easily switch between Local and Cloud)
define('MYSQL_HOST', getenv('MYSQL_HOST') ?: 'gateway01.ap-southeast-1.prod.aws.tidbcloud.com');
define('MYSQL_PORT', getenv('MYSQL_PORT') ?: 4000);
define('MYSQL_USER', getenv('MYSQL_USER') ?: 'NLS6MgEimuRYJNX.root');
define('MYSQL_PASS', getenv('MYSQL_PASS') ?: 'I86PsKRkL5xQBAKc');
define('MYSQL_DB',   getenv('MYSQL_DB')   ?: 'auth_db');
define('MYSQL_SSL',  getenv('MYSQL_SSL')  !== false ? (bool)getenv('MYSQL_SSL') : true);

define('REDIS_HOST', getenv('REDIS_HOST') ?: 'striking-pup-312255.upstash.io');
define('REDIS_PORT', getenv('REDIS_PORT') ?: 6379);
define('REDIS_PASS', getenv('REDIS_PASS') ?: 'gQAAAAAABMO_AAIgcDJlYmQwYzRiMjAxN2Y0ZDcxYWU4NzMxYTEzNTEzODlhYQ');
define('REDIS_REST_URL', getenv('REDIS_REST_URL') ?: 'https://striking-pup-312255.upstash.io');
define('REDIS_REST_TOKEN', getenv('REDIS_REST_TOKEN') ?: 'gQAAAAAABMO_AAIgcDJlYmQwYzRiMjAxN2Y0ZDcxYWU4NzMxYTEzNTEzODlhYQ');

define('MONGO_URI',  getenv('MONGO_URI')  ?: 'mongodb+srv://login:i2Ri5V7UtG2thAMo@cluster0.zvqghry.mongodb.net/?appName=Cluster0');
define('MONGO_DB',   getenv('MONGO_DB')   ?: 'auth_db');
define('MONGO_COLLECTION', 'profiles');

/**
 * =========================================================================
 * 1. MySQL Connection (Enforces Prepared Statements)
 * =========================================================================
 */
function getMySQLConnection() {
    static $conn = null;
    if ($conn !== null) {
        return $conn;
    }

    $conn = mysqli_init();
    $flags = 0;
    if (MYSQL_SSL || strpos(MYSQL_HOST, 'tidbcloud.com') !== false || MYSQL_PORT == 4000) {
        $conn->ssl_set(NULL, NULL, NULL, NULL, NULL);
        $flags = MYSQLI_CLIENT_SSL;
    }

    // Connect to server (with database)
    $connected = @$conn->real_connect(MYSQL_HOST, MYSQL_USER, MYSQL_PASS, MYSQL_DB, (int)MYSQL_PORT, NULL, $flags);
    if (!$connected) {
        // Fallback without DB first to create database
        $connected = @$conn->real_connect(MYSQL_HOST, MYSQL_USER, MYSQL_PASS, '', (int)MYSQL_PORT, NULL, $flags);
        if ($connected) {
            $conn->query("CREATE DATABASE IF NOT EXISTS `" . MYSQL_DB . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            $conn->select_db(MYSQL_DB);
        } else {
            sendJsonResponse('error', 'MySQL Connection Failed: ' . $conn->connect_error, null, 500);
        }
    }

    // Auto-create users table if not exists
    $createTableSql = "CREATE TABLE IF NOT EXISTS `users` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(100) NOT NULL,
        `email` VARCHAR(150) NOT NULL UNIQUE,
        `password` VARCHAR(255) NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
    $conn->query($createTableSql);

    $conn->set_charset("utf8mb4");
    return $conn;
}

/**
 * =========================================================================
 * 2. Redis Session Manager (Backend Session Storage)
 * =========================================================================
 */
class RedisSessionManager {
    private $redis = null;
    private $isNative = false;
    private $socket = null;
    private $fallbackDir = null;
    private $isUpstash = false;

    public function __construct() {
        // 0. Upstash Cloud Redis (REST API - works everywhere without TLS socket quirks)
        if (defined('REDIS_REST_URL') && REDIS_REST_URL && defined('REDIS_REST_TOKEN') && REDIS_REST_TOKEN) {
            $this->isUpstash = true;
            return;
        }

        // 1. Try native Redis extension if loaded
        if (class_exists('Redis')) {
            try {
                $r = new Redis();
                if (@$r->connect(REDIS_HOST, (int)REDIS_PORT, 2.0)) {
                    if (REDIS_PASS) {
                        $r->auth(REDIS_PASS);
                    }
                    $this->redis = $r;
                    $this->isNative = true;
                    return;
                }
            } catch (Exception $e) {
                // fall through to TCP socket
            }
        }

        // 2. Try raw TCP socket to Redis (Works on ALL PHP installs without ext-redis!)
        try {
            $fp = @fsockopen(REDIS_HOST, (int)REDIS_PORT, $errno, $errstr, 2.0);
            if ($fp) {
                $this->socket = $fp;
                if (REDIS_PASS) {
                    $this->executeCommand("AUTH " . REDIS_PASS);
                }
                return;
            }
        } catch (Exception $e) {
            // fall through to fallback
        }

        // 3. Fallback temporary session store if Redis server is stopped
        $this->fallbackDir = __DIR__ . '/.session_cache';
        if (!is_dir($this->fallbackDir)) {
            @mkdir($this->fallbackDir, 0777, true);
        }
    }

    private function upstashCommand($cmdArray) {
        if (!defined('REDIS_REST_URL') || !defined('REDIS_REST_TOKEN')) return null;
        $ch = curl_init(REDIS_REST_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($cmdArray));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . REDIS_REST_TOKEN,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_TIMEOUT, 4);
        $res = curl_exec($ch);
        curl_close($ch);
        if (!$res) return null;
        $decoded = json_decode($res, true);
        return isset($decoded['result']) ? $decoded['result'] : null;
    }

    private function executeCommand($cmd) {
        if (!$this->socket) return false;
        fwrite($this->socket, $cmd . "\r\n");
        $reply = fgets($this->socket);
        return trim($reply);
    }

    public function set($key, $value, $ttlSeconds = 86400) {
        $serialized = is_array($value) ? json_encode($value) : $value;
        $redisKey = "session:" . $key;

        if ($this->isUpstash) {
            $res = $this->upstashCommand(["SET", $redisKey, $serialized, "EX", $ttlSeconds]);
            return ($res === 'OK');
        }

        if ($this->isNative && $this->redis) {
            return $this->redis->setex($redisKey, $ttlSeconds, $serialized);
        }

        if ($this->socket) {
            // RESP protocol command
            $cmd = "SETEX {$redisKey} {$ttlSeconds} " . json_encode($serialized);
            $resp = $this->executeCommand($cmd);
            return ($resp === '+OK');
        }

        // Fallback file storage
        if ($this->fallbackDir) {
            $file = $this->fallbackDir . '/' . md5($key) . '.json';
            return file_put_contents($file, json_encode([
                'expires_at' => time() + $ttlSeconds,
                'data' => $serialized
            ])) !== false;
        }

        return false;
    }

    public function get($key) {
        $redisKey = "session:" . $key;

        if ($this->isUpstash) {
            $val = $this->upstashCommand(["GET", $redisKey]);
            if ($val === null) return null;
            $decoded = json_decode($val, true);
            return $decoded !== null ? $decoded : $val;
        }

        if ($this->isNative && $this->redis) {
            $val = $this->redis->get($redisKey);
            return $val ? json_decode($val, true) : null;
        }

        if ($this->socket) {
            fwrite($this->socket, "GET {$redisKey}\r\n");
            $line = fgets($this->socket);
            if (substr($line, 0, 1) === '$') {
                $len = (int)substr($line, 1);
                if ($len < 0) return null;
                $data = fread($this->socket, $len);
                fgets($this->socket); // trailing \r\n
                $decoded = json_decode($data, true);
                if (is_string($decoded)) {
                    $nested = json_decode($decoded, true);
                    return $nested !== null ? $nested : $decoded;
                }
                return $decoded;
            }
            return null;
        }

        if ($this->fallbackDir) {
            $file = $this->fallbackDir . '/' . md5($key) . '.json';
            if (file_exists($file)) {
                $content = json_decode(file_get_contents($file), true);
                if ($content && isset($content['expires_at']) && $content['expires_at'] > time()) {
                    return json_decode($content['data'], true);
                }
                @unlink($file);
            }
        }

        return null;
    }

    public function delete($key) {
        $redisKey = "session:" . $key;

        if ($this->isUpstash) {
            $this->upstashCommand(["DEL", $redisKey]);
            return true;
        }

        if ($this->isNative && $this->redis) {
            return $this->redis->del($redisKey);
        }

        if ($this->socket) {
            $this->executeCommand("DEL {$redisKey}");
            return true;
        }

        if ($this->fallbackDir) {
            $file = $this->fallbackDir . '/' . md5($key) . '.json';
            if (file_exists($file)) {
                @unlink($file);
            }
            return true;
        }

        return false;
    }
}

/**
 * =========================================================================
 * 3. MongoDB Profile Manager (Profile Data Storage)
 * =========================================================================
 */
class MongoProfileManager {
    private $manager = null;
    private $fallbackDir = null;

    public function __construct() {
        // 1. Try PHP MongoDB Driver extension if present
        if (class_exists('MongoDB\Driver\Manager')) {
            try {
                $this->manager = new MongoDB\Driver\Manager(MONGO_URI);
            } catch (Exception $e) {
                // fall through to fallback
            }
        }

        // 2. Data fallback directory if MongoDB extension or service is pending
        $this->fallbackDir = __DIR__ . '/.mongo_profiles';
        if (!is_dir($this->fallbackDir)) {
            @mkdir($this->fallbackDir, 0777, true);
        }
    }

    public function getProfile($email) {
        if ($this->manager) {
            try {
                $filter = ['email' => $email];
                $query = new MongoDB\Driver\Query($filter);
                $cursor = $this->manager->executeQuery(MONGO_DB . '.' . MONGO_COLLECTION, $query);
                $rows = $cursor->toArray();
                if (!empty($rows)) {
                    return (array)$rows[0];
                }
            } catch (Exception $e) {
                // fall through to fallback
            }
        }

        // Read from file fallback
        $file = $this->fallbackDir . '/' . md5($email) . '.json';
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true);
        }

        return [
            'email' => $email,
            'age' => '',
            'dob' => '',
            'contact' => '',
            'city' => '',
            'address' => '',
            'bio' => '',
            'created_at' => date('c'),
            'updated_at' => date('c')
        ];
    }

    public function updateProfile($email, $data) {
        $data['email'] = $email;
        $data['updated_at'] = date('c');

        if ($this->manager) {
            try {
                $bulk = new MongoDB\Driver\BulkWrite();
                $bulk->update(
                    ['email' => $email],
                    ['$set' => $data],
                    ['upsert' => true]
                );
                $this->manager->executeBulkWrite(MONGO_DB . '.' . MONGO_COLLECTION, $bulk);
                return true;
            } catch (Exception $e) {
                // fall through to fallback
            }
        }

        // Write to file fallback
        $file = $this->fallbackDir . '/' . md5($email) . '.json';
        return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)) !== false;
    }
}
