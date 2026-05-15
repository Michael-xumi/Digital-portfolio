<?php
// admin_upload.php - Admin file upload, permission, version, and comment management
session_start();
require 'db.php';

// CSRF Protection
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Function to get category path
function getCategoryPath($pdo, $category_id, $path = []) {
    $stmt = $pdo->prepare("SELECT id, name, parent_id FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $cat = $stmt->fetch();
    if ($cat) {
        array_unshift($path, $cat['name']);
        if ($cat['parent_id']) {
            return getCategoryPath($pdo, $cat['parent_id'], $path);
        }
    }
    return $path;
}

// Check if admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'Administrator') {
    header('Location: login.php');
    exit;
}

$message = '';
$message_type = 'success';

// Security validation function for files
function validateFile($file) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return "File upload error: " . $file['error'];
    }
    
    $max_size = 10 * 1024 * 1024;
    if ($file['size'] > $max_size) {
        return "File size exceeds 10MB limit.";
    }

    $allowed_exts = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'zip'];
    $allowed_mimes = [
        'application/pdf', 'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'image/jpeg', 'image/png', 'image/gif', 'text/plain', 'application/zip'
    ];

    $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    if (!in_array($file_ext, $allowed_exts) || !in_array($mime_type, $allowed_mimes)) {
        return "Invalid or dangerous file type detected.";
    }

    return true; // Valid
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $message = "CSRF Token Validation Failed.";
        $message_type = 'error';
    } else {
        if (isset($_POST['upload'])) {
            // New File Upload
            $title = trim($_POST['title']);
            $description = trim($_POST['description']);
            $category_id = $_POST['category_id'];
            $allowed_visitors = $_POST['visitors'] ?? [];

            if (empty($title) || empty($_FILES['file']['name']) || empty($category_id)) {
                $message = 'Title, File, and Category are required.';
                $message_type = 'error';
            } else {
                $validation = validateFile($_FILES['file']);
                if ($validation !== true) {
                    $message = $validation;
                    $message_type = 'error';
                } else {
                    $pdo->beginTransaction();
                    try {
                        // Insert file metadata
                        $stmt = $pdo->prepare("INSERT INTO files (title, description, category_id, uploaded_by, status, is_active) VALUES (?, ?, ?, ?, 'Submitted', 1)");
                        $stmt->execute([$title, $description, $category_id, $_SESSION['user_id']]);
                        $file_id = $pdo->lastInsertId();

                        // Directory logic
                        $upload_dir = 'uploads/file_' . $file_id . '/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0755, true);
                        }

                        $file_ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
                        $file_path = $upload_dir . 'v1.' . $file_ext;

                        if (move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
                            // Insert version
                            $stmt_v = $pdo->prepare("INSERT INTO file_versions (file_id, version_number, file_path, uploaded_by) VALUES (?, 1, ?, ?)");
                            $stmt_v->execute([$file_id, $file_path, $_SESSION['user_id']]);
                            $version_id = $pdo->lastInsertId();

                            // Update file with current version
                            $pdo->prepare("UPDATE files SET current_version_id = ? WHERE id = ?")->execute([$version_id, $file_id]);

                            if (!empty($allowed_visitors)) {
                                $stmt_perm = $pdo->prepare("INSERT INTO permissions (user_id, file_id, permission_level) VALUES (?, ?, 'read')");
                                foreach ($allowed_visitors as $visitor_id) {
                                    $stmt_perm->execute([$visitor_id, $file_id]);
                                }
                            }
                            $pdo->commit();
                            $message = "File uploaded successfully!";
                            $message_type = 'success';
                        } else {
                            throw new Exception("Failed to move uploaded file.");
                        }
                    } catch (Exception $e) {
                        $pdo->rollBack();
                        $message = 'Error: ' . $e->getMessage();
                        $message_type = 'error';
                    }
                }
            }
        } elseif (isset($_POST['edit'])) {
            // Edit File and Optional New Version
            $file_id = $_POST['file_id'];
            $title = trim($_POST['edit_title']);
            $description = trim($_POST['edit_description']);
            $allowed_ids = $_POST['edit_visitors'] ?? [];

            $pdo->beginTransaction();
            try {
                // Validate ownership
                $stmt = $pdo->prepare("SELECT id FROM files WHERE id = ? AND uploaded_by = ?");
                $stmt->execute([$file_id, $_SESSION['user_id']]);
                if (!$stmt->fetch()) throw new Exception("Access denied or file not found.");

                $stmt = $pdo->prepare("UPDATE files SET title = ?, description = ? WHERE id = ?");
                $stmt->execute([$title, $description, $file_id]);

                // Handle new version upload
                if (!empty($_FILES['edit_file']['name'])) {
                    $validation = validateFile($_FILES['edit_file']);
                    if ($validation !== true) {
                        throw new Exception($validation);
                    }

                    // Get next version number
                    $stmt_vnum = $pdo->prepare("SELECT COALESCE(MAX(version_number), 0) + 1 FROM file_versions WHERE file_id = ?");
                    $stmt_vnum->execute([$file_id]);
                    $next_version = $stmt_vnum->fetchColumn();

                    $upload_dir = 'uploads/file_' . $file_id . '/';
                    if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

                    $file_ext = strtolower(pathinfo($_FILES['edit_file']['name'], PATHINFO_EXTENSION));
                    $file_path = $upload_dir . 'v' . $next_version . '.' . $file_ext;

                    if (!move_uploaded_file($_FILES['edit_file']['tmp_name'], $file_path)) {
                        throw new Exception("Failed to move uploaded file.");
                    }

                    // Insert version
                    $stmt_v = $pdo->prepare("INSERT INTO file_versions (file_id, version_number, file_path, uploaded_by) VALUES (?, ?, ?, ?)");
                    $stmt_v->execute([$file_id, $next_version, $file_path, $_SESSION['user_id']]);
                    $version_id = $pdo->lastInsertId();

                    // Update current_version_id
                    $pdo->prepare("UPDATE files SET current_version_id = ? WHERE id = ?")->execute([$version_id, $file_id]);
                }

                // Update Permissions
                $pdo->prepare("DELETE FROM permissions WHERE file_id = ?")->execute([$file_id]);
                if (!empty($allowed_ids)) {
                    $stmt_perm = $pdo->prepare("INSERT INTO permissions (file_id, user_id, permission_level) VALUES (?, ?, 'read')");
                    foreach ($allowed_ids as $user_id) {
                        $stmt_perm->execute([$file_id, $user_id]);
                    }
                }

                $pdo->commit();
                $message = 'File updated successfully!';
                $message_type = 'success';
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = 'Error updating file: ' . $e->getMessage();
                $message_type = 'error';
            }
        } elseif (isset($_POST['update_status'])) {
            $file_id = $_POST['file_id'];
            $status = $_POST['status'];
            $is_active = isset($_POST['is_active']) ? 1 : 0;
            
            try {
                $stmt = $pdo->prepare("UPDATE files SET status = ?, is_active = ? WHERE id = ? AND uploaded_by = ?");
                $stmt->execute([$status, $is_active, $file_id, $_SESSION['user_id']]);
                $message = 'File status updated!';
                $message_type = 'success';
            } catch (Exception $e) {
                $message = 'Error updating status: ' . $e->getMessage();
                $message_type = 'error';
            }
        } elseif (isset($_POST['add_comment'])) {
            $file_id = $_POST['file_id'];
            $comment = trim($_POST['comment_text']);
            if (!empty($comment)) {
                try {
                    $stmt = $pdo->prepare("INSERT INTO admin_comments (file_id, admin_id, comment) VALUES (?, ?, ?)");
                    $stmt->execute([$file_id, $_SESSION['user_id'], $comment]);
                } catch (Exception $e) {
                    $message = 'Error adding comment: ' . $e->getMessage();
                    $message_type = 'error';
                }
            }
        } elseif (isset($_POST['close_comment'])) {
            $comment_id = $_POST['comment_id'];
            try {
                // Ensure only admins who own the file or commented can close it
                $stmt = $pdo->prepare("UPDATE admin_comments ac JOIN files f ON ac.file_id = f.id SET ac.status = 'Closed' WHERE ac.id = ? AND (f.uploaded_by = ? OR ac.admin_id = ?)");
                $stmt->execute([$comment_id, $_SESSION['user_id'], $_SESSION['user_id']]);
            } catch (Exception $e) {
                $message = 'Error closing comment: ' . $e->getMessage();
                $message_type = 'error';
            }
        } elseif (isset($_POST['delete'])) {
            $file_id = $_POST['file_id'];
            $pdo->beginTransaction();
            try {
                $stmt = $pdo->prepare("SELECT id FROM files WHERE id = ? AND uploaded_by = ?");
                $stmt->execute([$file_id, $_SESSION['user_id']]);
                if (!$stmt->fetch()) throw new Exception("Access denied.");

                // Get all versions to delete files
                $stmt_v = $pdo->prepare("SELECT file_path FROM file_versions WHERE file_id = ?");
                $stmt_v->execute([$file_id]);
                while ($v = $stmt_v->fetch()) {
                    if (file_exists($v['file_path'])) unlink($v['file_path']);
                }

                // Delete directory
                $dir = 'uploads/file_' . $file_id;
                if (is_dir($dir)) rmdir($dir);

                $pdo->prepare("DELETE FROM files WHERE id = ?")->execute([$file_id]);
                
                $pdo->commit();
                $message = 'File deleted successfully!';
                $message_type = 'success';
            } catch (Exception $e) {
                $pdo->rollBack();
                $message = 'Error deleting file: ' . $e->getMessage();
                $message_type = 'error';
            }
        }
    }
}

