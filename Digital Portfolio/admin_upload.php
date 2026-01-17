<?php
// admin_upload.php - Admin file upload and permission management
session_start();
require 'db.php';

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
$message_type = 'success'; // success or error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['upload'])) {
        $title = trim($_POST['title']);
        $description = trim($_POST['description']);
        $category_id = $_POST['category_id'];
        $allowed_visitors = $_POST['visitors'] ?? [];

        // Validate inputs
        if (empty($title)) {
            $message = 'Title is required.';
            $message_type = 'error';
        } elseif (empty($_FILES['file']['name'])) {
            $message = 'Please select a file to upload.';
            $message_type = 'error';
        } elseif (empty($category_id)) {
            $message = 'Please select a category.';
            $message_type = 'error';
        } elseif ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $message = 'File upload error: ' . $_FILES['file']['error'];
            $message_type = 'error';
        } else {
            // Check file size (max 10MB)
            $max_size = 10 * 1024 * 1024; // 10MB
            if ($_FILES['file']['size'] > $max_size) {
                $message = 'File size exceeds 10MB limit.';
                $message_type = 'error';
            } else {
                // Allowed file types
                $allowed_types = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'php', 'html', 'css', 'js', 'sql', 'zip'];
                $file_ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
                if (!in_array($file_ext, $allowed_types)) {
                    $message = 'Invalid file type. Allowed: ' . implode(', ', $allowed_types);
                    $message_type = 'error';
                } else {
                    // Proceed with upload
                    $upload_dir = 'uploads/';
                    if (!is_dir($upload_dir)) {
                        mkdir($upload_dir, 0755, true);
                    }

                    $file_name = basename($_FILES['file']['name']);
                    $file_path = $upload_dir . time() . '_' . preg_replace('/[^a-zA-Z0-9\._-]/', '_', $file_name);

                    if (move_uploaded_file($_FILES['file']['tmp_name'], $file_path)) {
                        try {
                            // Insert file into database
                            $stmt = $pdo->prepare("INSERT INTO files (title, description, file_path, category_id, uploaded_by) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$title, $description, $file_path, $category_id, $_SESSION['user_id']]);
                            $file_id = $pdo->lastInsertId();

                            // Insert permissions for selected visitors
                            if (!empty($allowed_visitors)) {
                                $stmt_perm = $pdo->prepare("INSERT INTO permissions (user_id, file_id) VALUES (?, ?)");
                                foreach ($allowed_visitors as $visitor_id) {
                                    $stmt_perm->execute([$visitor_id, $file_id]);
                                }
                            }

                            $message = "File uploaded successfully! File ID: $file_id";
                            $message_type = 'success';
                        } catch (PDOException $e) {
                            $message = 'Database error: ' . $e->getMessage();
                            $message_type = 'error';
                            // Delete the uploaded file if database insert fails
                            if (file_exists($file_path)) {
                                unlink($file_path);
                            }
                        }
                    } else {
                        $message = 'Failed to save the uploaded file.';
                        $message_type = 'error';
                    }
                }
            }
        }
    } elseif (isset($_POST['edit'])) {
        $file_id = $_POST['file_id'];
        $title = trim($_POST['edit_title']);
        $description = trim($_POST['edit_description']);
        $allowed_ids = $_POST['edit_visitors'] ?? [];

        try {
            // Update file
            $stmt = $pdo->prepare("UPDATE files SET title = ?, description = ? WHERE id = ? AND uploaded_by = ?");
            $stmt->execute([$title, $description, $file_id, $_SESSION['user_id']]);

            // Update permissions
            $pdo->prepare("DELETE FROM permissions WHERE file_id = ?")->execute([$file_id]);
            if (!empty($allowed_ids)) {
                $stmt = $pdo->prepare("INSERT INTO permissions (file_id, user_id) VALUES (?, ?)");
                foreach ($allowed_ids as $user_id) {
                    $stmt->execute([$file_id, $user_id]);
                }
            }

            $message = 'File updated successfully!';
            $message_type = 'success';
        } catch (PDOException $e) {
            $message = 'Error updating file: ' . $e->getMessage();
            $message_type = 'error';
        }
        
        header("Location: admin_upload.php");
        exit;
    } elseif (isset($_POST['delete'])) {
        $file_id = $_POST['file_id'];

        try {
            // Get file path
            $stmt = $pdo->prepare("SELECT file_path FROM files WHERE id = ? AND uploaded_by = ?");
            $stmt->execute([$file_id, $_SESSION['user_id']]);
            $file = $stmt->fetch();

            if ($file) {
                // Delete permissions
                $pdo->prepare("DELETE FROM permissions WHERE file_id = ?")->execute([$file_id]);

                // Delete file record
                $pdo->prepare("DELETE FROM files WHERE id = ? AND uploaded_by = ?")->execute([$file_id, $_SESSION['user_id']]);

                // Delete physical file
                if (file_exists($file['file_path'])) {
                    unlink($file['file_path']);
                }

                $message = 'File deleted successfully!';
                $message_type = 'success';
            } else {
                $message = 'File not found or access denied.';
                $message_type = 'error';
            }
        } catch (PDOException $e) {
            $message = 'Error deleting file: ' . $e->getMessage();
            $message_type = 'error';
        }
        
        header("Location: admin_upload.php");
        exit;
    }
}

// Fetch categories for dropdown
$categories = $pdo->query("SELECT * FROM categories ORDER BY id")->fetchAll();

// Fetch visitors for checkboxes
$visitors = $pdo->query("SELECT id, username, email FROM users WHERE role = 'Visitor'")->fetchAll();

