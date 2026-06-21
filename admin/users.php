<?php
declare(strict_types=1);
require_once __DIR__ . '/../public/config.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    if ($act === 'set_role') {
        $uid  = (int)($_POST['user_id'] ?? 0);
        $role = $_POST['role'] ?? 'user';
        if (!in_array($role, ['user','admin'])) { flash('error','Недопустимая роль'); }
        elseif ($uid === (int)auth()['id']) { flash('error','Нельзя изменить свою роль'); }
        else {
            // Protect last admin
            if ($role !== 'admin') {
                $admCount = (int)db()->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
                $st = db()->prepare("SELECT role FROM users WHERE id=:id"); $st->execute([':id'=>$uid]);
                $curRole = $st->fetchColumn();
                if ($curRole === 'admin' && $admCount <= 1) { flash('error','Нельзя снять роль с последнего администратора'); goto end; }
            }
            db()->prepare("UPDATE users SET role=:r WHERE id=:id")->execute([':r'=>$role,':id'=>$uid]);
            flash('success','Роль обновлена');
        }
    }
    end:
    header('Location: users.php');
    exit;
}

$page = max(1,(int)($_GET['p']??1));
$total = (int)db()->query("SELECT COUNT(*) FROM users")->fetchColumn();
$pag = paginate($total,$page,20);
$st = db()->prepare("SELECT u.*,(SELECT COUNT(*) FROM orders WHERE user_id=u.id) AS order_cnt FROM users u ORDER BY u.created_at DESC LIMIT {$pag['per']} OFFSET {$pag['offset']}");
$st->execute();
$users = $st->fetchAll();
$flashes = get_flash();
?>
<!DOCTYPE html>
<html lang="ru"><head><meta charset="UTF-8"><title>Пользователи — Админ</title>
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
  <a href="users.php" class="active">👥 Пользователи</a>
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
<h1>Пользователи</h1>
<div style="background:var(--dark2);border:1px solid var(--border);border-radius:6px;overflow:hidden">
  <table class="data-table">
    <thead><tr><th>ID</th><th>Имя</th><th>Email</th><th>Роль</th><th>Заказов</th><th>Регистрация</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= (int)$u['id'] ?></td>
        <td><?= e($u['name']) ?></td>
        <td><?= e($u['email']) ?></td>
        <td><span class="status-badge <?= $u['role']==='admin'?'status-paid':'status-new' ?>"><?= e($u['role']) ?></span></td>
        <td><?= (int)$u['order_cnt'] ?></td>
        <td style="color:var(--text2)"><?= date('d.m.Y',strtotime($u['created_at'])) ?></td>
        <td>
          <?php if ((int)$u['id'] !== (int)auth()['id']): ?>
          <form method="post" style="display:flex;gap:6px">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="set_role">
            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <select name="role" style="padding:5px 8px;background:var(--dark3);border:1px solid var(--border);color:var(--white);border-radius:4px;font-size:12px">
              <option value="user"  <?= $u['role']==='user' ?'selected':'' ?>>user</option>
              <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>>admin</option>
            </select>
            <button type="submit" class="btn btn-outline btn-sm">✓</button>
          </form>
          <?php else: ?><span style="color:var(--text2);font-size:12px">Это вы</span><?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
</div></div>
</body></html>
