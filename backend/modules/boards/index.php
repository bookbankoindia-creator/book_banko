<?php
/**
 * Educational Boards Management
 * Book Banko
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../config/helpers.php';

requireLogin();

$pageTitle = 'Educational Boards';
$pageSubtitle = 'Manage Boards like GSEB, CBSE, NCERT, ICSE for the application';
$db = Database::getConnection();

// Fetch all boards
$stmt = $db->query("
    SELECT b.*, 
    (SELECT COUNT(*) FROM subjects s WHERE s.board_id = b.id) as subject_count
    FROM boards b
    ORDER BY b.display_order ASC, b.id ASC
");
$boards = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <h5 class="fw-bold mb-0 text-dark">All Education Boards (<?= count($boards) ?>)</h5>
    <?php if (hasPermission('boards', 'add')): ?>
    <a href="<?= url('modules/boards/add.php') ?>" class="btn btn-bb-primary btn-sm d-inline-flex align-items-center gap-2">
        <i class="bi bi-plus-lg"></i>
        <span>Add New Board</span>
    </a>
    <?php endif; ?>
</div>

<div class="bb-card p-3 p-md-4">
    <div class="table-responsive">
        <table class="table bb-table data-table align-middle w-100">
            <thead>
                <tr>
                    <th width="60">Order</th>
                    <th>Board Code</th>
                    <th>Full Board Name</th>
                    <th>Subjects Linked</th>
                    <th>Status</th>
                    <th width="140" class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($boards as $board): ?>
                    <tr>
                        <td>
                            <span class="badge bg-light text-secondary border"><?= $board['display_order'] ?></span>
                        </td>
                        <td>
                            <span class="fw-bold text-primary fs-6"><?= htmlspecialchars($board['code']) ?></span>
                        </td>
                        <td>
                            <div class="fw-bold text-dark"><?= htmlspecialchars($board['name']) ?></div>
                            <?php if (!empty($board['description'])): ?>
                                <small class="text-muted"><?= htmlspecialchars($board['description']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle">
                                <?= $board['subject_count'] ?> Subjects
                            </span>
                        </td>
                        <td>
                            <?php if ($board['status'] === 'active'): ?>
                                <span class="badge-bb badge-bb-active"><i class="bi bi-check-circle-fill"></i> Active</span>
                            <?php else: ?>
                                <span class="badge-bb badge-bb-inactive"><i class="bi bi-x-circle-fill"></i> Inactive</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-end">
                            <?php if (hasPermission('boards', 'edit')): ?>
                                <a href="<?= url('modules/boards/edit.php?id=' . $board['id']) ?>" class="btn btn-sm btn-light border p-1 px-2" title="Edit">
                                    <i class="bi bi-pencil-fill text-primary"></i>
                                </a>
                                <a href="<?= url('modules/boards/delete.php?id=' . $board['id'] . '&csrf=' . getCSRFToken()) ?>" class="btn btn-sm btn-light border p-1 px-2 text-danger" title="Delete" onclick="return confirm('Are you sure you want to delete board <?= htmlspecialchars($board['code']) ?>? All associated subjects will also be deleted.');">
                                    <i class="bi bi-trash-fill"></i>
                                </a>
                            <?php else: ?>
                                <span class="badge bg-light text-secondary border">View Only</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
