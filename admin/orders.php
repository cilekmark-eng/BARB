<?php
declare(strict_types=1);
require_once __DIR__ . '/../public/config.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/auth.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    if ($act === 'update_status') {
        $oid    = (int)($_POST['order_id'] ?? 0);
        $status = $_POST['status'] ?? '';
        $allowed = ['new','paid','shipped','delivered','canceled'];
        if (in_array($status, $allowed) && $oid) {
            db()->prepare("UPDATE orders SET status=:s WHERE id=:id")->execute([':s'=>$status,':id'=>$oid]);
            flash('success', 'Статус обновлён');
        }
        header('Location: orders.php?id='.$oid);
        exit;
    }
    if ($act === 'create_lead') {
        $oid = (int)($_POST['order_id'] ?? 0);
        try {
            $st  = db()->prepare("SELECT * FROM orders WHERE id=:id");
            $st->execute([':id'=>$oid]);
            $o = $st->fetch();
            if ($o) {
                $exists = db()->prepare("SELECT id FROM crm_leads WHERE email=:e AND (source='order' OR status='customer') LIMIT 1");
                $exists->execute([':e' => $o['email']]);
                if ($exists->fetch()) {
                    flash('info', 'Лид для этого клиента уже существует');
                } else {
                    $leadId = crm_create_lead_from_order($o);
                    flash('success', 'Лид #' . $leadId . ' создан из заказа');
                }
            }
        } catch (\Throwable $e) {
            flash('error', 'Ошибка CRM: ' . $e->getMessage());
        }
        header('Location: orders.php?id='.$oid);
        exit;
    }
}

$flashes = get_flash();

// Single order view
if ($id) {
    $st = db()->prepare("SELECT o.*,u.name AS user_name FROM orders o LEFT JOIN users u ON u.id=o.user_id WHERE o.id=:id");
    $st->execute([':id'=>$id]);
    $order = $st->fetch();
    $st2 = db()->prepare("SELECT oi.*,p.slug FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=:id");
    $st2->execute([':id'=>$id]);
    $items = $st2->fetchAll();
}

// List
$page = max(1,(int)($_GET['p']??1));
$total = (int)db()->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$pag = paginate($total,$page,20);
$st3 = db()->prepare("SELECT o.*,u.name AS uname FROM orders o LEFT JOIN users u ON u.id=o.user_id ORDER BY o.created_at DESC LIMIT {$pag['per']} OFFSET {$pag['offset']}");
$st3->execute();
$orders = $st3->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru"><head><meta charset="UTF-8"><title>Заказы — Админ</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Bebas+Neue&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/css/style.css">
</head><body>
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
  <a href="orders.php" class="active">🛒 Заказы</a>
  <a href="users.php">👥 Пользователи</a>
  <a href="reviews.php">⭐ Отзывы</a>
  <div style="border-top:1px solid var(--border);margin:10px 0;padding-top:10px">
    <strong style="color:var(--gold);padding:0 16px;display:block;margin-bottom:8px">CRM</strong>
    <a href="leads.php">👤 Лиды</a>
    <a href="interactions.php">💬 Взаимодействия</a>
    <a href="tasks.php">📋 Задачи</a>
  </div>
</aside>
<div class="admin-content">
<?php foreach ($flashes as $f): ?><div class="alert alert--<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>

<?php if ($id && !empty($order)): ?>
  <div style="margin-bottom:16px"><a href="orders.php" style="color:var(--text2)">← Все заказы</a></div>
  <h1>Заказ #<?= (int)$order['id'] ?></h1>
  <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;margin-bottom:20px">
    <div class="order-detail">
      <h3>Позиции</h3>
      <table class="data-table">
        <thead><tr><th>Товар</th><th>Цена</th><th>Кол-во</th><th>Сумма</th></tr></thead>
        <tbody>
          <?php foreach ($items as $oi): ?>
          <tr>
            <td><?= $oi['slug'] ? '<a href="'.SITE_URL.'/?page=product&slug='.e($oi['slug']).'">'.e($oi['name']).'</a>' : e($oi['name']) ?></td>
            <td><?= fmt_price((float)$oi['price']) ?></td>
            <td><?= (int)$oi['qty'] ?></td>
            <td><?= fmt_price((float)$oi['price']*$oi['qty']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <div style="text-align:right;font-size:20px;font-weight:700;color:var(--gold);margin-top:12px">Итого: <?= fmt_price((float)$order['total']) ?></div>
    </div>
    <div>
      <div class="form-box">
        <h3 style="color:var(--white);margin-bottom:16px">Клиент</h3>
        <p><strong><?= e($order['name']) ?></strong></p>
        <p style="color:var(--text2)"><?= e($order['email']) ?></p>
        <p style="color:var(--text2)"><?= e($order['phone']) ?></p>
        <p style="color:var(--text2);margin-top:8px"><?= e($order['address']) ?></p>
        <?php if ($order['comment']): ?><p style="color:var(--text2);margin-top:8px;font-style:italic"><?= e($order['comment']) ?></p><?php endif; ?>
        <hr style="border-color:var(--border);margin:16px 0">
        <form method="post">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="update_status">
          <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
          <div class="form-group">
            <label>Статус</label>
            <select name="status">
              <?php foreach (['new','paid','shipped','delivered','canceled'] as $s): ?>
                <option value="<?= $s ?>" <?= $order['status']===$s?'selected':'' ?>><?= $s ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary" style="width:100%">Обновить статус</button>
        </form>
        <hr style="border-color:var(--border);margin:16px 0">
        <?php
        $existingLead = false;
        try {
            $stLead = db()->prepare("SELECT id FROM crm_leads WHERE email=:e AND (source='order' OR status='customer') LIMIT 1");
            $stLead->execute([':e' => $order['email']]);
            $existingLead = $stLead->fetch();
        } catch (\Throwable) {}
        ?>
        <?php if ($existingLead): ?>
          <p style="margin-bottom:8px;color:var(--green);font-size:13px">✅ Клиент в CRM</p>
          <a href="leads.php?action=edit&id=<?= (int)$existingLead['id'] ?>" class="btn btn-outline btn-sm" style="width:100%;text-align:center">👤 Открыть лид</a>
        <?php else: ?>
          <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="create_lead">
            <input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>">
            <button type="submit" class="btn btn-primary" style="width:100%">➕ Создать лид в CRM</button>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

<?php else: ?>
  <h1>Заказы</h1>
  <div style="background:var(--dark2);border:1px solid var(--border);border-radius:6px;overflow:hidden">
    <table class="data-table">
      <thead><tr><th>ID</th><th>Покупатель</th><th>Дата</th><th>Сумма</th><th>Статус</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($orders as $o): ?>
        <tr>
          <td>#<?= (int)$o['id'] ?></td>
          <td><?= e($o['name']) ?><br><small style="color:var(--text2)"><?= e($o['email']) ?></small></td>
          <td><?= date('d.m.Y H:i',strtotime($o['created_at'])) ?></td>
          <td style="color:var(--gold);font-weight:700"><?= fmt_price((float)$o['total']) ?></td>
          <td><span class="status-badge status-<?= e($o['status']) ?>"><?= e($o['status']) ?></span></td>
          <td><a href="orders.php?id=<?= (int)$o['id'] ?>" class="btn btn-outline btn-sm">Открыть</a></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
</div></div>
</body></html>
