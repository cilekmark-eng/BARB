<?php
/**
 * admin/index.php — Admin dashboard
 */
declare(strict_types=1);

require_once __DIR__ . '/../public/config.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/auth.php';

require_admin();

// Stats
$stats = [
    'orders'   => db()->query("SELECT COUNT(*) FROM orders")->fetchColumn(),
    'revenue'  => db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status!='canceled'")->fetchColumn(),
    'products' => db()->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn(),
    'users'    => db()->query("SELECT COUNT(*) FROM users")->fetchColumn(),
];

// CRM stats (gracefully handle missing tables)
try {
    $crmStats = [
        'leads'        => db()->query("SELECT COUNT(*) FROM crm_leads")->fetchColumn(),
        'new_leads'    => db()->query("SELECT COUNT(*) FROM crm_leads WHERE status='new'")->fetchColumn(),
        'tasks'        => db()->query("SELECT COUNT(*) FROM crm_tasks WHERE status!='completed'")->fetchColumn(),
        'interactions' => db()->query("SELECT COUNT(*) FROM crm_interactions")->fetchColumn(),
    ];
} catch (\Throwable) {
    $crmStats = ['leads'=>0, 'new_leads'=>0, 'tasks'=>0, 'interactions'=>0];
}

$latestOrders = db()->query(
    "SELECT o.*, u.name AS user_name FROM orders o LEFT JOIN users u ON u.id=o.user_id ORDER BY o.created_at DESC LIMIT 10"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Админ-панель — <?= SITE_NAME ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Bebas+Neue&family=Nunito+Sans:wght@400;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/css/style.css">
</head>
<body>

<header class="admin-header">
  <div class="admin-header-inner">
    <div class="admin-logo">✂ BarberStore Admin</div>
    <nav class="admin-nav">
      <a href="<?= SITE_URL ?>/" target="_blank">Перейти на сайт</a>
      <a href="<?= SITE_URL ?>/?page=logout">Выйти</a>
    </nav>
  </div>
</header>

<div class="admin-wrap">
  <aside class="admin-sidebar">
  <a href="index.php" class="active">📊 Дашборд</a>
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
    <a href="tasks.php">📋 Задачи</a>
  </div>
</aside>

  <div class="admin-content">
    <h1>Дашборд</h1>

    <div class="admin-stats">
      <div class="stat-card">
        <div class="stat-val"><?= (int)$stats['orders'] ?></div>
        <div class="stat-lbl">Заказов</div>
      </div>
      <div class="stat-card">
        <div class="stat-val"><?= fmt_price((float)$stats['revenue']) ?></div>
        <div class="stat-lbl">Выручка</div>
      </div>
      <div class="stat-card">
        <div class="stat-val"><?= (int)$stats['products'] ?></div>
        <div class="stat-lbl">Товаров</div>
      </div>
      <div class="stat-card">
        <div class="stat-val"><?= (int)$stats['users'] ?></div>
        <div class="stat-lbl">Пользователей</div>
      </div>
    </div>

    <h2 style="font-family:var(--font-head);color:var(--gold);margin:32px 0 16px;font-size:18px;letter-spacing:1px;text-transform:uppercase">CRM</h2>
    <div class="admin-stats">
      <div class="stat-card">
        <div class="stat-val"><?= (int)$crmStats['leads'] ?></div>
        <div class="stat-lbl">Всего лидов</div>
      </div>
      <div class="stat-card">
        <div class="stat-val"><?= (int)$crmStats['new_leads'] ?></div>
        <div class="stat-lbl">Новых лидов</div>
      </div>
      <div class="stat-card">
        <div class="stat-val"><?= (int)$crmStats['tasks'] ?></div>
        <div class="stat-lbl">Активных задач</div>
      </div>
      <div class="stat-card">
        <div class="stat-val"><?= (int)$crmStats['interactions'] ?></div>
        <div class="stat-lbl">Взаимодействий</div>
      </div>
    </div>

    <h2 style="font-family:var(--font-head);color:var(--white);margin-bottom:16px">Последние заказы</h2>
    <div style="background:var(--dark2);border:1px solid var(--border);border-radius:6px;overflow:hidden">
      <table class="data-table">
        <thead><tr><th>ID</th><th>Покупатель</th><th>Дата</th><th>Сумма</th><th>Статус</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($latestOrders as $o): ?>
          <tr>
            <td>#<?= (int)$o['id'] ?></td>
            <td><?= e($o['name']) ?><br><small style="color:var(--text2)"><?= e($o['email']) ?></small></td>
            <td><?= date('d.m.Y H:i', strtotime($o['created_at'])) ?></td>
            <td style="color:var(--gold);font-weight:700"><?= fmt_price((float)$o['total']) ?></td>
            <td><span class="status-badge status-<?= e($o['status']) ?>"><?= e($o['status']) ?></span></td>
            <td><a href="orders.php?id=<?= (int)$o['id'] ?>" class="btn btn-outline btn-sm">Открыть</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
</body></html>
