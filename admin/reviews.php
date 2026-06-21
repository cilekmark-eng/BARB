<?php
declare(strict_types=1);
require_once __DIR__ . '/../public/config.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    $rid = (int)($_POST['review_id'] ?? 0);
    if (in_array($act, ['approve','reject','delete']) && $rid) {
        if ($act === 'delete') {
            db()->prepare("DELETE FROM reviews WHERE id=:id")->execute([':id'=>$rid]);
            flash('success','Отзыв удалён');
        } else {
            $status = $act === 'approve' ? 'approved' : 'rejected';
            db()->prepare("UPDATE reviews SET status=:s WHERE id=:id")->execute([':s'=>$status,':id'=>$rid]);
            flash('success','Статус обновлён');
        }
    }
    header('Location: reviews.php');
    exit;
}

$filter = $_GET['status'] ?? 'pending';
$allowed = ['pending','approved','rejected'];
if (!in_array($filter, $allowed)) $filter = 'pending';

$st = db()->prepare(
    "SELECT r.*,p.name AS product_name,p.slug AS product_slug,u.name AS user_name
     FROM reviews r
     LEFT JOIN products p ON p.id=r.product_id
     LEFT JOIN users u ON u.id=r.user_id
     WHERE r.status=:s ORDER BY r.created_at DESC"
);
$st->execute([':s'=>$filter]);
$reviews = $st->fetchAll();
$flashes = get_flash();
?>
<!DOCTYPE html>
<html lang="ru"><head><meta charset="UTF-8"><title>Отзывы — Админ</title>
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
  <a href="orders.php">🛒 Заказы</a>
  <a href="users.php">👥 Пользователи</a>
  <a href="reviews.php" class="active">⭐ Отзывы</a>
  <div style="border-top:1px solid var(--border);margin:10px 0;padding-top:10px">
    <strong style="color:var(--gold);padding:0 16px;display:block;margin-bottom:8px">CRM</strong>
    <a href="leads.php">👤 Лиды</a>
    <a href="interactions.php">💬 Взаимодействия</a>
    <a href="tasks.php">📋 Задачи</a>
  </div>
</aside>
<div class="admin-content">
<?php foreach ($flashes as $f): ?><div class="alert alert--<?= e($f['type']) ?>"><?= e($f['msg']) ?></div><?php endforeach; ?>
<h1>Отзывы</h1>
<div style="display:flex;gap:8px;margin-bottom:20px">
  <?php foreach (['pending'=>'На модерации','approved'=>'Одобренные','rejected'=>'Отклонённые'] as $k=>$v): ?>
    <a href="reviews.php?status=<?= $k ?>" class="btn <?= $filter===$k?'btn-primary':'btn-outline' ?>"><?= $v ?></a>
  <?php endforeach; ?>
</div>
<div style="background:var(--dark2);border:1px solid var(--border);border-radius:6px;overflow:hidden">
  <table class="data-table">
    <thead><tr><th>ID</th><th>Товар</th><th>Автор</th><th>Оценка</th><th>Текст</th><th>Дата</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($reviews as $r): ?>
      <tr>
        <td><?= (int)$r['id'] ?></td>
        <td><a href="<?= SITE_URL ?>/?page=product&slug=<?= e($r['product_slug']) ?>"><?= e($r['product_name']) ?></a></td>
        <td><?= e($r['user_name']??'Аноним') ?></td>
        <td style="color:var(--gold)"><?= str_repeat('★',(int)$r['rating']) ?></td>
        <td style="max-width:240px;overflow:hidden;text-overflow:ellipsis"><?= e($r['text']) ?></td>
        <td style="color:var(--text2)"><?= date('d.m.Y',strtotime($r['created_at'])) ?></td>
        <td style="white-space:nowrap">
          <?php if ($r['status']==='pending'): ?>
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="approve"><input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-primary btn-sm">✓</button></form>
            <form method="post" style="display:inline"><?= csrf_field() ?><input type="hidden" name="action" value="reject"><input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-outline btn-sm">✕</button></form>
          <?php endif; ?>
          <form method="post" style="display:inline" onsubmit="return confirm('Удалить?')"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="review_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger btn-sm">🗑</button></form>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php if (!$reviews): ?><tr><td colspan="7" style="text-align:center;color:var(--text2);padding:24px">Нет отзывов</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>
</div></div>
</body></html>
