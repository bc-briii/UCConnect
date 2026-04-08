<?php
session_start();
header('Content-Type: application/json');

// Database configuration for InfinityFree
$host = 'sql100.infinityfree.com';
$dbname = 'if0_41608639_LocalLynk'; // Update with your actual database name
$username = 'if0_41608639';  // Update with your database username
$password = 'LOCALLYNKBSIT1A';  // Update with your database password

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    // Fallback to file-based storage if database fails
    $pdo = null;
}

// Helper function for file-based storage fallback
function getStorageData($filename) {
    $file = __DIR__ . '/data/' . $filename;
    if (!file_exists($file)) {
        $dir = dirname($file);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        return [];
    }
    $data = file_get_contents($file);
    return $data ? json_decode($data, true) : [];
}

function saveStorageData($filename, $data) {
    $file = __DIR__ . '/data/' . $filename;
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}

// API endpoint handling
$action = $_GET['action'] ?? '';

switch($action) {
    case 'register':
        handleRegister();
        break;
    case 'login':
        handleLogin();
        break;
    case 'saveProfile':
        handleSaveProfile();
        break;
    case 'getUsers':
        handleGetUsers();
        break;
    case 'saveLocation':
        handleSaveLocation();
        break;
    case 'getNearbyUsers':
        handleGetNearbyUsers();
        break;
    case 'sendRing':
        handleSendRing();
        break;
    case 'getRings':
        handleGetRings();
        break;
    case 'sendMessage':
        handleSendMessage();
        break;
    case 'getMessages':
        handleGetMessages();
        break;
    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}

function handleRegister() {
    global $pdo;
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $email = $_POST['email'] ?? '';
    
    if (!$username || !$password || !$email) {
        echo json_encode(['error' => 'All fields required']);
        return;
    }
    
    // Check if user exists
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            echo json_encode(['error' => 'Username already exists']);
            return;
        }
        
        // Insert new user
        $stmt = $pdo->prepare("INSERT INTO users (username, password, email, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$username, password_hash($password, PASSWORD_DEFAULT), $email]);
        $userId = $pdo->lastInsertId();
        
        echo json_encode(['success' => true, 'user_id' => $userId]);
    } else {
        // Fallback to file storage
        $users = getStorageData('users.json');
        foreach ($users as $user) {
            if ($user['username'] === $username) {
                echo json_encode(['error' => 'Username already exists']);
                return;
            }
        }
        
        $newUser = [
            'id' => uniqid(),
            'username' => $username,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'email' => $email,
            'created_at' => date('Y-m-d H:i:s'),
            'completed' => false,
            'profile' => null
        ];
        
        $users[] = $newUser;
        saveStorageData('users.json', $users);
        echo json_encode(['success' => true, 'user_id' => $newUser['id']]);
    }
}

function handleLogin() {
    global $pdo;
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (!$username || !$password) {
        echo json_encode(['error' => 'Username and password required']);
        return;
    }
    
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user && password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            echo json_encode(['success' => true, 'user' => $user]);
        } else {
            echo json_encode(['error' => 'Invalid credentials']);
        }
    } else {
        // Fallback to file storage
        $users = getStorageData('users.json');
        $userFound = null;
        
        foreach ($users as $user) {
            if ($user['username'] === $username && password_verify($password, $user['password'])) {
                $userFound = $user;
                break;
            }
        }
        
        if ($userFound) {
            $_SESSION['user_id'] = $userFound['id'];
            $_SESSION['username'] = $userFound['username'];
            echo json_encode(['success' => true, 'user' => $userFound]);
        } else {
            echo json_encode(['error' => 'Invalid credentials']);
        }
    }
}

function handleSaveProfile() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $name = $_POST['name'] ?? '';
    $age = $_POST['age'] ?? '';
    $hobbies = $_POST['hobbies'] ?? '';
    $bio = $_POST['bio'] ?? '';
    $picture = $_POST['picture'] ?? '';
    
    if (!$name || !$age || !$hobbies) {
        echo json_encode(['error' => 'Name, age, and hobbies required']);
        return;
    }
    
    $profileData = [
        'name' => $name,
        'age' => intval($age),
        'hobbies' => $hobbies,
        'bio' => $bio ?: 'Ready to connect!',
        'picture' => $picture ?: 'https://cdn-icons-png.flaticon.com/512/3135/3135715.png'
    ];
    
    if ($pdo) {
        $stmt = $pdo->prepare("UPDATE users SET profile = ?, completed = 1 WHERE id = ?");
        $stmt->execute([json_encode($profileData), $_SESSION['user_id']]);
        echo json_encode(['success' => true]);
    } else {
        // Fallback to file storage
        $users = getStorageData('users.json');
        foreach ($users as &$user) {
            if ($user['id'] === $_SESSION['user_id']) {
                $user['profile'] = $profileData;
                $user['completed'] = true;
                break;
            }
        }
        saveStorageData('users.json', $users);
        echo json_encode(['success' => true]);
    }
}

