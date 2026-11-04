<?php
require_once 'db.php';

$message = '';
$msg_type = 'success'; // 'success' or 'error'
$edit_id = null;
$full_name = '';
$year_of_study = '';
$level = 'O Level';
$email = '';

// Capture Search & Filter Inputs
$search = trim($_GET['search'] ?? '');
$filter_level = $_GET['filter_level'] ?? '';

// --- 1. CREATE & UPDATE LOGIC WITH BULLETPROOF VALIDATION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name     = trim($_POST['full_name'] ?? '');
    $year_of_study = trim($_POST['year_of_study'] ?? '');
    $level         = $_POST['level'] ?? '';
    $email         = trim($_POST['email'] ?? '');
    $id            = $_POST['id'] ?? null;

    $errors = [];

    // Validate Full Name
    if (empty($full_name)) {
        $errors[] = "Full name is required.";
    } elseif (strlen($full_name) < 3) {
        $errors[] = "Full name must be at least 3 characters long.";
    }

    // Validate Year of Study / Class
    if (empty($year_of_study)) {
        $errors[] = "Class / Year of study is required (e.g., Form 4, Form 6).";
    }

    // Validate Level (Strictly enforce ENUM constraints to prevent form tampering)
    if (!in_array($level, ['O Level', 'A Level'])) {
        $errors[] = "Invalid level selection.";
    }

    // Validate Email Address
    if (empty($email)) {
        $errors[] = "Email address is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format entered.";
    }

    // If no errors, proceed with database transaction
    if (empty($errors)) {
        if (!empty($id)) {
            // Update existing record using secure prepared statement
            $stmt = $pdo->prepare("UPDATE alumni_registry SET full_name = ?, year_of_study = ?, level = ?, email = ? WHERE id = ?");
            $stmt->execute([$full_name, $year_of_study, $level, $email, $id]);
            $message = "Record updated successfully!";
        } else {
            // Insert new record using secure prepared statement
            $stmt = $pdo->prepare("INSERT INTO alumni_registry (full_name, year_of_study, level, email) VALUES (?, ?, ?, ?)");
            $stmt->execute([$full_name, $year_of_study, $level, $email]);
            $message = "Student added successfully!";
        }
        // Reset form variables
        $full_name = $year_of_study = $email = '';
        $level = 'O Level';
        $edit_id = null;
    } else {
        $message = implode("<br>", $errors);
        $msg_type = "error";
    }
}

// --- 2. DELETE LOGIC ---
if (isset($_GET['delete'])) {
    $delete_id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM alumni_registry WHERE id = ?");
    $stmt->execute([$delete_id]);
    header("Location: alumni.php");
    exit();
}

// --- 3. FETCH RECORD FOR EDITING ---
if (isset($_GET['edit'])) {
    $edit_id = $_GET['edit'];
    $stmt = $pdo->prepare("SELECT * FROM alumni_registry WHERE id = ?");
    $stmt->execute([$edit_id]);
    $record = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($record) {
        $full_name     = $record['full_name'];
        $year_of_study = $record['year_of_study'];
        $level         = $record['level'];
        $email         = $record['email'];
    }
}

// --- 4. READ RECORDS WITH SEARCH & FILTER ---
$query = "SELECT * FROM alumni_registry WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (full_name LIKE ? OR year_of_study LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if (!empty($filter_level)) {
    $query .= " AND level = ?";
    $params[] = $filter_level;
}