// Fetch categories and visitors
$categories = $pdo->query("SELECT * FROM categories ORDER BY id")->fetchAll();
$visitors = $pdo->query("SELECT id, username, email FROM users WHERE role = 'Visitor'")->fetchAll();

// --- Filter Logic ---
$where_clauses = ["f.uploaded_by = ?"];
$params = [$_SESSION['user_id']];

if (!empty($_GET['filter_category'])) {
    $where_clauses[] = "f.category_id = ?";
    $params[] = $_GET['filter_category'];
}
if (!empty($_GET['filter_status'])) {
    $where_clauses[] = "f.status = ?";
    $params[] = $_GET['filter_status'];
}
if (!empty($_GET['filter_comment'])) {
    $where_clauses[] = "EXISTS (SELECT 1 FROM admin_comments ac WHERE ac.file_id = f.id AND ac.status = ?)";
    $params[] = $_GET['filter_comment'];
}

$where_sql = implode(' AND ', $where_clauses);

$files_stmt = $pdo->prepare("
    SELECT f.*, 
           fv.file_path, 
           fv.version_number,
           (SELECT GROUP_CONCAT(u.username) FROM permissions p JOIN users u ON p.user_id = u.id WHERE p.file_id = f.id) as allowed_visitors,
           (SELECT COUNT(*) FROM admin_comments ac WHERE ac.file_id = f.id AND ac.status = 'Open') as open_comments_count
    FROM files f 
    LEFT JOIN file_versions fv ON f.current_version_id = fv.id
    WHERE $where_sql
    ORDER BY f.id DESC
");
$files_stmt->execute($params);
$files = $files_stmt->fetchAll();

// Fetch comments and versions for modals
$all_comments = [];
$all_versions = [];
if (!empty($files)) {
    $file_ids = array_column($files, 'id');
    $placeholders = implode(',', array_fill(0, count($file_ids), '?'));
    
    // Comments
    $c_stmt = $pdo->prepare("SELECT ac.*, u.username FROM admin_comments ac JOIN users u ON ac.admin_id = u.id WHERE ac.file_id IN ($placeholders) ORDER BY ac.created_at DESC");
    $c_stmt->execute($file_ids);
    foreach ($c_stmt->fetchAll() as $c) {
        $all_comments[$c['file_id']][] = $c;
    }

    // Versions
    $v_stmt = $pdo->prepare("SELECT fv.*, u.username FROM file_versions fv LEFT JOIN users u ON fv.uploaded_by = u.id WHERE fv.file_id IN ($placeholders) ORDER BY fv.version_number DESC");
    $v_stmt->execute($file_ids);
    foreach ($v_stmt->fetchAll() as $v) {
        $all_versions[$v['file_id']][] = $v;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Upload - Portfolio System</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <div class="bg-white p-8 rounded-lg shadow-md mb-8">
                <h2 class="text-2xl font-bold mb-6">Upload File</h2>

                <?php if ($message): ?>
                    <div class="mb-4 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-400' : 'bg-red-100 text-red-700 border border-red-400'; ?>">
                        <?php echo htmlspecialchars($message); ?>
                    </div>
                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                    <div class="mb-4">
                        <label for="title" class="block text-gray-700 font-medium mb-2">Title *</label>
                        <input type="text" id="title" name="title" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    </div>

                    <div class="mb-4">
                        <label for="description" class="block text-gray-700 font-medium mb-2">Description</label>
                        <textarea id="description" name="description" rows="3" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="category_id" class="block text-gray-700 font-medium mb-2">Category *</label>
                        <select id="category_id" name="category_id" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $cat):
                                $path = getCategoryPath($pdo, $cat['id']);
                                $depth = count($path) - 1;
                                $indent = str_repeat('— ', $depth);
                                $label = $indent . end($path);
                            ?>
                                <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($label); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label for="file" class="block text-gray-700 font-medium mb-2">File *</label>
                        <input type="file" id="file" name="file" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        <p class="text-sm text-gray-500 mt-1">Max size: 10MB. Allowed safe formats only (pdf, docx, xlsx, images, zip, txt).</p>
                    </div>

                    <div class="mb-6">
                        <label class="block text-gray-700 font-medium mb-2">Allowed Visitors (Check to grant access)</label>
                        <div class="space-y-2">
                            <?php if (empty($visitors)): ?>
                                <p class="text-gray-500 italic">No visitors found. Create visitor accounts first.</p>
                            <?php else: ?>
                                <?php foreach ($visitors as $visitor): ?>
                                    <label class="flex items-center">
                                        <input type="checkbox" name="visitors[]" value="<?php echo $visitor['id']; ?>" class="mr-2">
                                        <span><?php echo htmlspecialchars($visitor['username']); ?> (<?php echo htmlspecialchars($visitor['email']); ?>)</span>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <button type="submit" name="upload" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-200">
                        Upload File
                    </button>
                </form>
            </div>

            <!-- Manage Files Section -->
            <div class="bg-white p-8 rounded-lg shadow-md">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-2xl font-bold">Manage Files</h2>
                </div>

                <!-- Filters -->
                <form method="GET" class="mb-6 bg-gray-50 p-4 rounded-lg flex flex-wrap gap-4 items-end border">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <select name="filter_category" class="px-3 py-2 border rounded-lg focus:outline-none">
                            <option value="">All Categories</option>
                            <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo (isset($_GET['filter_category']) && $_GET['filter_category'] == $cat['id']) ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">File Status</label>
                        <select name="filter_status" class="px-3 py-2 border rounded-lg focus:outline-none">
                            <option value="">All Statuses</option>
                            <option value="Submitted" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Submitted') ? 'selected' : ''; ?>>Submitted</option>
                            <option value="Approved" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Approved') ? 'selected' : ''; ?>>Approved</option>
                            <option value="Rejected" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Rejected') ? 'selected' : ''; ?>>Rejected</option>
                            <option value="Modification Required" <?php echo (isset($_GET['filter_status']) && $_GET['filter_status'] == 'Modification Required') ? 'selected' : ''; ?>>Modification Required</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Comments</label>
                        <select name="filter_comment" class="px-3 py-2 border rounded-lg focus:outline-none">
                            <option value="">All</option>
                            <option value="Open" <?php echo (isset($_GET['filter_comment']) && $_GET['filter_comment'] == 'Open') ? 'selected' : ''; ?>>Has Open Comments</option>
                        </select>
                    </div>
                    <div>
                        <button type="submit" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-lg hover:bg-gray-300">Filter</button>
                        <a href="admin_upload.php" class="ml-2 text-sm text-blue-500 hover:underline">Clear</a>
                    </div>
                </form>

                <?php if (empty($files)): ?>
                    <p class="text-gray-600">No files found.</p>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($files as $file): ?>
                            <div class="border p-4 rounded-lg bg-gray-50 flex flex-col md:flex-row justify-between">
                                <div class="flex-1">
                                    <h3 class="font-bold text-lg flex items-center">
                                        <?php echo htmlspecialchars($file['title']); ?>
                                        <span class="ml-3 text-xs px-2 py-1 bg-blue-100 text-blue-800 rounded-full">v<?php echo $file['version_number']; ?></span>
                                    </h3>
                                    <p class="text-gray-600 mb-2"><?php echo htmlspecialchars($file['description']); ?></p>
                                    <p class="text-sm text-gray-500">
                                        <span class="font-medium">File Path:</span> <?php echo htmlspecialchars($file['file_path']); ?>
                                    </p>
                                    <p class="text-sm text-gray-500">
                                        <span class="font-medium">Allowed Visitors:</span> <?php echo htmlspecialchars($file['allowed_visitors'] ?? 'None'); ?>
                                    </p>
                                    
                                    <!-- Status Toggle Form -->
                                    <form method="POST" class="mt-3 flex items-center space-x-4 bg-white p-2 rounded border inline-block">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                        <select name="status" class="text-sm border-gray-300 rounded focus:outline-none">
                                            <option value="Submitted" <?php echo $file['status'] === 'Submitted' ? 'selected' : ''; ?>>Submitted</option>
                                            <option value="Approved" <?php echo $file['status'] === 'Approved' ? 'selected' : ''; ?>>Approved</option>
                                            <option value="Rejected" <?php echo $file['status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                            <option value="Modification Required" <?php echo $file['status'] === 'Modification Required' ? 'selected' : ''; ?>>Modification Req.</option>
                                        </select>
                                        <label class="flex items-center text-sm">
                                            <input type="checkbox" name="is_active" value="1" class="mr-1" <?php echo $file['is_active'] ? 'checked' : ''; ?>> Visible
                                        </label>
                                        <button type="submit" name="update_status" class="text-xs bg-gray-200 px-2 py-1 rounded hover:bg-gray-300">Save</button>
                                    </form>
                                </div>
                                <div class="mt-4 md:mt-0 flex flex-col space-y-2 justify-center">
                                    <button
                                        onclick='openCommentsModal(<?php echo json_encode($file['id']); ?>, <?php echo json_encode($all_comments[$file['id']] ?? []); ?>)'
                                        class="text-sm border border-blue-500 text-blue-500 px-3 py-1 rounded hover:bg-blue-50 transition duration-200 relative">
                                        Comments
                                        <?php if ($file['open_comments_count'] > 0): ?>
                                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center"><?php echo $file['open_comments_count']; ?></span>
                                        <?php endif; ?>
                                    </button>
                                    <button
                                        onclick='openVersionsModal(<?php echo json_encode($file['id']); ?>, <?php echo json_encode($all_versions[$file['id']] ?? []); ?>)'
                                        class="text-sm border border-gray-500 text-gray-700 px-3 py-1 rounded hover:bg-gray-100 transition duration-200">
                                        Version History
                                    </button>
                                    <button
                                        data-id="<?php echo $file['id']; ?>"
                                        data-title="<?php echo htmlspecialchars($file['title'], ENT_QUOTES); ?>"
                                        data-description="<?php echo htmlspecialchars($file['description'], ENT_QUOTES); ?>"
                                        data-visitors="<?php echo htmlspecialchars($file['allowed_visitors'] ?? '', ENT_QUOTES); ?>"
                                        onclick="editFile(this)"
                                        class="text-sm bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 transition duration-200">
                                        Edit / Upload New Version
                                    </button>
                                    <form method="POST" style="display:inline;">
                                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                        <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                        <button type="submit" name="delete" onclick="return confirm('Are you sure you want to completely delete this file and all its versions?')"
                                                class="w-full text-sm bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 transition duration-200">
                                            Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Edit Modal -->
            <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 p-4 overflow-y-auto">
                <div class="bg-white p-6 rounded-lg w-full max-w-md my-8">
                    <h3 class="text-xl font-bold mb-4">Edit File & Upload New Version</h3>
                    <form method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="file_id" id="edit_file_id">
                        <div class="mb-4">
                            <label for="edit_title" class="block text-gray-700 font-medium mb-2">Title</label>
                            <input type="text" id="edit_title" name="edit_title" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <div class="mb-4">
                            <label for="edit_description" class="block text-gray-700 font-medium mb-2">Description</label>
                            <textarea id="edit_description" name="edit_description" rows="3" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        <div class="mb-4 p-4 border border-dashed border-gray-400 rounded bg-gray-50">
                            <label for="edit_file" class="block text-gray-700 font-medium mb-2">Upload New Version (Optional)</label>
                            <input type="file" id="edit_file" name="edit_file" class="w-full text-sm">
                            <p class="text-xs text-gray-500 mt-1">Leave empty to keep current file. If you upload a file, it will become the new current version.</p>
                        </div>
                        <div class="mb-6">
                            <label class="block text-gray-700 font-medium mb-2">Allowed Visitors</label>
                            <div class="max-h-32 overflow-y-auto border p-2 rounded">
                                <?php foreach ($visitors as $visitor): ?>
                                    <label class="flex items-center mb-1 text-sm">
                                        <input type="checkbox" name="edit_visitors[]" value="<?php echo $visitor['id']; ?>" class="mr-2 edit-visitor-checkbox">
                                        <span><?php echo htmlspecialchars($visitor['username']); ?></span>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="flex space-x-2">
                            <button type="submit" name="edit" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600 transition duration-200">
                                Save Changes
                            </button>
                            <button type="button" onclick="closeEditModal()" class="bg-gray-500 text-white px-4 py-2 rounded hover:bg-gray-600 transition duration-200">
                                Cancel
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Comments Modal -->
            <div id="commentsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 p-4">
                <div class="bg-white p-6 rounded-lg w-full max-w-lg max-h-[90vh] flex flex-col">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold">Admin Comments</h3>
                        <button onclick="closeCommentsModal()" class="text-gray-500 hover:text-gray-700">&times;</button>
                    </div>
                    <div id="commentsContainer" class="flex-1 overflow-y-auto space-y-4 mb-4 pr-2">
                        <!-- Comments rendered here via JS -->
                    </div>
                    <form method="POST" class="mt-auto border-t pt-4">
                        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                        <input type="hidden" name="file_id" id="comment_file_id">
                        <textarea name="comment_text" rows="2" class="w-full px-3 py-2 border rounded-lg focus:outline-none mb-2" placeholder="Add a private admin comment..." required></textarea>
                        <button type="submit" name="add_comment" class="bg-blue-500 text-white px-4 py-2 rounded hover:bg-blue-600">Post Comment</button>
                    </form>
                </div>
            </div>

            <!-- Versions Modal -->
            <div id="versionsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50 p-4">
                <div class="bg-white p-6 rounded-lg w-full max-w-lg max-h-[90vh] flex flex-col">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-xl font-bold">Version History</h3>
                        <button onclick="closeVersionsModal()" class="text-gray-500 hover:text-gray-700">&times;</button>
                    </div>
                    <div id="versionsContainer" class="flex-1 overflow-y-auto space-y-3">
                        <!-- Versions rendered here via JS -->
                    </div>
                </div>
            </div>

            <div class="mt-6">
                <a href="portfolio.php" class="inline-block text-blue-500 hover:text-blue-700">← Back to Portfolio</a>
            </div>
        </div>
    </div>

    <script>
        // PHP-generated value — must stay inline; all other JS is in script.js
        const CSRF_TOKEN = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
    </script>
    <script src="script.js"></script>
</body>
</html>

            document.getElementById('edit_file_id').value = button.dataset.id;
            document.getElementById('edit_title').value = button.dataset.title;
            document.getElementById('edit_description').value = button.dataset.description;

            // Clear all checkboxes
            document.querySelectorAll('.edit-visitor-checkbox').forEach(cb => cb.checked = false);

            // Check appropriate boxes
            const visitors = button.dataset.visitors ? button.dataset.visitors.split(',') : [];
            document.querySelectorAll('.edit-visitor-checkbox').forEach(cb => {
                const label = cb.parentElement.textContent.trim();
                if (visitors.some(v => label.includes(v.trim()))) {
                    cb.checked = true;
                }
            });

            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }

        function escapeHTML(str) {
            return str.replace(/[&<>'"]/g, 
                tag => ({
                    '&': '&amp;',
                    '<': '&lt;',
                    '>': '&gt;',
                    "'": '&#39;',
                    '"': '&quot;'
                }[tag])
            );
        }

        function openCommentsModal(fileId, comments) {
            document.getElementById('comment_file_id').value = fileId;
            const container = document.getElementById('commentsContainer');
            container.innerHTML = '';

            if (comments.length === 0) {
                container.innerHTML = '<p class="text-gray-500 italic">No comments yet.</p>';
            } else {
                comments.forEach(c => {
                    const isClosed = c.status === 'Closed';
                    const bgClass = isClosed ? 'bg-gray-100' : 'bg-blue-50 border border-blue-100';
                    const closeBtn = !isClosed ? `
                        <form method="POST" class="inline">
                            <input type="hidden" name="csrf_token" value="${escapeHTML(CSRF_TOKEN)}">
                            <input type="hidden" name="comment_id" value="${c.id}">
                            <button type="submit" name="close_comment" class="text-xs text-gray-500 hover:text-green-600 underline">Resolve</button>
                        </form>` : '<span class="text-xs text-green-600 font-medium">Resolved</span>';

                    container.innerHTML += `
                        <div class="p-3 rounded ${bgClass}">
                            <div class="flex justify-between items-start mb-1">
                                <span class="font-bold text-sm text-gray-800">${escapeHTML(c.username)}</span>
                                <div class="flex space-x-2 items-center">
                                    <span class="text-xs text-gray-500">${c.created_at}</span>
                                    ${closeBtn}
                                </div>
                            </div>
                            <p class="text-sm text-gray-700 ${isClosed ? 'opacity-70' : ''}">${escapeHTML(c.comment)}</p>
                        </div>
                    `;
                });
            }
            document.getElementById('commentsModal').classList.remove('hidden');
        }

        function closeCommentsModal() {
            document.getElementById('commentsModal').classList.add('hidden');
        }

        function openVersionsModal(fileId, versions) {
            const container = document.getElementById('versionsContainer');
            container.innerHTML = '';
            
            versions.forEach((v, index) => {
                const isLatest = index === 0;
                container.innerHTML += `
                    <div class="p-3 border rounded flex justify-between items-center ${isLatest ? 'bg-blue-50 border-blue-200' : 'bg-white'}">
                        <div>
                            <span class="font-bold text-sm">Version ${v.version_number} ${isLatest ? '<span class="text-xs text-blue-600 font-normal">(Current)</span>' : ''}</span>
                            <div class="text-xs text-gray-500">Uploaded by ${escapeHTML(v.username)} on ${v.created_at}</div>
                        </div>
                        <a href="${escapeHTML(v.file_path)}" target="_blank" class="text-sm text-blue-500 hover:underline">Download</a>
                    </div>
                `;
            });

            document.getElementById('versionsModal').classList.remove('hidden');
        }

        function closeVersionsModal() {
            document.getElementById('versionsModal').classList.add('hidden');
        }
    </script>
</body>
</html>