<?php
require_once __DIR__ . '/../config/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../models/User.php';
require_once __DIR__ . '/../includes/ActivityLogger.php';

// Check if user is logged in BEFORE including header
if (!isset($_SESSION['user_id'])) {
    header('Location: ' . SITE_URL . 'google-auth.php?action=login');
    exit();
}

require_once __DIR__ . '/../includes/header.php';

$userId = $_SESSION['user_id'];
$user = new User();
$currentUser = $user->getCurrentUser();

// Handle profile update
$updateMessage = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['upload_avatar'])) {
        $uploadError = '';
        $avatarFile = $_FILES['avatar'] ?? null;
        $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

        if (!$avatarFile || $avatarFile['error'] !== UPLOAD_ERR_OK) {
            $uploadError = 'Please choose an image to upload.';
        } elseif ($avatarFile['size'] > 5 * 1024 * 1024) {
            $uploadError = 'Profile images must be 5 MB or smaller.';
        } else {
            $imageInfo = @getimagesize($avatarFile['tmp_name']);
            $mimeType = $imageInfo['mime'] ?? '';
            if (!$imageInfo || !in_array($mimeType, $allowedMimeTypes, true)) {
                $uploadError = 'Please upload a valid JPG, PNG, WEBP, or GIF image.';
            }
        }

        if ($uploadError === '') {
            $uploadDirectory = __DIR__ . '/../uploads/profile/';
            if (!is_dir($uploadDirectory)) {
                mkdir($uploadDirectory, 0755, true);
            }

            $extension = strtolower(pathinfo($avatarFile['name'], PATHINFO_EXTENSION));
            $fileName = 'avatar_' . $userId . '_' . bin2hex(random_bytes(8)) . '.' . $extension;
            $destination = $uploadDirectory . $fileName;
            if (move_uploaded_file($avatarFile['tmp_name'], $destination)) {
                $avatarUrl = SITE_URL . 'uploads/profile/' . $fileName;
                $result = $user->updateAvatar($userId, $avatarUrl);
                if ($result['success']) {
                    $activityDatabase = new Database();
                    logUserActivity($activityDatabase->getConnection(), $userId, 'avatar_updated', 'Updated profile image');
                }
                $updateMessage = $result['success']
                    ? '<div style="background-color: #d1fae5; color: #065f46; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;"><i class="fas fa-check-circle"></i> ' . htmlspecialchars($result['message']) . '</div>'
                    : '<div style="background-color: #fee2e2; color: #7f1d1d; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($result['message']) . '</div>';
                $currentUser = $user->getCurrentUser();
            } else {
                $uploadError = 'Unable to save the uploaded image.';
            }
        }

        if ($uploadError !== '') {
            $updateMessage = '<div style="background-color: #fee2e2; color: #7f1d1d; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($uploadError) . '</div>';
        }
    } else {
    $fullname = $_POST['fullname'] ?? '';
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'] ?? '';

    // Use the existing email from database since it's readonly
    $email = $currentUser['email'] ?? '';

    if (!empty($fullname) && !empty($email) && !empty($phone)) {
        $result = $user->updateProfile($userId, $fullname, $email, $phone);
        if ($result['success']) {
            $activityDatabase = new Database();
            logUserActivity($activityDatabase->getConnection(), $userId, 'profile_updated', 'Updated profile information');
            $updateMessage = '<div style="background-color: #d1fae5; color: #065f46; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;"><i class="fas fa-check-circle"></i> Profile updated successfully!</div>';
            // Refresh current user data
            $currentUser = $user->getCurrentUser();
        } else {
            $updateMessage = '<div style="background-color: #fee2e2; color: #7f1d1d; padding: 1rem; border-radius: 5px; margin-bottom: 1rem;"><i class="fas fa-exclamation-circle"></i> ' . htmlspecialchars($result['message']) . '</div>';
        }
    }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Villa Soledad</title>
    <link rel="stylesheet" href="<?php echo SITE_URL; ?>css/style.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: #f3f4f6;
        }

        .profile-page {
            padding: 2rem 1rem 4rem;
            max-width: 800px;
            margin: 0 auto;
        }

        .profile-header {
            display: flex;
            align-items: center;
            gap: 2rem;
            margin-bottom: 3rem;
            background: white;
            padding: 2rem;
            border-radius: 1.5rem;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        }

        .profile-avatar {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #102a43, #ff7a3d);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            flex-shrink: 0;
            overflow: hidden;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-upload {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            flex-shrink: 0;
        }

        .avatar-upload-button {
            border: 0;
            border-radius: 999px;
            padding: 0.55rem 0.85rem;
            background: #102a43;
            color: #fff;
            font-size: 0.8rem;
            font-weight: 700;
            cursor: pointer;
        }

        .profile-info h1 {
            font-size: 2rem;
            color: #102a43;
            margin: 0 0 0.5rem 0;
        }

        .profile-info p {
            color: #64748b;
            margin: 0.25rem 0;
        }

        .profile-form {
            background: white;
            padding: 2rem;
            border-radius: 1.5rem;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.08);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #102a43;
            font-weight: 700;
            font-size: 0.95rem;
        }

        .form-group input {
            width: 100%;
            padding: 1rem;
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-sizing: border-box;
        }

        .form-group input:focus {
            outline: none;
            border-color: #ff7a3d;
            box-shadow: 0 0 0 3px rgba(255, 122, 61, 0.1);
        }

        .form-group input[readonly] {
            background: #f8fafc;
            cursor: not-allowed;
            color: #475569;
            border-color: #e2e8f0;
        }

        .form-group input[readonly]:focus {
            border-color: #e2e8f0;
            box-shadow: none;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn-primary {
            background: #ff7a3d;
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 999px;
            cursor: pointer;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #ff6b1f;
        }

        .btn-secondary {
            background: #e2e8f0;
            color: #102a43;
            border: none;
            padding: 1rem 2rem;
            border-radius: 999px;
            cursor: pointer;
            font-weight: 700;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .btn-secondary:hover {
            background: #cbd5e1;
        }

        @media (max-width: 768px) {
            .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .profile-info h1 {
                font-size: 1.5rem;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn-primary, .btn-secondary {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <main class="profile-page">
        <div class="profile-header">
            <div class="avatar-upload">
                <div class="profile-avatar">
                    <?php if (!empty($currentUser['avatar'])): ?>
                        <img src="<?php echo htmlspecialchars($currentUser['avatar']); ?>" alt="Profile photo">
                    <?php else: ?>
                        <i class="fas fa-user"></i>
                    <?php endif; ?>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="upload_avatar" value="1">
                    <input type="file" name="avatar" id="avatarUpload" accept="image/jpeg,image/png,image/webp,image/gif" hidden required>
                    <label for="avatarUpload" class="avatar-upload-button"><i class="fas fa-camera"></i> Upload Profile</label>
                    <button type="submit" id="avatarSubmit" hidden>Upload</button>
                </form>
            </div>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($currentUser['fullname'] ?? 'User'); ?></h1>
                <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($currentUser['email'] ?? 'N/A'); ?></p>
                <p><i class="fas fa-phone"></i> <?php echo htmlspecialchars($currentUser['phone'] ?? 'N/A'); ?></p>
            </div>
        </div>

        <div class="profile-form">
            <h2 style="color: #102a43; margin-top: 0;">Edit Profile</h2>
            
            <?php echo $updateMessage; ?>

            <form method="POST">
                <div class="form-group">
                    <label for="fullname">Full Name</label>
                    <input 
                        type="text" 
                        id="fullname" 
                        name="fullname" 
                        value="<?php echo htmlspecialchars($currentUser['fullname'] ?? ''); ?>" 
                        required
                    >
                </div>

                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        value="<?php echo htmlspecialchars($currentUser['email'] ?? ''); ?>" 
                        required
                        readonly
                    >
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number</label>
                    <input 
                        type="tel" 
                        id="phone" 
                        name="phone" 
                        value="<?php echo htmlspecialchars($currentUser['phone'] ?? ''); ?>" 
                        required
                    >
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary">Save Changes</button>
                    <button type="button" class="btn-secondary" onclick="window.history.back()">Cancel</button>
                </div>
            </form>
        </div>
    </main>
    <script>
        document.getElementById('avatarUpload')?.addEventListener('change', function() {
            if (this.files.length > 0) {
                this.form.submit();
            }
        });
    </script>
</body>
</html>
