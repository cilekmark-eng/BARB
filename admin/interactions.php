<?php
declare(strict_types=1);
require_once __DIR__ . '/../public/config.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/auth.php';
require_admin();

$action = $_GET['action'] ?? 'list';
$id     = (int)($_GET['id'] ?? 0);
$leadId = (int)($_GET['lead_id'] ?? 0);

// ── POST handlers ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';

    if ($act === 'save') {
        $data = [
            ':lead_id'     => $_POST['lead_id'] ? (int)$_POST['lead_id'] : null,
            ':user_id'     => $_POST['user_id'] ? (int)$_POST['user_id'] : null,
            ':type'        => $_POST['type'] ?? 'note',
            ':description' => trim($_POST['description'] ?? ''),
            ':created_by'  => (int)$_SESSION['user_id'],
        ];
        $iid = (int)($_POST['interaction_id'] ?? 0);
        if ($iid) {
            $data[':id'] = $iid;
            db()->prepare("UPDATE crm_interactions SET lead_id=:lead_id,user_id=:user_id,type=:type,description=:description WHERE id=:id")
                ->execute($data);
        } else {
            db()->prepare("INSERT INTO crm_interactions (lead_id,user_id,type,description,created_by)
                VALUES (:lead_id,:user_id,:type,:description,:created_by)")
                ->execute($data);
        }
        flash('success', 'Взаимодействие сохранено');
        header('Location: interactions.php' . ($leadId ? '?lead_id=' . $leadId : ''));
        exit;
    }

    if ($act === 'delete') {
        $iid = (int)($_POST['interaction_id'] ?? 0);
        db()->prepare("DELETE FROM crm_interactions WHERE id=:id")->execute([':id'=>$iid]);
        flash('success', 'Взаимодействие удалено');
        header('Location: interactions.php' . ($leadId ? '?lead_id=' . $leadId : ''));
        exit;
    }
}

// ── Load data for edit ─────────────────────────────────────
$interaction = null;
if ($id && in_array($action, ['edit','delete'])) {
    $st = db()->prepare("SELECT * FROM crm_interactions WHERE id=:id");
    $st->execute([':id'=>$id]);
    $interaction = $st->fetch();
}

$leads = db()->query("SELECT id, name FROM crm_leads ORDER BY name")->fetchAll();
$users = db()->query("SELECT id, name, email FROM users ORDER BY name")->fetchAll();

// Pagination for list
$page = max(1,(int)($_GET['p']??1));
$per  = 20;
$where = $leadId ? " WHERE i.lead_id = {$leadId}" : '';
$total = (int)db()->query("SELECT COUNT(*) FROM crm_interactions i{$where}")->fetchColumn();
$pag  = paginate($total, $page, $per);

$interactions = db()->prepare(
    "SELECT i.*, l.name AS lead_name, u.name AS user_name, c.name AS creator_name 
     FROM crm_interactions i 
     LEFT JOIN crm_leads l ON l.id=i.lead_id 
     LEFT JOIN users u ON u.id=i.user_id 
     LEFT JOIN users c ON c.id=i.created_by 
     {$where}
     ORDER BY i.created_at DESC LIMIT {$pag['per']} OFFSET {$pag['offset']}"
);
$interactions->execute();
$interactions = $interactions->fetchAll();

$flashes = get_flash();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8"><title>Взаимодействия — CRM Админ</title>
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
    <a href="interactions.php" class="active">💬 Взаимодействия</a>
    <a href="tasks.php">📋 Задачи</a>
  </div>
</aside>
<div class="admin-content">
<?php foreach ($flashes as $f): ?><div class="alert alert--<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>

<?php if (in_array($action, ['edit','new'])): ?>
  <h1><?= $interaction ? 'Редактировать взаимодействие' : 'Новое взаимодействие' ?></h1>
  <form method="post" class="form-box">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="interaction_id" value="<?= (int)($interaction['id'] ?? 0) ?>">
    <div class="form-row">
      <div class="form-group">
        <label>Лид</label>
        <select name="lead_id">
          <option value="">— Не привязан —</option>
          <?php foreach ($leads as $l): ?>
            <option value="<?= (int)$l['id'] ?>" <?= (int)($interaction['lead_id'] ?? $leadId) === (int)$l['id'] ? 'selected' : '' ?>>
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
            <option value="<?= (int)$u['id'] ?>" <?= (int)($interaction['user_id'] ?? 0) === (int)$u['id'] ? 'selected' : '' ?>>
              <?= e($u['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Тип</label>
        <select name="type">
          <?php foreach(['call','meeting','email','note'] as $t): ?>
            <option value="<?= $t ?>" <?= ($interaction['type'] ?? 'note') === $t ? 'selected' : '' ?>>
              <?= $t === 'call' ? 'Звонок' : ($t === 'meeting' ? 'Встреча' : ($t === 'email' ? 'Письмо' : 'Заметка')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <div class="form-group">
      <label>Описание *</label>
      <textarea name="description" rows="5" required><?= e($interaction['description'] ?? '') ?></textarea>
    </div>
    <div style="display:flex;gap:12px">
      <button type="submit" class="btn btn-primary">💾 Сохранить</button>
      <a href="interactions.php<?= $leadId ? '?lead_id=' . $leadId : '' ?>" class="btn btn-outline">Отмена</a>
    </div>
  </form>

<?php else: ?>
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <h1>Взаимодействия<?= $leadId ? ' для лида #' . $leadId : '' ?></h1>
    <a href="interactions.php?action=new<?= $leadId ? '&lead_id=' . $leadId : '' ?>" class="btn btn-primary">+ Добавить</a>
  </div>

  <div style="background:var(--dark2);border:1px solid var(--border);border-radius:6px;overflow:hidden">
    <table class="data-table">
      <thead><tr><th>ID</th><th>Тип</th><th>Лид/Пользователь</th><th>Описание</th><th>Кем создано</th><th>Дата</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($interactions as $i): ?>
        <tr>
          <td>#<?= (int)$i['id'] ?></td>
          <td><span class="status-badge status-<?= e($i['type']) ?>">
            <?= $i['type'] === 'call' ? '📞 Звонок' : ($i['type'] === 'meeting' ? '🤝 Встреча' : ($i['type'] === 'email' ? '✉️ Письмо' : '📝 Заметка')) ?>
          </span></td>
          <td>
            <?php if ($i['lead_name']): ?>
              <strong style="color:var(--white)"><?= e($i['lead_name']) ?></strong>
            <?php elseif ($i['user_name']): ?>
              <strong style="color:var(--white)"><?= e($i['user_name']) ?></strong>
            <?php else: ?>
              —
            <?php endif; ?>
          </td>
          <td style="max-width:300px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= e($i['description']) ?></td>
          <td><?= e($i['creator_name']) ?></td>
          <td><?= date('d.m.Y H:i', strtotime($i['created_at'])) ?></td>
          <td style="white-space:nowrap">
            <a href="interactions.php?action=edit&id=<?= (int)$i['id'] ?><?= $leadId ? '&lead_id=' . $leadId : '' ?>" class="btn btn-outline btn-sm">✏</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Удалить?')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="interaction_id" value="<?= (int)$i['id'] ?>">
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
      <a href="interactions.php?p=<?= $i ?><?= $leadId ? '&lead_id=' . $leadId : '' ?>" class="<?= $page===$i?'current':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
<?php endif; ?>
</div></div>
</body></html>