function handleGetUsers() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    if ($pdo) {
        $stmt = $pdo->prepare("SELECT id, username, profile, location, completed FROM users WHERE id != ? AND completed = 1");
        $stmt->execute([$_SESSION['user_id']]);
        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'users' => $users]);
    } else {
        // Fallback to file storage
        $users = getStorageData('users.json');
        $filteredUsers = array_filter($users, function($user) {
            return $user['id'] !== $_SESSION['user_id'] && $user['completed'] === true;
        });
        echo json_encode(['success' => true, 'users' => array_values($filteredUsers)]);
    }
}

function handleSaveLocation() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $lat = $_POST['lat'] ?? '';
    $lng = $_POST['lng'] ?? '';
    
    if (!$lat || !$lng) {
        echo json_encode(['error' => 'Latitude and longitude required']);
        return;
    }
    
    $location = ['lat' => floatval($lat), 'lng' => floatval($lng)];
    
    if ($pdo) {
        $stmt = $pdo->prepare("UPDATE users SET location = ? WHERE id = ?");
        $stmt->execute([json_encode($location), $_SESSION['user_id']]);
        echo json_encode(['success' => true]);
    } else {
        // Fallback to file storage
        $users = getStorageData('users.json');
        foreach ($users as &$user) {
            if ($user['id'] === $_SESSION['user_id']) {
                $user['location'] = $location;
                break;
            }
        }
        saveStorageData('users.json', $users);
        echo json_encode(['success' => true]);
    }
}

function handleGetNearbyUsers() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $userLat = $_POST['lat'] ?? '';
    $userLng = $_POST['lng'] ?? '';
    $radius = $_POST['radius'] ?? 100; // Default 100km
    
    if (!$userLat || !$userLng) {
        echo json_encode(['error' => 'User location required']);
        return;
    }
    
    if ($pdo) {
        // Using Haversine formula in SQL
        $stmt = $pdo->prepare("
            SELECT id, username, profile, location,
                   (6371 * acos(cos(radians(?)) * cos(radians(JSON_EXTRACT(location, '$.lat'))) * 
                   cos(radians(JSON_EXTRACT(location, '$.lng')) - radians(?)) + 
                   sin(radians(?)) * sin(radians(JSON_EXTRACT(location, '$.lat'))))) AS distance
            FROM users 
            WHERE id != ? AND completed = 1 AND location IS NOT NULL
            HAVING distance < ?
            ORDER BY distance
        ");
        $stmt->execute([$userLat, $userLng, $userLat, $_SESSION['user_id'], $radius]);
        $nearbyUsers = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'users' => $nearbyUsers]);
    } else {
        // Fallback to file storage
        $users = getStorageData('users.json');
        $nearbyUsers = [];
        
        foreach ($users as $user) {
            if ($user['id'] !== $_SESSION['user_id'] && $user['completed'] === true && isset($user['location'])) {
                $distance = calculateDistance($userLat, $userLng, $user['location']['lat'], $user['location']['lng']);
                if ($distance <= $radius) {
                    $user['distance'] = $distance;
                    $nearbyUsers[] = $user;
                }
            }
        }
        
        // Sort by distance
        usort($nearbyUsers, function($a, $b) {
            return $a['distance'] <=> $b['distance'];
        });
        
        echo json_encode(['success' => true, 'users' => $nearbyUsers]);
    }
}

function handleSendRing() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $targetUserId = $_POST['target_user_id'] ?? '';
    
    if (!$targetUserId) {
        echo json_encode(['error' => 'Target user ID required']);
        return;
    }
    
    $ringData = [
        'from_user_id' => $_SESSION['user_id'],
        'to_user_id' => $targetUserId,
        'created_at' => date('Y-m-d H:i:s'),
        'status' => 'pending'
    ];
    
    if ($pdo) {
        $stmt = $pdo->prepare("INSERT INTO rings (from_user_id, to_user_id, created_at, status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$ringData['from_user_id'], $ringData['to_user_id'], $ringData['created_at'], $ringData['status']]);
        echo json_encode(['success' => true]);
    } else {
        // Fallback to file storage
        $rings = getStorageData('rings.json');
        $ringData['id'] = uniqid();
        $rings[] = $ringData;
        saveStorageData('rings.json', $rings);
        echo json_encode(['success' => true]);
    }
}

