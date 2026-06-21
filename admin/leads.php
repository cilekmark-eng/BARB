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
            ':name'        => trim($_POST['name'] ?? ''),
            ':email'       => trim($_POST['email'] ?? '') ?: null,
            ':phone'       => trim($_POST['phone'] ?? '') ?: null,
            ':source'      => trim($_POST['source'] ?? '') ?: null,
            ':status'      => $_POST['status'] ?? 'new',
            ':notes'       => trim($_POST['notes'] ?? '') ?: null,
            ':assigned_to' => $_POST['assigned_to'] ? (int)$_POST['assigned_to'] : null,
        ];
        $lid = (int)($_POST['lead_id'] ?? 0);
        if ($lid) {
            $data[':id'] = $lid;
            db()->prepare("UPDATE crm_leads SET name=:name,email=:email,phone=:phone,source=:source,
                status=:status,notes=:notes,assigned_to=:assigned_to WHERE id=:id")
                ->execute($data);
        } else {
            db()->prepare("INSERT INTO crm_leads (name,email,phone,source,status,notes,assigned_to)
                VALUES (:name,:email,:phone,:source,:status,:notes,:assigned_to)")
                ->execute($data);
            $lid = (int)db()->lastInsertId();
        }
        flash('success', 'Лид сохранён');
        header('Location: leads.php');
        exit;
    }

    if ($act === 'delete') {
        $lid = (int)($_POST['lead_id'] ?? 0);
        db()->prepare("DELETE FROM crm_leads WHERE id=:id")->execute([':id'=>$lid]);
        flash('success', 'Лид удалён');
        header('Location: leads.php');
        exit;
    }
}

// ── Load data for edit ─────────────────────────────────────
$lead = null;
if ($id && in_array($action, ['edit','delete'])) {
    $st = db()->prepare("SELECT * FROM crm_leads WHERE id=:id");
    $st->execute([':id'=>$id]);
    $lead = $st->fetch();
}

$users = db()->query("SELECT id, name, email FROM users ORDER BY name")->fetchAll();

// Pagination for list
$page = max(1,(int)($_GET['p']??1));
$per  = 20;
$total = (int)db()->query("SELECT COUNT(*) FROM crm_leads")->fetchColumn();
$pag  = paginate($total, $page, $per);

$leads = db()->prepare(
    "SELECT l.*, u.name AS assigned_name FROM crm_leads l 
     LEFT JOIN users u ON u.id=l.assigned_to 
     ORDER BY l.created_at DESC LIMIT {$pag['per']} OFFSET {$pag['offset']}"
);
$leads->execute();
$leads = $leads->fetchAll();

$flashes = get_flash();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8"><title>Лиды — CRM Админ</title>
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
    <a href="leads.php" class="active">👤 Лиды</a>
    <a href="interactions.php">💬 Взаимодействия</a>
    <a href="tasks.php">📋 Задачи</a>
  </div>
</aside>
<div class="admin-content">
<?php foreach ($flashes as $f): ?><div class="alert alert--<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>

<?php if (in_array($action, ['edit','new'])): ?>
  <h1><?= $lead ? 'Редактировать лид: '.e($lead['name']) : 'Новый лид' ?></h1>
  <form method="post" class="form-box">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="lead_id" value="<?= (int)($lead['id'] ?? 0) ?>">
    <div class="form-row">
      <div class="form-group">
        <label>Имя *</label>
        <input type="text" name="name" required value="<?= e($lead['name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Email</label>
        <input type="email" name="email" value="<?= e($lead['email'] ?? '') ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Телефон</label>
        <input type="text" name="phone" value="<?= e($lead['phone'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Источник</label>
        <input type="text" name="source" placeholder="Сайт, реклама, рекомендация..." value="<?= e($lead['source'] ?? '') ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Статус</label>
        <select name="status">
          <?php foreach(['new','contacted','qualified','lost','customer'] as $s): ?>
            <option value="<?= $s ?>" <?= ($lead['status'] ?? 'new') === $s ? 'selected' : '' ?>>
              <?= $s === 'new' ? 'Новый' : ($s === 'contacted' ? 'Связались' : ($s === 'qualified' ? 'Квалифицирован' : ($s === 'lost' ? 'Потерян' : 'Клиент'))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>Ответственный</label>
        <select name="assigned_to">
          <option value="">— Не назначен —</option>
          <?php foreach ($users as $u): ?>
            <option value="<?= (int)$u['id'] ?>" <?= (int)($lead['assigned_to'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
              <?= e($u['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label>Заметки</label>
      <textarea name="notes" rows="4"><?= e($lead['notes'] ?? '') ?></textarea>
    </div>
    <div style="display:flex;gap:12px">
      <button type="submit" class="btn btn-primary">💾 Сохранить</button>
      <a href="leads.php" class="btn btn-outline">Отмена</a>
    </div>
  </form>

<?php else: ?>
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <h1>Лиды</h1>
    <a href="leads.php?action=new" class="btn btn-primary">+ Добавить лид</a>
  </div>

  <div style="background:var(--dark2);border:1px solid var(--border);border-radius:6px;overflow:hidden">
    <table class="data-table">
      <thead><tr><th>ID</th><th>Имя</th><th>Контакты</th><th>Источник</th><th>Статус</th><th>Ответственный</th><th>Дата</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($leads as $l): ?>
        <tr>
          <td>#<?= (int)$l['id'] ?></td>
          <td><strong style="color:var(--white)"><?= e($l['name']) ?></strong></td>
          <td>
            <?php if ($l['email']): ?><small><?= e($l['email']) ?></small><br><?php endif; ?>
            <?php if ($l['phone']): ?><small><?= e($l['phone']) ?></small><?php endif; ?>
          </td>
          <td><?= e($l['source'] ?? '—') ?></td>
          <td><span class="status-badge status-<?= e($l['status']) ?>">
            <?= $l['status'] === 'new' ? 'Новый' : ($l['status'] === 'contacted' ? 'Связались' : ($l['status'] === 'qualified' ? 'Квалифицирован' : ($l['status'] === 'lost' ? 'Потерян' : 'Клиент'))) ?>
          </span></td>
          <td><?= e($l['assigned_name'] ?? '—') ?></td>
          <td><?= date('d.m.Y', strtotime($l['created_at'])) ?></td>
          <td style="white-space:nowrap">
            <a href="leads.php?action=edit&id=<?= (int)$l['id'] ?>" class="btn btn-outline btn-sm">✏</a>
            <a href="interactions.php?lead_id=<?= (int)$l['id'] ?>" class="btn btn-outline btn-sm">💬</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Удалить лид?')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="lead_id" value="<?= (int)$l['id'] ?>">
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
      <a href="leads.php?p=<?= $i ?>" class="<?= $page===$i?'current':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
<?php endif; ?>
</div></div>
</body></html>
