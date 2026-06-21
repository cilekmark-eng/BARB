<?php
declare(strict_types=1);
require_once __DIR__ . '/../public/config.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/auth.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $act = $_POST['action'] ?? '';
    if ($act === 'save') {
        $name = trim($_POST['name'] ?? '');
        $slug = make_slug($_POST['slug'] ?? $name);
        $tid  = (int)($_POST['tag_id'] ?? 0);
        if ($tid) {
            db()->prepare("UPDATE tags SET name=:n,slug=:s WHERE id=:id")->execute([':n'=>$name,':s'=>$slug,':id'=>$tid]);
        } else {
            db()->prepare("INSERT INTO tags (name,slug) VALUES (:n,:s)")->execute([':n'=>$name,':s'=>$slug]);
        }
        flash('success','Тег сохранён');
    }
    if ($act === 'delete') {
        db()->prepare("DELETE FROM tags WHERE id=:id")->execute([':id'=>(int)$_POST['tag_id']]);
        flash('success','Тег удалён');
    }
    header('Location: tags.php');
    exit;
}

$tags = get_tags();
$edit = null;
if (isset($_GET['edit'])) {
    $st = db()->prepare("SELECT * FROM tags WHERE id=:id"); $st->execute([':id'=>(int)$_GET['edit']]);
    $edit = $st->fetch();
}
$flashes = get_flash();
?>
<!DOCTYPE html>
<html lang="ru"><head><meta charset="UTF-8"><title>Теги — Админ</title>
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
  <a href="tags.php" class="active">🏷 Теги</a>
  <a href="orders.php">🛒 Заказы</a>
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
<div style="display:grid;grid-template-columns:1fr 320px;gap:24px">
  <div><h1>Теги</h1>
    <div style="background:var(--dark2);border:1px solid var(--border);border-radius:6px;overflow:hidden">
      <table class="data-table">
        <thead><tr><th>ID</th><th>Название</th><th>Slug</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($tags as $t): ?>
          <tr>
            <td><?= (int)$t['id'] ?></td><td><?= e($t['name']) ?></td><td style="color:var(--text2)"><?= e($t['slug']) ?></td>
            <td><a href="tags.php?edit=<?= (int)$t['id'] ?>" class="btn btn-outline btn-sm">✏</a>
              <form method="post" style="display:inline" onsubmit="return confirm('Удалить?')">
                <?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="tag_id" value="<?= (int)$t['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">✕</button>
              </form>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="form-box"><h2><?= $edit?'Редактировать':'Добавить тег' ?></h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="save"><input type="hidden" name="tag_id" value="<?= (int)($edit['id']??0) ?>">
      <div class="form-group"><label>Название *</label><input type="text" name="name" required value="<?= e($edit['name']??'') ?>"></div>
      <div class="form-group"><label>Slug</label><input type="text" name="slug" value="<?= e($edit['slug']??'') ?>"></div>
      <button type="submit" class="btn btn-primary">Сохранить</button>
      <?php if ($edit): ?><a href="tags.php" class="btn btn-outline" style="margin-left:8px">Отмена</a><?php endif; ?>
    </form>
  </div>
</div>
</div></div>
</body></html>