function handleGetRings() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    if ($pdo) {
        $stmt = $pdo->prepare("
            SELECT r.*, u.username as from_username, up.profile as from_profile
            FROM rings r
            JOIN users u ON r.from_user_id = u.id
            LEFT JOIN users up ON r.from_user_id = up.id
            WHERE r.to_user_id = ? AND r.status = 'pending'
            ORDER BY r.created_at DESC
        ");
        $stmt->execute([$_SESSION['user_id']]);
        $rings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'rings' => $rings]);
    } else {
        // Fallback to file storage
        $rings = getStorageData('rings.json');
        $users = getStorageData('users.json');
        $pendingRings = [];
        
        foreach ($rings as $ring) {
            if ($ring['to_user_id'] === $_SESSION['user_id'] && $ring['status'] === 'pending') {
                // Find user details
                foreach ($users as $user) {
                    if ($user['id'] === $ring['from_user_id']) {
                        $ring['from_username'] = $user['username'];
                        $ring['from_profile'] = $user['profile'];
                        $pendingRings[] = $ring;
                        break;
                    }
                }
            }
        }
        
        echo json_encode(['success' => true, 'rings' => $pendingRings]);
    }
}

function handleSendMessage() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $targetUserId = $_POST['target_user_id'] ?? '';
    $message = $_POST['message'] ?? '';
    
    if (!$targetUserId || !$message) {
        echo json_encode(['error' => 'Target user ID and message required']);
        return;
    }
    
    $messageData = [
        'from_user_id' => $_SESSION['user_id'],
        'to_user_id' => $targetUserId,
        'message' => $message,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    if ($pdo) {
        $stmt = $pdo->prepare("INSERT INTO messages (from_user_id, to_user_id, message, created_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([$messageData['from_user_id'], $messageData['to_user_id'], $messageData['message'], $messageData['created_at']]);
        echo json_encode(['success' => true]);
    } else {
        // Fallback to file storage
        $messages = getStorageData('messages.json');
        $messageData['id'] = uniqid();
        $messages[] = $messageData;
        saveStorageData('messages.json', $messages);
        echo json_encode(['success' => true]);
    }
}

function handleGetMessages() {
    global $pdo;
    
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['error' => 'Not logged in']);
        return;
    }
    
    $targetUserId = $_GET['target_user_id'] ?? '';
    
    if (!$targetUserId) {
        echo json_encode(['error' => 'Target user ID required']);
        return;
    }
    
    if ($pdo) {
        $stmt = $pdo->prepare("
            SELECT m.*, u.username as from_username
            FROM messages m
            JOIN users u ON m.from_user_id = u.id
            WHERE (m.from_user_id = ? AND m.to_user_id = ?) OR (m.from_user_id = ? AND m.to_user_id = ?)
            ORDER BY m.created_at ASC
        ");
        $stmt->execute([$_SESSION['user_id'], $targetUserId, $targetUserId, $_SESSION['user_id']]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode(['success' => true, 'messages' => $messages]);
    } else {
        // Fallback to file storage
        $messages = getStorageData('messages.json');
        $users = getStorageData('users.json');
        $threadMessages = [];
        
        foreach ($messages as $message) {
            if (($message['from_user_id'] === $_SESSION['user_id'] && $message['to_user_id'] === $targetUserId) ||
                ($message['from_user_id'] === $targetUserId && $message['to_user_id'] === $_SESSION['user_id'])) {
                // Add username
                foreach ($users as $user) {
                    if ($user['id'] === $message['from_user_id']) {
                        $message['from_username'] = $user['username'];
                        break;
                    }
                }
                $threadMessages[] = $message;
            }
        }
        
        // Sort by date
        usort($threadMessages, function($a, $b) {
            return strtotime($a['created_at']) <=> strtotime($b['created_at']);
        });
        
        echo json_encode(['success' => true, 'messages' => $threadMessages]);
    }
}

// Helper function for distance calculation (Haversine formula)
function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    $R = 6371; // Earth's radius in km
    $dLat = ($lat2 - $lat1) * M_PI / 180;
    $dLon = ($lon2 - $lon1) * M_PI / 180;
    $a = sin($dLat/2) * sin($dLat/2) +
         cos($lat1 * M_PI / 180) * cos($lat2 * M_PI / 180) *
         sin($dLon/2) * sin($dLon/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return $R * $c; // Distance in km
}
?>
