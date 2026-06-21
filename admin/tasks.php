<?php
declare(strict_types=1);
require_once __DIR__ . '/../public/config.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/auth.php';
require_admin();

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);

// ── POST handlers ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';

    if ($act === 'save') {
        $data = [
            ':title'       => trim($_POST['title'] ?? ''),
            ':description' => trim($_POST['description'] ?? '') ?: null,
            ':status'      => $_POST['status'] ?? 'pending',
            ':priority'    => $_POST['priority'] ?? 'medium',
            ':due_date'    => !empty($_POST['due_date']) ? $_POST['due_date'] : null,
            ':lead_id'     => $_POST['lead_id'] ? (int)$_POST['lead_id'] : null,
            ':user_id'     => $_POST['user_id'] ? (int)$_POST['user_id'] : null,
            ':assigned_to' => $_POST['assigned_to'] ? (int)$_POST['assigned_to'] : null,
            ':created_by'  => (int)$_SESSION['user_id'],
        ];
        $tid = (int)($_POST['task_id'] ?? 0);
        if ($tid) {
            $data[':id'] = $tid;
            db()->prepare("UPDATE crm_tasks SET title=:title,description=:description,status=:status,
                priority=:priority,due_date=:due_date,lead_id=:lead_id,user_id=:user_id,assigned_to=:assigned_to 
                WHERE id=:id")->execute($data);
        } else {
            db()->prepare("INSERT INTO crm_tasks (title,description,status,priority,due_date,lead_id,user_id,assigned_to,created_by)
                VALUES (:title,:description,:status,:priority,:due_date,:lead_id,:user_id,:assigned_to,:created_by)")
                ->execute($data);
        }
        flash('success', 'Задача сохранена');
        header('Location: tasks.php');
        exit;
    }

    if ($act === 'delete') {
        $tid = (int)($_POST['task_id'] ?? 0);
        db()->prepare("DELETE FROM crm_tasks WHERE id=:id")->execute([':id'=>$tid]);
        flash('success', 'Задача удалена');
        header('Location: tasks.php');
        exit;
    }
}

// ── Load data for edit ─────────────────────────────────────
$task = null;
if ($id && in_array($action, ['edit','delete'])) {
    $st = db()->prepare("SELECT * FROM crm_tasks WHERE id=:id");
    $st->execute([':id'=>$id]);
    $task = $st->fetch();
}

$leads = db()->query("SELECT id, name FROM crm_leads ORDER BY name")->fetchAll();
$users = db()->query("SELECT id, name, email FROM users ORDER BY name")->fetchAll();

// Pagination for list
$page = max(1,(int)($_GET['p']??1));
$per  = 20;
$total = (int)db()->query("SELECT COUNT(*) FROM crm_tasks")->fetchColumn();
$pag  = paginate($total, $page, $per);

$tasks = db()->prepare(
    "SELECT t.*, l.name AS lead_name, u.name AS user_name, a.name AS assigned_name, c.name AS creator_name 
     FROM crm_tasks t 
     LEFT JOIN crm_leads l ON l.id=t.lead_id 
     LEFT JOIN users u ON u.id=t.user_id 
     LEFT JOIN users a ON a.id=t.assigned_to 
     LEFT JOIN users c ON c.id=t.created_by 
     ORDER BY t.due_date IS NULL, t.due_date ASC, t.priority DESC, t.created_at DESC 
     LIMIT {$pag['per']} OFFSET {$pag['offset']}"
);
$tasks->execute();
$tasks = $tasks->fetchAll();

$flashes = get_flash();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8"><title>Задачи — CRM Админ</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Bebas+Neue&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/css/style.css">
</head>
<body>
<header class="admin-header"><div class="admin-header-inner">
  <div class="admin-logo">✂ BarberStore Admin</div>
  <nav class="admin-nav"><a href="<?= SITE_URL ?>/" target="_blank">Сайт</a><a href="<?= SITE_URL ?>/?page=logout">Выйти</a></nav>
</div></header>
<div class="admin-wrap">
<aside class="admin-sidebar">
  <a href="index.php">📊 Дашборд</a>
  <a href="products.php">📦 Товары</a>
  <a href="categories.php">📁 Категории</a>
  <a href="tags.php">🏷 Теги</a>
  <a href="orders.php">🛒 Заказы</a>
  <a href="users.php">👥 Пользователи</a>
  <a href="reviews.php">⭐ Отзывы</a>
  <div style="border-top:1px solid var(--border);margin:10px 0;padding-top:10px">
    <strong style="color:var(--gold);padding:0 16px;display:block;margin-bottom:8px">CRM</strong>
    <a href="leads.php">👤 Лиды</a>
    <a href="interactions.php">💬 Взаимодействия</a>
    <a href="tasks.php" class="active">📋 Задачи</a>
  </div>
</aside>
<div class="admin-content">
<?php foreach ($flashes as $f): ?><div class="alert alert--<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>

<?php if (in_array($action, ['edit','new'])): ?>
  <h1><?= $task ? 'Редактировать задачу: '.e($task['title']) : 'Новая задача' ?></h1>
  <form method="post" class="form-box">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="task_id" value="<?= (int)($task['id'] ?? 0) ?>">
    <div class="form-group">
      <label>Заголовок *</label>
      <input type="text" name="title" required value="<?= e($task['title'] ?? '') ?>">
    </div>
    <div class="form-group">
      <label>Описание</label>
      <textarea name="description" rows="4"><?= e($task['description'] ?? '') ?></textarea>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Статус</label>
        <select name="status">
          <?php foreach(['pending','in_progress','completed'] as $s): ?>
            <option value="<?= $s ?>" <?= ($task['status'] ?? 'pending') === $s ? 'selected' : '' ?>>
              <?= $s === 'pending' ? 'Ожидает' : ($s === 'in_progress' ? 'В работе' : 'Завершено') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Приоритет</label>
        <select name="priority">
          <?php foreach(['low','medium','high'] as $p): ?>
            <option value="<?= $p ?>" <?= ($task['priority'] ?? 'medium') === $p ? 'selected' : '' ?>>
              <?= $p === 'low' ? 'Низкий' : ($p === 'medium' ? 'Средний' : 'Высокий') ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Срок</label>
        <input type="datetime-local" name="due_date" value="<?= $task['due_date'] ? date('Y-m-d\TH:i', strtotime($task['due_date'])) : '' ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Лид</label>
        <select name="lead_id">
          <option value="">— Не привязан —</option>
          <?php foreach ($leads as $l): ?>
            <option value="<?= (int)$l['id'] ?>" <?= (int)($task['lead_id'] ?? 0) === (int)$l['id'] ? 'selected' : '' ?>>
              <?= e($l['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Пользователь</label>
        <select name="user_id">
          <option value="">— Не привязан —</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= (int)($task['user_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
              <?= e($u['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Назначена</label>
        <select name="assigned_to">
          <option value="">— Не назначено —</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= (int)($task['assigned_to'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
              <?= e($u['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div style="display:flex;gap:12px">
      <button type="submit" class="btn btn-primary">💾 Сохранить</button>
      <a href="tasks.php" class="btn btn-outline">Отмена</a>
    </div>
  </form>

<?php else: ?>
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <h1>Задачи</h1>
    <a href="tasks.php?action=new" class="btn btn-primary">+ Добавить задачу</a>
  </div>

  <div style="background:var(--dark2);border:1px solid var(--border);border-radius:6px;overflow:hidden">
    <table class="data-table">
      <thead><tr><th>ID</th><th>Задача</th><th>Статус</th><th>Приоритет</th><th>Срок</th><th>Привязка</th><th>Назначена</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($tasks as $t): ?>
        <tr>
          <td>#<?= (int)$t['id'] ?></td>
          <td><strong style="color:var(--white)"><?= e($t['title']) ?></strong></td>
          <td><span class="status-badge status-<?= e($t['status']) ?>">
            <?= $t['status'] === 'pending' ? '⏳ Ожидает' : ($t['status'] === 'in_progress' ? '🔄 В работе' : '✅ Завершено') ?>
          </span></td>
          <td><span style="color:<?= $t['priority'] === 'high' ? '#ff6b6b' : ($t['priority'] === 'medium' ? '#ffd93d' : '#6bcb77') ?>">
            <?= $t['priority'] === 'low' ? '↓ Низкий' : ($t['priority'] === 'medium' ? '→ Средний' : '↑ Высокий') ?>
          </span></td>
          <td><?= $t['due_date'] ? date('d.m.Y H:i', strtotime($t['due_date'])) : '—' ?></td>
          <td>
            <?php if ($t['lead_name']): ?>👤 <?= e($t['lead_name']) ?><?php endif; ?>
            <?php if ($t['user_name']): ?>🧑 <?= e($t['user_name']) ?><?php endif; ?>
            <?php if (!$t['lead_name'] && !$t['user_name']): ?>—<?php endif; ?>
          </td>
          <td><?= e($t['assigned_name'] ?? '—') ?></td>
          <td style="white-space:nowrap">
            <a href="tasks.php?action=edit&id=<?= (int)$t['id'] ?>" class="btn btn-outline btn-sm">✏</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Удалить задачу?')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="task_id" value="<?= (int)$t['id'] ?>">
              <button type="submit" class="btn btn-danger btn-sm">✕</button>
            </form>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php if ($pag['pages'] > 1): ?>
  <div class="pagination" style="margin-top:20px">
    <?php for ($i=1;$i<=$pag['pages'];$i++): ?>
      <a href="tasks.php?p=<?= $i ?>" class="<?= $page===$i?'current':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
<?php endif; ?>
</div></div>
</body></html>
