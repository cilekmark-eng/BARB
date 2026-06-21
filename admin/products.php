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
            ':slug'        => make_slug(trim($_POST['slug'] ?? $_POST['name'] ?? '')),
            ':description' => trim($_POST['description'] ?? ''),
            ':price'       => max(0, (float)($_POST['price'] ?? 0)),
            ':discount'    => max(0, min(100, (int)($_POST['discount'] ?? 0))),
            ':stock'       => max(0, (int)($_POST['stock'] ?? 0)),
            ':category_id' => $_POST['category_id'] ? (int)$_POST['category_id'] : null,
            ':is_active'   => isset($_POST['is_active']) ? 1 : 0,
        ];
        $pid = (int)($_POST['product_id'] ?? 0);
        if ($pid) {
            $data[':id'] = $pid;
            db()->prepare("UPDATE products SET name=:name,slug=:slug,description=:description,price=:price,
                discount=:discount,stock=:stock,category_id=:category_id,is_active=:is_active WHERE id=:id")
                ->execute($data);
        } else {
            db()->prepare("INSERT INTO products (name,slug,description,price,discount,stock,category_id,is_active)
                VALUES (:name,:slug,:description,:price,:discount,:stock,:category_id,:is_active)")
                ->execute($data);
            $pid = (int)db()->lastInsertId();
        }

        // Tags
        db()->prepare("DELETE FROM product_tags WHERE product_id=:p")->execute([':p'=>$pid]);
        if (!empty($_POST['tags'])) {
            $stTag = db()->prepare("INSERT IGNORE INTO product_tags (product_id,tag_id) VALUES (:p,:t)");
            foreach ((array)$_POST['tags'] as $tId) {
                $stTag->execute([':p'=>$pid, ':t'=>(int)$tId]);
            }
        }

        // Images
        if (!empty($_FILES['images']['name'][0])) {
            foreach ($_FILES['images']['name'] as $i => $fname) {
                if (!$fname) continue;
                $file = [
                    'name'     => $_FILES['images']['name'][$i],
                    'type'     => $_FILES['images']['type'][$i],
                    'tmp_name' => $_FILES['images']['tmp_name'][$i],
                    'error'    => $_FILES['images']['error'][$i],
                    'size'     => $_FILES['images']['size'][$i],
                ];
                $saved = upload_product_image($file, $pid);
                if ($saved) {
                    db()->prepare("INSERT INTO product_images (product_id,filename,sort_order) VALUES (:p,:f,:s)")
                         ->execute([':p'=>$pid,':f'=>$saved,':s'=>$i]);
                }
            }
        }

        flash('success', 'Товар сохранён');
        header('Location: products.php');
        exit;
    }

    if ($act === 'delete_image') {
        $imgId = (int)($_POST['img_id'] ?? 0);
        $st = db()->prepare("SELECT * FROM product_images WHERE id=:id");
        $st->execute([':id'=>$imgId]);
        $img = $st->fetch();
        if ($img) {
            $file = UPLOAD_DIR . $img['product_id'] . '/' . $img['filename'];
            if (file_exists($file)) @unlink($file);
            db()->prepare("DELETE FROM product_images WHERE id=:id")->execute([':id'=>$imgId]);
        }
        flash('success', 'Фото удалено');
        header('Location: products.php?action=edit&id=' . ($img['product_id'] ?? 0));
        exit;
    }

    if ($act === 'delete') {
        $pid = (int)($_POST['product_id'] ?? 0);
        db()->prepare("UPDATE products SET is_active=0 WHERE id=:id")->execute([':id'=>$pid]);
        flash('success', 'Товар деактивирован');
        header('Location: products.php');
        exit;
    }
}

// ── Load data for edit ─────────────────────────────────────
$prod = null;
$prodTags  = [];
$prodImages = [];
if ($id && in_array($action, ['edit','delete'])) {
    $st = db()->prepare("SELECT * FROM products WHERE id=:id");
    $st->execute([':id'=>$id]);
    $prod = $st->fetch();
    if ($prod) {
        $prodTags   = array_column(get_product_tags($id), 'id');
        $prodImages = get_product_images($id);
    }
}

$cats = get_categories();
$tags = get_tags();

// Pagination for list
$page = max(1,(int)($_GET['p']??1));
$per  = 20;
$total = (int)db()->query("SELECT COUNT(*) FROM products")->fetchColumn();
$pag  = paginate($total, $page, $per);

$products = db()->prepare(
    "SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id
     ORDER BY p.id DESC LIMIT {$pag['per']} OFFSET {$pag['offset']}"
);
$products->execute();
$products = $products->fetchAll();

