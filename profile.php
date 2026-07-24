<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
$pageTitle = 'Profile';

$message = '';
$error = '';
$user_id = (int)($_SESSION['user_id'] ?? 0);

$stmt = $pdo->prepare("SELECT id, username, password, full_name, phone, id_number, email, role, profile_photo, created_at FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_post_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_profile') {
        $username = trim($_POST['username'] ?? '');
        $full_name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $id_number = trim($_POST['id_number'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if ($username === '' || $full_name === '' || $phone === '' || $id_number === '' || $email === '') {
            $error = 'Full name, username, phone number, ID number, and email are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } else {
            $stmt = $pdo->prepare("SELECT username, email, id_number FROM users WHERE (username = ? OR email = ? OR id_number = ?) AND id <> ? LIMIT 1");
            $stmt->execute([$username, $email, $id_number, $user_id]);
            $existing_user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($existing_user && $existing_user['username'] === $username) {
                $error = 'That username is already in use.';
            } elseif ($existing_user && $existing_user['email'] === $email) {
                $error = 'That email is already in use.';
            } elseif ($existing_user && $existing_user['id_number'] === $id_number) {
                $error = 'That ID number is already in use.';
            } else {
                $stmt = $pdo->prepare("UPDATE users SET username = ?, full_name = ?, phone = ?, id_number = ?, email = ? WHERE id = ?");
                $stmt->execute([$username, $full_name, $phone, $id_number, $email, $user_id]);

                $_SESSION['username'] = $username;
                $_SESSION['full_name'] = $full_name;
                $message = 'Profile updated successfully.';
            }
        }
    }

    if ($action === 'change_password') {
        $current_password = $_POST['current_password'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        $confirm_password = $_POST['confirm_password'] ?? '';

        if ($current_password === '' || $new_password === '' || $confirm_password === '') {
            $error = 'All password fields are required.';
        } elseif (!password_verify($current_password, $user['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new_password) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $new_password)) {
            $error = 'New password must include at least one uppercase letter.';
        } elseif (!preg_match('/[a-z]/', $new_password)) {
            $error = 'New password must include at least one lowercase letter.';
        } elseif (!preg_match('/\d/', $new_password)) {
            $error = 'New password must include at least one number.';
        } elseif (!preg_match('/[^a-zA-Z\d]/', $new_password)) {
            $error = 'New password must include at least one special character.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New password and confirmation do not match.';
        } else {
            $hash = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
            $stmt->execute([$hash, $user_id]);
            $message = 'Password changed successfully.';
        }
    }

    if ($action === 'upload_photo') {
        $photo = $_FILES['profile_photo'] ?? null;
        $allowed_types = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if (!$photo || ($photo['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            $error = 'Choose a profile photo to upload.';
        } elseif (($photo['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            $error = 'Photo upload failed. Please try again.';
        } elseif (($photo['size'] ?? 0) > 2 * 1024 * 1024) {
            $error = 'Profile photo must be 2 MB or smaller.';
        } else {
            $image_info = @getimagesize($photo['tmp_name']);
            $mime = $image_info['mime'] ?? '';

            if (!isset($allowed_types[$mime])) {
                $error = 'Profile photo must be a JPG, PNG, WEBP, or GIF image.';
            } else {
                $upload_dir = __DIR__ . '/assets/profile_photos';
                if (!is_dir($upload_dir)) {
                    mkdir($upload_dir, 0755, true);
                }
                $deny_file = $upload_dir . '/.htaccess';
                if (!is_file($deny_file)) {
                    @file_put_contents($deny_file, "Options -Indexes\nphp_flag engine off\n<FilesMatch \"\\.(php|phtml|phar)$\">\n    Require all denied\n</FilesMatch>\n");
                }

                $filename = 'user_' . $user_id . '_' . bin2hex(random_bytes(8)) . '.' . $allowed_types[$mime];
                $relative_path = 'assets/profile_photos/' . $filename;
                $target_path = $upload_dir . '/' . $filename;

                try {
                    if (!move_uploaded_file($photo['tmp_name'], $target_path)) {
                        throw new RuntimeException('The photo could not be saved. Please try again.');
                    }

                    if (!empty($user['profile_photo'])) {
                        $old_path = __DIR__ . '/' . $user['profile_photo'];
                        $old_dir = realpath(dirname($old_path));
                        $profile_dir = realpath($upload_dir);
                        if ($old_dir === $profile_dir && is_file($old_path)) {
                            @unlink($old_path);
                        }
                    }

                    $stmt = $pdo->prepare("UPDATE users SET profile_photo = ? WHERE id = ?");
                    $stmt->execute([$relative_path, $user_id]);
                    $_SESSION['profile_photo'] = $relative_path;
                    $message = 'Profile photo updated successfully.';
                } catch (Throwable $e) {
                    $error = app_exception_message($e, 'The photo could not be uploaded right now. Please try again.');
                }
            }
        }
    }

    if ($action === 'remove_photo') {
        if (!empty($user['profile_photo'])) {
            $photo_path = __DIR__ . '/' . $user['profile_photo'];
            $profile_dir = realpath(__DIR__ . '/assets/profile_photos');
            $photo_dir = realpath(dirname($photo_path));
            if ($photo_dir === $profile_dir && is_file($photo_path)) {
                unlink($photo_path);
            }
        }

        $stmt = $pdo->prepare("UPDATE users SET profile_photo = NULL WHERE id = ?");
        $stmt->execute([$user_id]);
        unset($_SESSION['profile_photo']);
        $message = 'Profile photo removed successfully.';
    }

    $stmt = $pdo->prepare("SELECT id, username, password, full_name, phone, id_number, email, role, profile_photo, created_at FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
}

$_SESSION['profile_photo'] = $user['profile_photo'] ?? null;

include 'includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h2 class="mb-0">Profile Settings</h2>
    <span class="badge text-bg-secondary"><?= htmlspecialchars(ucfirst($user['role'])) ?></span>
</div>

<?php if ($message): ?>
    <div class="alert alert-success"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<div class="row g-4">
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header">Profile Photo</div>
            <div class="card-body">
                <div class="d-flex align-items-center gap-3 mb-3">
                    <?php if (!empty($user['profile_photo'])): ?>
                        <img src="<?= htmlspecialchars($user['profile_photo']) ?>" class="rounded-circle object-fit-cover border" width="96" height="96" alt="Profile photo">
                    <?php else: ?>
                        <div class="rounded-circle bg-secondary-subtle text-secondary d-flex align-items-center justify-content-center border" style="width: 96px; height: 96px;">
                            <i class="bi bi-person-fill fs-1"></i>
                        </div>
                    <?php endif; ?>
                    <div>
                        <div class="fw-semibold"><?= htmlspecialchars($user['full_name']) ?></div>
                        <div class="text-muted small"><?= htmlspecialchars($user['username']) ?></div>
                    </div>
                </div>
                <form method="POST" class="needs-validation" novalidate enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="upload_photo">
                    <div class="mb-3">
                        <input type="file" name="profile_photo" class="form-control" accept="image/jpeg,image/png,image/webp,image/gif" required>
                        <div class="invalid-feedback">Choose a profile photo to upload.</div>
                        <div class="form-text">JPG, PNG, WEBP, or GIF. Max 2 MB.</div>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-upload"></i> Upload Photo
                    </button>
                </form>
                <?php if (!empty($user['profile_photo'])): ?>
                    <form method="POST" class="mt-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="remove_photo">
                        <button type="submit" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-trash"></i> Remove Photo
                        </button>
                    </form>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Account Details</div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="update_profile">
                    <div class="mb-3">
                        <label class="form-label" for="full_name">Full Name</label>
                        <input type="text" name="full_name" id="full_name" class="form-control" value="<?= htmlspecialchars($user['full_name']) ?>" required>
                        <div class="invalid-feedback">Full name is required.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="username">Username</label>
                        <input type="text" name="username" id="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" required>
                        <div class="invalid-feedback">Username is required.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="phone">Phone Number</label>
                        <input type="tel" name="phone" id="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
                        <div class="invalid-feedback">Phone number is required.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="id_number">ID Number</label>
                        <input type="text" name="id_number" id="id_number" class="form-control" value="<?= htmlspecialchars($user['id_number'] ?? '') ?>" required>
                        <div class="invalid-feedback">ID number is required.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input type="email" name="email" id="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" autocomplete="email" required>
                        <div class="invalid-feedback">A valid email is required.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars(ucfirst($user['role'])) ?>" disabled>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Member Since</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars(date('d M Y', strtotime($user['created_at']))) ?>" disabled>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-save"></i> Save Changes
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-lg-7">
        <div class="card">
            <div class="card-header">Change Password</div>
            <div class="card-body">
                <form method="POST" class="needs-validation" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_password">
                    <div class="mb-3">
                        <label class="form-label" for="current_password">Current Password</label>
                        <div class="input-group">
                            <input type="password" name="current_password" id="current_password" class="form-control password-input" data-hint-id="currentPasswordHint" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="current_password" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">Current password is required.</div>
                        <div class="form-text text-muted mt-1 password-hint" id="currentPasswordHint" style="display:none;">Enter your current password.</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="new_password">New Password</label>
                        <div class="input-group">
                            <input type="password" name="new_password" id="new_password" class="form-control password-input" data-hint-id="newPasswordHint" minlength="8" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="new_password" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">New password is required.</div>
                        <div class="form-text text-muted mt-1 password-hint" id="newPasswordHint" style="display:none;">Use at least 8 characters, including uppercase, lowercase, a number, and a special character.</div>
                        <div class="form-text text-muted mt-1" id="newPasswordRules" style="display:none;">
                            <div class="small">Password requirements:</div>
                            <ul class="small mb-0">
                                <li id="ruleLength">At least 8 characters</li>
                                <li id="ruleUpper">Uppercase letter</li>
                                <li id="ruleLower">Lowercase letter</li>
                                <li id="ruleNumber">Number</li>
                                <li id="ruleSpecial">Special character</li>
                            </ul>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="confirm_password">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" name="confirm_password" id="confirm_password" class="form-control password-input" data-hint-id="confirmNewPasswordHint" minlength="8" required>
                            <button class="btn btn-outline-secondary toggle-password" type="button" data-target="confirm_password" aria-label="Show password">
                                <i class="bi bi-eye"></i>
                            </button>
                        </div>
                        <div class="invalid-feedback">Password confirmation is required.</div>
                        <div class="form-text text-muted mt-1 password-hint" id="confirmNewPasswordHint" style="display:none;">Re-enter your new password to confirm it.</div>
                        <div class="form-text mt-1" id="confirmPasswordMatch" style="display:none;"></div>
                    </div>
                    <button type="submit" class="btn btn-warning">
                        <i class="bi bi-shield-lock"></i> Update Password
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.querySelectorAll('.password-input').forEach(input => {
    const hint = document.getElementById(input.dataset.hintId);
    if (!hint) {
        return;
    }

    const showHint = () => {
        hint.style.display = 'block';
    };
    const hideHint = () => {
        hint.style.display = 'none';
    };

    input.addEventListener('focus', showHint);
    input.addEventListener('click', showHint);
    input.addEventListener('blur', hideHint);
});

const newPassword = document.getElementById('new_password');
const confirmPassword = document.getElementById('confirm_password');
const confirmPasswordMatch = document.getElementById('confirmPasswordMatch');
const ruleLength = document.getElementById('ruleLength');
const ruleUpper = document.getElementById('ruleUpper');
const ruleLower = document.getElementById('ruleLower');
const ruleNumber = document.getElementById('ruleNumber');
const ruleSpecial = document.getElementById('ruleSpecial');
const newPasswordRules = document.getElementById('newPasswordRules');

const updatePasswordRules = () => {
    const value = newPassword.value;
    ruleLength.classList.toggle('text-success', value.length >= 8);
    ruleUpper.classList.toggle('text-success', /[A-Z]/.test(value));
    ruleLower.classList.toggle('text-success', /[a-z]/.test(value));
    ruleNumber.classList.toggle('text-success', /\d/.test(value));
    ruleSpecial.classList.toggle('text-success', /[^a-zA-Z\d]/.test(value));
};

const updatePasswordMatch = () => {
    if (!confirmPassword.value) {
        confirmPasswordMatch.style.display = 'none';
        return;
    }

    confirmPasswordMatch.style.display = 'block';
    if (newPassword.value === confirmPassword.value) {
        confirmPasswordMatch.textContent = 'Passwords match.';
        confirmPasswordMatch.className = 'form-text text-success mt-1';
    } else {
        confirmPasswordMatch.textContent = 'Passwords do not match.';
        confirmPasswordMatch.className = 'form-text text-danger mt-1';
    }
};

if (newPassword && confirmPassword) {
    newPassword.addEventListener('focus', () => {
        newPasswordRules.style.display = 'block';
    });
    newPassword.addEventListener('input', () => {
        updatePasswordRules();
        updatePasswordMatch();
    });
    newPassword.addEventListener('blur', () => {
        newPasswordRules.style.display = 'none';
    });

    confirmPassword.addEventListener('input', updatePasswordMatch);
}

document.querySelectorAll('.toggle-password').forEach(button => {
    button.addEventListener('click', () => {
        const input = document.getElementById(button.dataset.target);
        const icon = button.querySelector('i');
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        icon.className = show ? 'bi bi-eye-slash' : 'bi bi-eye';
        button.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
    });
});
</script>
<?php include 'includes/footer.php'; ?>
