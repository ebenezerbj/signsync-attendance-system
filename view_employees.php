<?php
include 'db.php';

// Fetch filter options
$branchOptions = $conn->query("SELECT BranchID, BranchName FROM tbl_branches ORDER BY BranchName")->fetchAll(PDO::FETCH_ASSOC);
$categoryOptions = $conn->query("SELECT CategoryID, CategoryName FROM employee_categories ORDER BY CategoryName")->fetchAll(PDO::FETCH_ASSOC);

// Handle filters
$branchFilter = $_GET['branch'] ?? '';
$categoryFilter = $_GET['category'] ?? '';
$where = [];
$params = [];

if ($branchFilter) {
    $where[] = "e.EmployeeID IN (SELECT EmployeeID FROM employee_branches WHERE BranchID = ?)";
    $params[] = $branchFilter;
}
if ($categoryFilter) {
    $where[] = "e.CategoryID = ?";
    $params[] = $categoryFilter;
}
$whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

// Fetch all employees with their department, role, and branches
$stmt = $conn->prepare("
    SELECT 
        e.EmployeeID, e.FullName, e.Username, e.PhoneNumber, e.IsSpecial,
        d.DepartmentName,
        r.RoleName,
        COALESCE(GROUP_CONCAT(b.BranchName ORDER BY b.BranchName SEPARATOR ', '), '—') AS Branches
    FROM tbl_employees e
    LEFT JOIN employee_branches eb ON e.EmployeeID = eb.EmployeeID
    LEFT JOIN tbl_branches b ON eb.BranchID = b.BranchID
    LEFT JOIN tbl_departments d ON e.DepartmentID = d.DepartmentID
    LEFT JOIN tbl_roles r ON e.RoleID = r.RoleID
    $whereSql
    GROUP BY e.EmployeeID
    ORDER BY e.FullName
");
$stmt->execute($params);
$employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>View Employees</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
    body { background: #f8fafc; }
    .table-section { max-width: 1100px; margin: 40px auto; background: #fff; border-radius: 12px; box-shadow: 0 2px 8px #0001; padding: 2rem; }
    .badge-special { background: #0d6efd; }
    .filters { margin-bottom: 1.5rem; display: flex; gap: 1rem; flex-wrap: wrap; }
    @media (max-width: 700px) { .filters { flex-direction: column; gap: 0.5rem; } }
  </style>
</head>
<body>
  <div class="table-section">
    <h2 class="mb-4 text-primary">All Employees &amp; Branches</h2>
    <form method="get" class="filters">
      <div>
        <label for="branch" class="form-label mb-0">Branch:</label>
        <select name="branch" id="branch" class="form-select">
          <option value="">All Branches</option>
          <?php foreach ($branchOptions as $b): ?>
            <option value="<?= $b['BranchID'] ?>" <?= $branchFilter == $b['BranchID'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($b['BranchName']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label for="category" class="form-label mb-0">Category:</label>
        <select name="category" id="category" class="form-select">
          <option value="">All Categories</option>
          <?php foreach ($categoryOptions as $c): ?>
            <option value="<?= $c['CategoryID'] ?>" <?= $categoryFilter == $c['CategoryID'] ? 'selected' : '' ?>>
              <?= htmlspecialchars($c['CategoryName']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="align-self-end">
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="view_employees.php" class="btn btn-secondary">Reset</a>
      </div>
    </form>
    <table class="table table-bordered table-hover align-middle">
      <thead class="table-light">
        <tr>
          <th>Employee ID</th>
          <th>Full Name</th>
          <th>Department</th>
          <th>Role</th>
          <th>Username</th>
          <th>Phone</th>
          <th>Branches</th>
          <th>Type</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($employees as $emp): ?>
        <tr>
          <td><?= htmlspecialchars($emp['EmployeeID']) ?></td>
          <td><?= htmlspecialchars($emp['FullName']) ?></td>
          <td><?= htmlspecialchars($emp['DepartmentName'] ?? 'N/A') ?></td>
          <td><?= htmlspecialchars($emp['RoleName'] ?? 'N/A') ?></td>
          <td><?= htmlspecialchars($emp['Username']) ?></td>
          <td><?= htmlspecialchars($emp['PhoneNumber']) ?></td>
          <td><?= htmlspecialchars($emp['Branches'] ?: '—') ?></td>
          <td>
            <?php if ($emp['IsSpecial']): ?>
              <span class="badge badge-special text-white">Special</span>
            <?php else: ?>
              <span class="badge bg-secondary">Normal</span>
            <?php endif; ?>
          </td>
          <td>
            <a href="edit_employee.php?id=<?= $emp['EmployeeID'] ?>" class="btn btn-sm btn-info" title="Edit">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-pencil-square" viewBox="0 0 16 16"><path d="M15.502 1.94a.5.5 0 0 1 0 .706l-1 1a.5.5 0 0 1-.708 0l-1-1a.5.5 0 0 1 0-.708l1-1a.5.5 0 0 1 .708 0l1 1zM12.5 5.5a.5.5 0 0 1 .5.5v1.5a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5z"/><path d="M1 13.5A1.5 1.5 0 0 0 2.5 15h11a1.5 1.5 0 0 0 1.5-1.5v-6a.5.5 0 0 0-1 0v6a.5.5 0 0 1-.5.5h-11a.5.5 0 0 1-.5-.5v-11a.5.5 0 0 1 .5-.5h6a.5.5 0 0 0 0-1h-6A1.5 1.5 0 0 0 1 2.5v11z"/></svg>
            </a>
            <a href="delete_employee.php?id=<?= $emp['EmployeeID'] ?>" class="btn btn-sm btn-danger ms-1" title="Delete" onclick="return confirm('Are you sure you want to delete this employee?');">
              <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" fill="currentColor" class="bi bi-trash" viewBox="0 0 16 16"><path d="M5.5 5.5A.5.5 0 0 1 6 6v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm2.5 0a.5.5 0 0 1 .5.5v6a.5.5 0 0 1-1 0V6a.5.5 0 0 1 .5-.5zm3 .5a.5.5 0 0 0-1 0v6a.5.5 0 0 0 1 0V6z"/><path fill-rule="evenodd" d="M14.5 3a1 1 0 0 1-1 1H13v9.5A1.5 1.5 0 0 1 11.5 15h-7A1.5 1.5 0 0 1 3 13.5V4h-.5a1 1 0 0 1-1-1V2.5A1.5 1.5 0 0 1 3 1h10a1.5 1.5 0 0 1 1.5 1.5V3zM2 3h12v-.5a.5.5 0 0 0-.5-.5H2.5a.5.5 0 0 0-.5.5V3z"/></svg>
            </a>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</body>
</html>