// Flash
$flashes = get_flash();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8"><title>Товары — Админ</title>
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
  <a href="products.php" class="active">📦 Товары</a>
  <a href="categories.php">📁 Категории</a>
  <a href="tags.php">🏷 Теги</a>
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

<?php if (in_array($action, ['edit','new'])): ?>
  <h1><?= $prod ? 'Редактировать: '.e($prod['name']) : 'Новый товар' ?></h1>
  <form method="post" enctype="multipart/form-data" class="form-box">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="product_id" value="<?= (int)($prod['id'] ?? 0) ?>">
    <div class="form-row">
      <div class="form-group">
        <label>Название *</label>
        <input type="text" name="name" required value="<?= e($prod['name'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Slug (URL)</label>
        <input type="text" name="slug" value="<?= e($prod['slug'] ?? '') ?>">
      </div>
    </div>
    <div class="form-group">
      <label>Описание</label>
      <textarea name="description" rows="5"><?= e($prod['description'] ?? '') ?></textarea>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Цена (₽) *</label>
        <input type="number" name="price" step="0.01" min="0" required value="<?= e($prod['price'] ?? '') ?>">
      </div>
      <div class="form-group">
        <label>Скидка (%)</label>
        <input type="number" name="discount" min="0" max="100" value="<?= e($prod['discount'] ?? 0) ?>">
      </div>
      <div class="form-group">
        <label>Остаток (шт)</label>
        <input type="number" name="stock" min="0" value="<?= e($prod['stock'] ?? 0) ?>">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label>Категория</label>
        <select name="category_id">
          <option value="">— Без категории —</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (int)($prod['category_id'] ?? 0) === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label><input type="checkbox" name="is_active" value="1" <?= ($prod['is_active'] ?? 1) ? 'checked' : '' ?>> Активен</label>
      </div>
    </div>

    <div class="form-group">
      <label>Теги</label>
      <div style="display:flex;flex-wrap:wrap;gap:10px">
        <?php foreach ($tags as $t): ?>
          <label style="display:flex;align-items:center;gap:6px">
            <input type="checkbox" name="tags[]" value="<?= (int)$t['id'] ?>" <?= in_array((int)$t['id'], $prodTags) ? 'checked' : '' ?>>
            <?= e($t['name']) ?>
          </label>
        <?php endforeach; ?>
      </div>
    </div>

    <?php if ($prodImages): ?>
    <div class="form-group">
      <label>Текущие фото</label>
      <div class="upload-preview">
        <?php foreach ($prodImages as $img): ?>
        <div>
          <img src="<?= e(product_image_url((int)$prod['id'], $img['filename'])) ?>" alt="">
          <form method="post" style="display:inline">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete_image">
            <input type="hidden" name="img_id" value="<?= (int)$img['id'] ?>">
            <button type="submit" class="img-del-btn">✕ Удалить</button>
          </form>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endif; ?>

    <div class="form-group">
      <label>Загрузить фото (jpg/png/webp, до 2MB, можно несколько)</label>
      <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple>
    </div>

    <div style="display:flex;gap:12px">
      <button type="submit" class="btn btn-primary">💾 Сохранить</button>
      <a href="products.php" class="btn btn-outline">Отмена</a>
    </div>
  </form>

<?php else: ?>
  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px">
    <h1>Товары</h1>
    <a href="products.php?action=new" class="btn btn-primary">+ Добавить товар</a>
  </div>

  <div style="background:var(--dark2);border:1px solid var(--border);border-radius:6px;overflow:hidden">
    <table class="data-table">
      <thead><tr><th>ID</th><th>Название</th><th>Категория</th><th>Цена</th><th>Скидка</th><th>Склад</th><th>Активен</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($products as $p): ?>
        <tr>
          <td><?= (int)$p['id'] ?></td>
          <td><strong style="color:var(--white)"><?= e($p['name']) ?></strong></td>
          <td><?= e($p['cat_name'] ?? '—') ?></td>
          <td><?= fmt_price((float)$p['price']) ?></td>
          <td><?= $p['discount'] > 0 ? '<span class="badge badge-discount">-'.(int)$p['discount'].'%</span>' : '—' ?></td>
          <td><?= (int)$p['stock'] ?></td>
          <td><?= $p['is_active'] ? '✅' : '❌' ?></td>
          <td style="white-space:nowrap">
            <a href="products.php?action=edit&id=<?= (int)$p['id'] ?>" class="btn btn-outline btn-sm">✏</a>
            <form method="post" style="display:inline" onsubmit="return confirm('Деактивировать?')">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="product_id" value="<?= (int)$p['id'] ?>">
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
      <a href="products.php?p=<?= $i ?>" class="<?= $page===$i?'current':'' ?>"><?= $i ?></a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
<?php endif; ?>
</div></div>
</body></html>