$query .= " ORDER BY id DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$students = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>St. Leo's College Kyegobe - Student Registry</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 30px; background: #f4f6f9; }
        h1, h2 { color: #1a365d; }
        .container { max-width: 950px; margin: auto; background: white; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .msg-success { padding: 12px; background: #d4edda; color: #155724; margin-bottom: 15px; border-radius: 4px; border: 1px solid #c3e6cb; }
        .msg-error { padding: 12px; background: #f8d7da; color: #721c24; margin-bottom: 15px; border-radius: 4px; border: 1px solid #f5c6cb; }
        form.crud-form { margin-bottom: 30px; display: grid; gap: 10px; grid-template-columns: 1fr 1fr; background: #f8fafc; padding: 15px; border-radius: 6px; border: 1px solid #e2e8f0; }
        input, select, button { padding: 10px; border: 1px solid #ccc; border-radius: 4px; font-size: 14px; }
        .crud-form button { background: #1a365d; color: white; border: none; cursor: pointer; grid-column: span 2; }
        .crud-form button:hover { background: #2c5282; }
        
        /* Search Bar Box */
        .filter-box { display: flex; gap: 10px; margin-bottom: 15px; background: #edf2f7; padding: 12px; border-radius: 6px; align-items: center; }
        .filter-box input { flex: 2; }
        .filter-box select { flex: 1; }
        .filter-box button { background: #2b6cb0; color: white; border: none; cursor: pointer; }
        .filter-box a.reset-btn { padding: 9px 15px; background: #718096; color: white; text-decoration: none; border-radius: 4px; font-size: 14px; }
        
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
        th { background: #1a365d; color: white; }
        a.action-btn { padding: 5px 10px; color: white; text-decoration: none; border-radius: 3px; font-size: 12px; }
        .edit-btn { background: #e2e8f0; color: #2d3748; margin-right: 5px; }
        .delete-btn { background: #e53e3e; }
    </style>
</head>
<body>

<div class="container">
    <h1>St. Leo's College Kyegobe</h1>
    <h2>Student Registry Management (CRUD Module)</h2>

    <?php if ($message): ?>
        <div class="<?= $msg_type === 'error' ? 'msg-error' : 'msg-success' ?>">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <!-- STUDENT ENTRY / EDIT FORM -->
    <form action="alumni.php" method="POST" class="crud-form">
        <input type="hidden" name="id" value="<?= htmlspecialchars($edit_id) ?>">
        
        <div>
            <label>Full Name:</label><br>
            <input type="text" name="full_name" value="<?= htmlspecialchars($full_name) ?>" required style="width: 90%;">
        </div>

        <div>
            <label>Year / Class (e.g., Form 4, Form 6):</label><br>
            <input type="text" name="year_of_study" value="<?= htmlspecialchars($year_of_study) ?>" required style="width: 90%;">
        </div>

        <div>
            <label>Level:</label><br>
            <select name="level" style="width: 95%;">
                <option value="O Level" <?= $level == 'O Level' ? 'selected' : '' ?>>O Level</option>
                <option value="A Level" <?= $level == 'A Level' ? 'selected' : '' ?>>A Level</option>
            </select>
        </div>

        <div>
            <label>Email Address:</label><br>
            <input type="email" name="email" value="<?= htmlspecialchars($email) ?>" required style="width: 90%;">
        </div>

        <button type="submit"><?= $edit_id ? 'Update Record' : 'Add Student' ?></button>
    </form>

    <h2>Registered Students</h2>

    <!-- SEARCH & FILTER BAR -->
    <form action="alumni.php" method="GET" class="filter-box">
        <input type="text" name="search" placeholder="Search by name, class, or email..." value="<?= htmlspecialchars($search) ?>">
        
        <select name="filter_level">
            <option value="">-- All Levels --</option>
            <option value="O Level" <?= $filter_level == 'O Level' ? 'selected' : '' ?>>O Level</option>
            <option value="A Level" <?= $filter_level == 'A Level' ? 'selected' : '' ?>>A Level</option>
        </select>
        
        <button type="submit">Search</button>
        
        <?php if (!empty($search) || !empty($filter_level)): ?>
            <a href="alumni.php" class="reset-btn">Reset</a>
        <?php endif; ?>

        <!-- EXPORT CSV BUTTON -->
        <a href="export.php?search=<?= urlencode($search) ?>&filter_level=<?= urlencode($filter_level) ?>" 
           style="padding: 9px 15px; background: #276749; color: white; text-decoration: none; border-radius: 4px; font-size: 14px;">
           Export CSV
        </a>
    </form>

    <!-- TABLE LIST -->
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Full Name</th>
                <th>Class / Year</th>
                <th>Level</th>
                <th>Email</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($students) > 0): ?>
                <?php foreach ($students as $row): ?>
                <tr>
                    <td><?= htmlspecialchars($row['id']) ?></td>
                    <td><?= htmlspecialchars($row['full_name']) ?></td>
                    <td><?= htmlspecialchars($row['year_of_study']) ?></td>
                    <td><?= htmlspecialchars($row['level']) ?></td>
                    <td><?= htmlspecialchars($row['email']) ?></td>
                    <td>
                        <a href="alumni.php?edit=<?= $row['id'] ?>" class="action-btn edit-btn">Edit</a>
                        <a href="alumni.php?delete=<?= $row['id'] ?>" class="action-btn delete-btn" onclick="return confirm('Are you sure you want to delete this record?')">Delete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="6" style="text-align: center; color: #718096;">No students found matching your criteria.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

</body>
</html>