// Fetch files uploaded by this admin
$files = $pdo->prepare("SELECT f.*, GROUP_CONCAT(u.username) as allowed_visitors FROM files f LEFT JOIN permissions p ON f.id = p.file_id LEFT JOIN users u ON p.user_id = u.id WHERE f.uploaded_by = ? GROUP BY f.id ORDER BY f.id DESC");
$files->execute([$_SESSION['user_id']]);
$files = $files->fetchAll();
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
        <div class="max-w-2xl mx-auto bg-white p-8 rounded-lg shadow-md">
            <h2 class="text-2xl font-bold mb-6">Upload File</h2>
            
            <?php if ($message): ?>
                <div class="mb-4 p-4 rounded-lg <?php echo $message_type === 'success' ? 'bg-green-100 text-green-700 border border-green-400' : 'bg-red-100 text-red-700 border border-red-400'; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" enctype="multipart/form-data">
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
                            $full_name = implode(' > ', $path);
                        ?>
                            <option value="<?php echo $cat['id']; ?>"><?php echo htmlspecialchars($full_name); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="mb-4">
                    <label for="file" class="block text-gray-700 font-medium mb-2">File *</label>
                    <input type="file" id="file" name="file" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                    <p class="text-sm text-gray-500 mt-1">Max size: 10MB. Allowed: pdf, doc, docx, xls, xlsx, jpg, jpeg, png, gif, txt, php, html, css, js, sql, zip</p>
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
                
                <!-- FIXED: Added name="upload" to the submit button -->
                <button type="submit" name="upload" class="w-full bg-blue-500 text-white py-2 rounded-lg hover:bg-blue-600 transition duration-200">
                    Upload File
                </button>
            </form>
            
            <h2 class="text-2xl font-bold mb-6 mt-8">Manage Files</h2>
            <?php if (empty($files)): ?>
                <p class="text-gray-600">No files uploaded yet.</p>
            <?php else: ?>
                <div class="space-y-4">
                    <?php foreach ($files as $file): ?>
                        <div class="border p-4 rounded-lg bg-gray-50">
                            <h3 class="font-bold text-lg"><?php echo htmlspecialchars($file['title']); ?></h3>
                            <p class="text-gray-600"><?php echo htmlspecialchars($file['description']); ?></p>
                            <p class="text-sm text-gray-500 mt-2">
                                <span class="font-medium">File:</span> <?php echo htmlspecialchars($file['file_path']); ?>
                            </p>
                            <p class="text-sm text-gray-500">
                                <span class="font-medium">Allowed Visitors:</span> <?php echo $file['allowed_visitors'] ?: 'None'; ?>
                            </p>
                            <div class="mt-3 flex space-x-2">
                                <button onclick="editFile(<?php echo $file['id']; ?>, '<?php echo addslashes($file['title']); ?>', '<?php echo addslashes($file['description']); ?>', '<?php echo addslashes($file['allowed_visitors']); ?>')" 
                                        class="bg-yellow-500 text-white px-3 py-1 rounded hover:bg-yellow-600 transition duration-200">
                                    Edit
                                </button>
                                <form method="POST" style="display:inline;">
                                    <input type="hidden" name="file_id" value="<?php echo $file['id']; ?>">
                                    <button type="submit" name="delete" onclick="return confirm('Are you sure you want to delete this file?')" 
                                            class="bg-red-500 text-white px-3 py-1 rounded hover:bg-red-600 transition duration-200">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Edit Modal -->
            <div id="editModal" class="fixed inset-0 bg-black bg-opacity-50 hidden flex items-center justify-center z-50">
                <div class="bg-white p-6 rounded-lg w-full max-w-md">
                    <h3 class="text-xl font-bold mb-4">Edit File</h3>
                    <form method="POST">
                        <input type="hidden" name="file_id" id="edit_file_id">
                        <div class="mb-4">
                            <label for="edit_title" class="block text-gray-700 font-medium mb-2">Title</label>
                            <input type="text" id="edit_title" name="edit_title" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500" required>
                        </div>
                        <div class="mb-4">
                            <label for="edit_description" class="block text-gray-700 font-medium mb-2">Description</label>
                            <textarea id="edit_description" name="edit_description" rows="3" class="w-full px-3 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500"></textarea>
                        </div>
                        <div class="mb-6">
                            <label class="block text-gray-700 font-medium mb-2">Allowed Visitors</label>
                            <?php foreach ($visitors as $visitor): ?>
                                <label class="flex items-center mb-2">
                                    <input type="checkbox" name="edit_visitors[]" value="<?php echo $visitor['id']; ?>" class="mr-2 edit-visitor-checkbox">
                                    <span><?php echo htmlspecialchars($visitor['username']); ?></span>
                                </label>
                            <?php endforeach; ?>
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
            
            <div class="mt-6 space-x-4">
                <a href="portfolio.php" class="inline-block text-blue-500 hover:text-blue-700">← Back to Portfolio</a>
            </div>
        </div>
    </div>

    <script>
        function editFile(id, title, description, allowedVisitors) {
            document.getElementById('edit_file_id').value = id;
            document.getElementById('edit_title').value = title;
            document.getElementById('edit_description').value = description;
            
            // Clear all checkboxes first
            document.querySelectorAll('.edit-visitor-checkbox').forEach(cb => cb.checked = false);
            
            // Check the appropriate boxes
            if (allowedVisitors) {
                const visitors = allowedVisitors.split(',');
                document.querySelectorAll('.edit-visitor-checkbox').forEach(cb => {
                    const label = cb.parentElement.textContent.trim();
                    if (visitors.some(v => label.includes(v.trim()))) {
                        cb.checked = true;
                    }
                });
            }
            
            document.getElementById('editModal').classList.remove('hidden');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.add('hidden');
        }
    </script>
</body>
</html>