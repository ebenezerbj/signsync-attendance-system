<?php
include 'db.php';

// Add new rank
if (isset($_POST['add'])) {
    $rank = $_POST['rank_name'];
    $leave_days = $_POST['leave_days'];
    $stmt = $conn->prepare("INSERT INTO tbl_ranks (RankName, LeaveDays) VALUES (?, ?)");
    $stmt->execute([$rank, $leave_days]);
}

// Update rank
if (isset($_POST['update'])) {
    $id = $_POST['rank_id'];
    $rank = $_POST['rank_name'];
    $leave_days = $_POST['leave_days'];
    $stmt = $conn->prepare("UPDATE tbl_ranks SET RankName = ?, LeaveDays = ? WHERE RankID = ?");
    $stmt->execute([$rank, $leave_days, $id]);
}

// Delete rank
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    $stmt = $conn->prepare("DELETE FROM tbl_ranks WHERE RankID = ?");
    $stmt->execute([$id]);
}

// Fetch ranks
$ranks = $conn->query("SELECT * FROM tbl_ranks")->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html>
<head>
    <title>Manage Employee Ranks</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f8fafc; padding: 32px; color: #222; }
        h2 { color: #4f46e5; margin-bottom: 18px; }
        input[type=text], input[type=number] {
            padding: 10px;
            width: 220px;
            margin-right: 10px;
            border-radius: 6px;
            border: 1px solid #c7d2fe;
            background: #fff;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        input[type=text]:focus, input[type=number]:focus {
            border-color: #6366f1;
            outline: none;
        }
        button {
            padding: 10px 18px;
            background: #6366f1;
            color: #fff;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        button:hover {
            background: #4f46e5;
        }
        table {
            border-collapse: collapse;
            margin-top: 24px;
            width: 100%;
            background: #fff;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 12px rgba(99,102,241,0.08);
        }
        th, td {
            padding: 12px;
            border-bottom: 1px solid #e0e7ff;
            text-align: left;
        }
        th {
            background: #eef2ff;
            color: #6366f1;
            font-weight: 600;
        }
        tr:last-child td { border-bottom: none; }
        .form-inline { margin-top: 20px; display: flex; gap: 10px; align-items: center; }
        .actions { display: flex; gap: 8px; }
        .delete-link {
            color: #ef4444;
            text-decoration: none;
            font-weight: 500;
            padding: 6px 10px;
            border-radius: 6px;
            background: #fee2e2;
            transition: background 0.2s;
        }
        .delete-link:hover {
            background: #fecaca;
        }
        @media (max-width: 600px) {
            table, .form-inline { font-size: 0.95rem; }
            input, button { width: 100%; margin-bottom: 8px; }
            .form-inline { flex-direction: column; align-items: stretch; }
        }
    </style>
</head>
<body>

<h2>Employee Ranks & Leave Days</h2>

<form method="POST" class="form-inline" aria-label="Add new rank">
    <input type="hidden" name="rank_id" value="">
    <input type="text" name="rank_name" placeholder="Rank Name" required aria-label="Rank Name">
    <input type="number" name="leave_days" placeholder="Leave Days" required min="0" aria-label="Leave Days">
    <button type="submit" name="add">Add Rank</button>
</form>

<table aria-label="Employee Ranks Table">
    <tr>
        <th>ID</th>
        <th>Rank Name</th>
        <th>Leave Days</th>
        <th>Actions</th>
    </tr>
    <?php foreach ($ranks as $r): ?>
        <tr>
            <form method="POST">
                <td><?= $r['RankID'] ?></td>
                <td>
                    <input type="text" name="rank_name" value="<?= htmlspecialchars($r['RankName']) ?>" required aria-label="Edit Rank Name">
                </td>
                <td>
                    <input type="number" name="leave_days" value="<?= $r['LeaveDays'] ?>" required min="0" aria-label="Edit Leave Days">
                </td>
                <td class="actions">
                    <input type="hidden" name="rank_id" value="<?= $r['RankID'] ?>">
                    <button type="submit" name="update" title="Update this rank">Update</button>
                    <a href="?delete=<?= $r['RankID'] ?>" class="delete-link" onclick="return confirm('Delete this rank?')" title="Delete this rank">Delete</a>
                </td>
            </form>
        </tr>
    <?php endforeach; ?>
</table>

</body>
</html>
