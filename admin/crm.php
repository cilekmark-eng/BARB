<?php
declare(strict_types=1);
require_once __DIR__ . '/../public/config.php';
require_once __DIR__ . '/../app/functions.php';
require_once __DIR__ . '/../app/auth.php';
require_admin();

// Real data from database
$realStats = [
    'orders'     => (int)(db()->query("SELECT COUNT(*) FROM orders")->fetchColumn()),
    'revenue'    => (float)(db()->query("SELECT COALESCE(SUM(total),0) FROM orders WHERE status!='canceled'")->fetchColumn()),
    'products'   => (int)(db()->query("SELECT COUNT(*) FROM products WHERE is_active=1")->fetchColumn()),
    'users'      => (int)(db()->query("SELECT COUNT(*) FROM users")->fetchColumn()),
];

$realOrders = db()->query(
    "SELECT o.*, u.name AS uname FROM orders o LEFT JOIN users u ON u.id=o.user_id ORDER BY o.created_at DESC LIMIT 20"
)->fetchAll();

$realProducts = db()->query(
    "SELECT p.*, c.name AS cat_name FROM products p LEFT JOIN categories c ON c.id=p.category_id WHERE p.is_active=1 ORDER BY p.views DESC"
)->fetchAll();

$realUsers = db()->query(
    "SELECT u.*,(SELECT COUNT(*) FROM orders WHERE user_id=u.id) AS order_cnt FROM users u ORDER BY u.created_at DESC"
)->fetchAll();

$realReviews = db()->query(
    "SELECT r.*,p.name AS product_name,p.slug AS product_slug,u.name AS user_name
     FROM reviews r LEFT JOIN products p ON p.id=r.product_id LEFT JOIN users u ON u.id=r.user_id
     ORDER BY r.created_at DESC"
)->fetchAll();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>BarberCRM — Админ-панель</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300..700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.30.0/dist/tabler-icons.min.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/css/style.css">
<style>
:root {
  --font-sans: 'Inter', system-ui, sans-serif;
  --gold: #c9a84c;
  --gold2: #e8c96e;
  --dark: #111;
  --dark2: #1a1a1a;
  --dark3: #222;
  --border: #2a2a2a;
  --text2: #888;
  --red: #cc3333;
  --green: #2a9d5c;
}
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:var(--font-sans);background:#0a0a0a;color:#d4cfc7;font-size:14px;overflow:hidden}
.app{display:flex;height:100vh}
.sidebar{width:200px;flex-shrink:0;background:var(--dark2);border-right:1px solid var(--border);display:flex;flex-direction:column}
.sidebar-logo{padding:20px 16px;border-bottom:1px solid var(--border)}
.sidebar-logo span{font-size:18px;color:var(--gold);font-weight:500;letter-spacing:1px}
.sidebar-logo small{display:block;font-size:10px;color:var(--text2);text-transform:uppercase;letter-spacing:2px;margin-top:2px}
.nav-item{display:flex;align-items:center;gap:10px;padding:10px 16px;cursor:pointer;color:var(--text2);font-size:13px;border-left:2px solid transparent;transition:.15s}
.nav-item:hover{color:#fff;background:rgba(201,168,76,.07)}
.nav-item.active{color:var(--gold);border-left-color:var(--gold);background:rgba(201,168,76,.08)}
.main{flex:1;display:flex;flex-direction:column;overflow:hidden}
.topbar{padding:14px 24px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;background:var(--dark)}
.topbar h1{font-size:18px;font-weight:500;color:#fff}
.content{flex:1;overflow-y:auto;padding:24px}
.stats-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:16px;margin-bottom:24px}
.stat-card{background:var(--dark2);border:1px solid var(--border);border-radius:8px;padding:16px}
.stat-val{font-size:26px;font-weight:500;color:#fff}
.stat-val.gold{color:var(--gold)}
.stat-lbl{font-size:12px;color:var(--text2);margin-top:4px;text-transform:uppercase;letter-spacing:.5px}
.panel{background:var(--dark2);border:1px solid var(--border);border-radius:8px;margin-bottom:20px;overflow:hidden}
.panel-head{padding:14px 18px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between}
.panel-head h2{font-size:14px;font-weight:500;color:#fff}
.panel-body{padding:18px}
table{width:100%;border-collapse:collapse}
th{text-align:left;font-size:11px;color:var(--text2);text-transform:uppercase;letter-spacing:.5px;padding:8px 12px;border-bottom:1px solid var(--border)}
td{padding:10px 12px;border-bottom:1px solid var(--border);font-size:13px;color:var(--text)}
tr:last-child td{border-bottom:none}
tr:hover td{background:rgba(255,255,255,.02)}
.td-name{color:#fff;font-weight:500}
.td-muted{color:var(--text2);font-size:12px}
.badge{display:inline-block;padding:2px 8px;border-radius:4px;font-size:11px;font-weight:500}
.badge-new{background:rgba(55,138,221,.15);color:#85b7eb}
.badge-paid{background:rgba(42,157,92,.15);color:#5dcaa5}
.badge-shipped{background:rgba(201,168,76,.15);color:var(--gold)}
.badge-delivered{background:rgba(97,220,97,.1);color:#6ddf6d}
.badge-canceled{background:rgba(204,51,51,.15);color:#f09595}
.badge-pending{background:rgba(201,168,76,.12);color:var(--gold)}
.badge-approved{background:rgba(42,157,92,.15);color:#5dcaa5}
.badge-rejected{background:rgba(204,51,51,.15);color:#f09595}
.btn{display:inline-flex;align-items:center;gap:6px;padding:6px 14px;border-radius:4px;font-size:12px;font-weight:500;cursor:pointer;border:1px solid var(--border);background:var(--dark3);color:var(--text2);transition:.15s;text-decoration:none}
.btn:hover{color:#fff;border-color:#555}
.btn-gold{background:rgba(201,168,76,.12);color:var(--gold);border-color:rgba(201,168,76,.3)}
.btn-gold:hover{background:var(--gold);color:#000}
.btn-sm{padding:4px 10px;font-size:11px}
.search-bar{display:flex;align-items:center;gap:8px;padding:12px 18px;border-bottom:1px solid var(--border);background:var(--dark3)}
.search-bar input{flex:1;background:var(--dark2);border:1px solid var(--border);border-radius:4px;padding:6px 10px;color:#fff;font-size:13px}
.search-bar input::placeholder{color:var(--text2)}
.search-bar select{background:var(--dark2);border:1px solid var(--border);border-radius:4px;padding:6px 10px;color:var(--text2);font-size:12px}
.two-col{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.section-title{font-size:13px;font-weight:500;color:#fff;margin-bottom:12px}
.back-link{display:inline-flex;align-items:center;gap:6px;color:var(--text2);font-size:13px;cursor:pointer;margin-bottom:18px}
.back-link:hover{color:var(--gold)}
.page{display:none}
.page.active{display:block}
.cat-tag{display:inline-block;padding:2px 8px;background:rgba(201,168,76,.1);color:var(--gold);border-radius:4px;font-size:11px;margin-right:4px}
.review-stars{color:var(--gold);font-size:14px;letter-spacing:1px}
.review-text{font-size:13px;color:var(--text2);margin-top:4px;font-style:italic}
.filter-row{display:flex;gap:8px;margin-bottom:14px}
.filter-btn{padding:5px 12px;border-radius:4px;font-size:12px;cursor:pointer;border:1px solid var(--border);background:var(--dark3);color:var(--text2);transition:.15s}
.filter-btn.active{border-color:var(--gold);color:var(--gold);background:rgba(201,168,76,.1)}
.products-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:14px;padding:18px}
.product-card{background:var(--dark3);border:1px solid var(--border);border-radius:6px;padding:14px}
.product-price{font-size:20px;font-weight:500;color:var(--gold);margin:8px 0 4px}
.product-stock{font-size:12px;color:var(--text2)}
.product-actions{display:flex;gap:6px;margin-top:10px}
.detail-grid{display:grid;grid-template-columns:1fr 280px;gap:20px}
.detail-row{display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid var(--border);font-size:13px}
.detail-row:last-child{border-bottom:none}
.detail-lbl{color:var(--text2)}
.detail-val{color:#fff;font-weight:500}
.status-select{background:var(--dark3);border:1px solid var(--border);border-radius:4px;padding:7px 10px;color:#fff;font-size:13px;width:100%}
.user-avatar{width:36px;height:36px;border-radius:50%;background:rgba(201,168,76,.15);display:flex;align-items:center;justify-content:center;font-size:13px;font-weight:500;color:var(--gold);flex-shrink:0}
.user-row{display:flex;align-items:center;gap:10px}
.toast{position:fixed;bottom:20px;right:20px;background:var(--green);color:#fff;padding:10px 18px;border-radius:6px;font-size:13px;z-index:999;opacity:0;transition:.3s;pointer-events:none}
.toast.show{opacity:1}
.progress-bar{height:4px;border-radius:2px;background:var(--border);overflow:hidden;margin-top:6px}
.progress-fill{height:100%;border-radius:2px;background:var(--gold)}
.alert{padding:10px 14px;border-radius:4px;font-size:13px;margin-bottom:14px}
.alert-success{background:rgba(42,157,92,.1);border:1px solid rgba(42,157,92,.3);color:#5dcaa5}
</style>
</head>
<body>

<?php
// Build JSON data from real database
$jsonOrders = [];
foreach ($realOrders as $o) {
    $st = db()->prepare("SELECT oi.*, p.name AS pname FROM order_items oi LEFT JOIN products p ON p.id=oi.product_id WHERE oi.order_id=:oid");
    $st->execute([':oid' => $o['id']]);
    $items = $st->fetchAll();
    $jsonOrders[] = [
        'id' => (int)$o['id'],
        'name' => $o['name'],
        'email' => $o['email'],
        'phone' => $o['phone'],
        'address' => $o['address'],
        'total' => (float)$o['total'],
        'status' => $o['status'],
        'date' => date('Y-m-d', strtotime($o['created_at'])),
        'items' => array_map(fn($i) => ['name' => $i['pname'] ?? $i['name'], 'qty' => (int)$i['qty'], 'price' => (float)$i['price'] * (int)$i['qty']], $items),
        'user_name' => $o['uname'] ?? null,
    ];
}

$jsonProducts = array_map(fn($p) => [
    'id' => (int)$p['id'],
    'name' => $p['name'],
    'cat' => $p['cat_name'] ?? '',
    'price' => (float)$p['price'],
    'discount' => (int)$p['discount'],
    'stock' => (int)$p['stock'],
    'views' => (int)$p['views'],
    'active' => (int)$p['is_active'],
], $realProducts);

$jsonUsers = array_map(fn($u) => [
    'id' => (int)$u['id'],
    'name' => $u['name'],
    'email' => $u['email'],
    'role' => $u['role'],
    'phone' => $u['phone'] ?? '',
    'orders' => (int)$u['order_cnt'],
    'date' => date('Y-m-d', strtotime($u['created_at'])),
], $realUsers);

$jsonReviews = array_map(fn($r) => [
    'id' => (int)$r['id'],
    'product' => $r['product_name'] ?? '',
    'user' => $r['user_name'] ?? 'Аноним',
    'rating' => (int)$r['rating'],
    'text' => $r['text'] ?? '',
    'status' => $r['status'] ?? 'pending',
], $realReviews);

$fmtRevenue = number_format($realStats['revenue'], 0, ',', ' ');
?>

<div class="app">
<aside class="sidebar">
  <div class="sidebar-logo">
    <span>✂ BarberCRM</span>
    <small>Admin Panel</small>
  </div>
  <nav style="flex:1;padding-top:8px">
    <div class="nav-item active" onclick="nav('dashboard',this)"><i class="ti ti-layout-dashboard"></i>Дашборд</div>
    <div class="nav-item" onclick="nav('orders',this)"><i class="ti ti-shopping-cart"></i>Заказы</div>
    <div class="nav-item" onclick="nav('products',this)"><i class="ti ti-package"></i>Товары</div>
    <div class="nav-item" onclick="nav('users',this)"><i class="ti ti-users"></i>Клиенты</div>
    <div class="nav-item" onclick="nav('reviews',this)"><i class="ti ti-star"></i>Отзывы</div>
    <div class="nav-item" onclick="nav('analytics',this)"><i class="ti ti-chart-bar"></i>Аналитика</div>
  </nav>
  <div style="padding:14px 16px;border-top:1px solid var(--border)">
    <div style="font-size:12px;color:var(--text2)"><?= e(auth()['email']) ?></div>
    <div style="font-size:11px;color:#555;margin-top:2px">Администратор</div>
    <a href="index.php" style="display:block;margin-top:8px;font-size:11px;color:var(--gold)">← К старой админке</a>
  </div>
</aside>

<div class="main">
<div class="topbar">
  <h1 id="page-title">Дашборд</h1>
  <div style="display:flex;gap:8px">
    <a href="<?= SITE_URL ?>/" target="_blank" class="btn btn-sm"><i class="ti ti-external-link" style="font-size:13px"></i>На сайт</a>
    <a href="<?= SITE_URL ?>/?page=logout" class="btn btn-sm btn-gold"><i class="ti ti-logout" style="font-size:13px"></i>Выйти</a>
  </div>
</div>

<div class="content">
<div id="page-dashboard" class="page active">
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-val"><?= $realStats['orders'] ?></div>
      <div class="stat-lbl">Заказов</div>
    </div>
    <div class="stat-card">
      <div class="stat-val gold"><?= $fmtRevenue ?> ₽</div>
      <div class="stat-lbl">Выручка</div>
    </div>
    <div class="stat-card">
      <div class="stat-val"><?= $realStats['products'] ?></div>
      <div class="stat-lbl">Товаров</div>
    </div>
    <div class="stat-card">
      <div class="stat-val"><?= $realStats['users'] ?></div>
      <div class="stat-lbl">Клиентов</div>
    </div>
  </div>

  <div class="two-col">
    <div class="panel">
      <div class="panel-head"><h2>Выручка по месяцам</h2></div>
      <div class="panel-body"><div style="position:relative;height:200px"><canvas id="revenueChart"></canvas></div></div>
    </div>
    <div class="panel">
      <div class="panel-head"><h2>Статусы заказов</h2></div>
      <div class="panel-body"><div style="position:relative;height:200px"><canvas id="statusChart"></canvas></div></div>
    </div>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Последние заказы</h2><button class="btn btn-sm" onclick="nav('orders',document.querySelectorAll('.nav-item')[1])">Все заказы →</button></div>
    <table>
      <thead><tr><th>ID</th><th>Покупатель</th><th>Сумма</th><th>Статус</th><th>Дата</th><th></th></tr></thead>
      <tbody id="dash-orders"></tbody>
    </table>
  </div>

  <div class="panel">
    <div class="panel-head"><h2>Товары с низким остатком</h2></div>
    <table>
      <thead><tr><th>Товар</th><th>Категория</th><th>Цена</th><th>Остаток</th><th>Заполненность</th></tr></thead>
      <tbody id="low-stock"></tbody>
    </table>
  </div>
</div>

<div id="page-orders" class="page">
  <div id="orders-list">
    <div class="search-bar">
      <i class="ti ti-search" style="color:var(--text2);font-size:16px"></i>
      <input type="text" placeholder="Поиск по имени, email, ID..." id="order-search" oninput="filterOrders()">
      <select id="order-status-filter" onchange="filterOrders()">
        <option value="">Все статусы</option>
        <option value="new">Новые</option>
        <option value="paid">Оплаченные</option>
        <option value="shipped">Отправленные</option>
        <option value="delivered">Доставленные</option>
        <option value="canceled">Отменённые</option>
      </select>
    </div>
    <table>
      <thead><tr><th>ID</th><th>Покупатель</th><th>Сумма</th><th>Статус</th><th>Дата</th><th></th></tr></thead>
      <tbody id="orders-tbody"></tbody>
    </table>
  </div>
  <div id="order-detail" style="display:none"></div>
</div>

<div id="page-products" class="page">
  <div class="search-bar">
    <i class="ti ti-search" style="color:var(--text2);font-size:16px"></i>
    <input type="text" placeholder="Поиск по названию..." id="prod-search" oninput="filterProducts()">
    <select id="prod-cat" onchange="filterProducts()">
      <option value="">Все категории</option>
      <option value="Машинки для стрижки">Машинки</option>
      <option value="Триммеры">Триммеры</option>
      <option value="Бритвы">Бритвы</option>
      <option value="Ножницы">Ножницы</option>
      <option value="Средства для укладки">Укладка</option>
      <option value="Уход за бородой">Уход за бородой</option>
      <option value="Аксессуары">Аксессуары</option>
    </select>
  </div>
  <div class="products-grid" id="prod-grid"></div>
</div>

<div id="page-users" class="page">
  <div class="search-bar">
    <i class="ti ti-search" style="color:var(--text2);font-size:16px"></i>
    <input type="text" placeholder="Поиск по имени или email..." id="user-search" oninput="filterUsers()">
    <select id="user-role" onchange="filterUsers()">
      <option value="">Все роли</option>
      <option value="admin">Администраторы</option>
      <option value="user">Клиенты</option>
    </select>
  </div>
  <table>
    <thead><tr><th>Клиент</th><th>Email</th><th>Роль</th><th>Телефон</th><th>Заказов</th><th>Регистрация</th></tr></thead>
    <tbody id="users-tbody"></tbody>
  </table>
</div>

<div id="page-reviews" class="page">
  <div class="filter-row">
    <button class="filter-btn active" onclick="filterReviews('',this)">Все</button>
    <button class="filter-btn" onclick="filterReviews('pending',this)">На модерации</button>
    <button class="filter-btn" onclick="filterReviews('approved',this)">Одобренные</button>
    <button class="filter-btn" onclick="filterReviews('rejected',this)">Отклонённые</button>
  </div>
  <div class="panel">
    <table>
      <thead><tr><th>Товар</th><th>Клиент</th><th>Оценка</th><th>Отзыв</th><th>Статус</th><th>Действия</th></tr></thead>
      <tbody id="reviews-tbody"></tbody>
    </table>
  </div>
</div>

<div id="page-analytics" class="page">
  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-val gold"><?= $realStats['orders'] > 0 ? number_format($realStats['revenue'] / $realStats['orders'], 0, ',', ' ') : 0 ?> ₽</div>
      <div class="stat-lbl">Средний чек</div>
    </div>
    <div class="stat-card">
      <div class="stat-val">—</div>
      <div class="stat-lbl">Конверсия</div>
    </div>
    <div class="stat-card">
      <div class="stat-val"><?php
        $avgRating = db()->query("SELECT COALESCE(AVG(rating),0) FROM reviews WHERE status='approved'")->fetchColumn();
        echo number_format((float)$avgRating, 1, '.', '');
      ?></div>
      <div class="stat-lbl">Ср. рейтинг</div>
    </div>
    <div class="stat-card">
      <div class="stat-val">—</div>
      <div class="stat-lbl">Повторных покупок</div>
    </div>
  </div>
  <div class="two-col">
    <div class="panel">
      <div class="panel-head"><h2>Топ товаров по просмотрам</h2></div>
      <div class="panel-body"><div style="position:relative;height:220px"><canvas id="topProdChart"></canvas></div></div>
    </div>
    <div class="panel">
      <div class="panel-head"><h2>Товары по категориям</h2></div>
      <div class="panel-body"><div style="position:relative;height:220px"><canvas id="catChart"></canvas></div></div>
    </div>
  </div>
  <div class="panel">
    <div class="panel-head"><h2>Заказы по дням</h2></div>
    <div class="panel-body"><div style="position:relative;height:180px"><canvas id="ordersChart"></canvas></div></div>
  </div>
</div>

</div>
</div>
</div>

<div class="toast" id="toast"></div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.js"></script>
<script>
// ── Data from PHP ──
const orders = <?= json_encode($jsonOrders, JSON_UNESCAPED_UNICODE) ?>;
const products = <?= json_encode($jsonProducts, JSON_UNESCAPED_UNICODE) ?>;
const users = <?= json_encode($jsonUsers, JSON_UNESCAPED_UNICODE) ?>;
const reviewsData = <?= json_encode($jsonReviews, JSON_UNESCAPED_UNICODE) ?>;

// ── Helpers ──
function fmt(n){return n.toLocaleString('ru-RU')+' ₽'}
const statusMap={new:'Новый',paid:'Оплачен',shipped:'Отправлен',delivered:'Доставлен',canceled:'Отменён'};
function statusBadge(s){return `<span class="badge badge-${s}">${statusMap[s]||s}</span>`}
function stars(r){return '★'.repeat(r)+'☆'.repeat(5-r)}
function initials(n){return n.split(' ').map(w=>w[0]).join('').slice(0,2).toUpperCase()}

// ── Navigation ──
let currentPage='dashboard';
function nav(page,el){
  document.querySelectorAll('.nav-item').forEach(i=>i.classList.remove('active'));
  el.classList.add('active');
  document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
  document.getElementById('page-'+page).classList.add('active');
  const titles={dashboard:'Дашборд',orders:'Заказы',products:'Товары',users:'Клиенты',reviews:'Отзывы',analytics:'Аналитика'};
  document.getElementById('page-title').textContent=titles[page];
  currentPage=page;
  if(page==='orders'){renderOrders(orders);document.getElementById('orders-list').style.display='block';document.getElementById('order-detail').style.display='none';}
  if(page==='products')renderProducts(products);
  if(page==='users')renderUsers(users);
  if(page==='reviews')renderReviews(reviewsData,'');
  if(page==='analytics')initAnalytics();
}

// ── Dashboard ──
function renderDashOrders(){
  const tb=document.getElementById('dash-orders');
  tb.innerHTML=orders.slice(0,5).map(o=>`
    <tr>
      <td style="color:var(--text2)">#${o.id}</td>
      <td><div class="td-name">${o.name}</div><div class="td-muted">${o.email}</div></td>
      <td style="color:var(--gold);font-weight:500">${fmt(o.total)}</td>
      <td>${statusBadge(o.status)}</td>
      <td>${o.date.split('-').reverse().join('.')}</td>
      <td><button class="btn btn-sm" onclick="openOrder(${o.id})">Открыть</button></td>
    </tr>`).join('');
}
function renderLowStock(){
  const tb=document.getElementById('low-stock');
  const low=products.filter(p=>p.stock<=15).sort((a,b)=>a.stock-b.stock).slice(0,5);
  tb.innerHTML=low.map(p=>`
    <tr>
      <td class="td-name">${p.name}</td>
      <td><span class="cat-tag">${p.cat}</span></td>
      <td style="color:var(--gold)">${fmt(p.price)}</td>
      <td style="color:${p.stock<=6?'var(--red)':p.stock<=10?'var(--gold)':'var(--text2)'}"><strong>${p.stock}</strong> шт.</td>
      <td style="width:120px"><div class="progress-bar"><div class="progress-fill" style="width:${Math.min(100,p.stock*4)}%;background:${p.stock<=6?'var(--red)':p.stock<=10?'var(--gold)':'var(--green)'}"></div></div></td>
    </tr>`).join('');
}

// ── Orders ──
function renderOrders(list){
  const tb=document.getElementById('orders-tbody');
  tb.innerHTML=list.map(o=>`
    <tr>
      <td style="color:var(--text2);font-size:12px">#${o.id}</td>
      <td><div class="td-name">${o.name}</div><div class="td-muted">${o.email}</div></td>
      <td style="color:var(--gold);font-weight:500">${fmt(o.total)}</td>
      <td>${statusBadge(o.status)}</td>
      <td style="color:var(--text2)">${o.date.split('-').reverse().join('.')}</td>
      <td><button class="btn btn-sm btn-gold" onclick="openOrder(${o.id})">Открыть</button></td>
    </tr>`).join('');
}
function filterOrders(){
  const q=document.getElementById('order-search').value.toLowerCase();
  const s=document.getElementById('order-status-filter').value;
  renderOrders(orders.filter(o=>(o.name+o.email+''+o.id).toLowerCase().includes(q)&&(!s||o.status===s)));
}
function openOrder(id){
  const o=orders.find(x=>x.id===id);
  document.getElementById('orders-list').style.display='none';
  const d=document.getElementById('order-detail');
  d.style.display='block';
  d.innerHTML=`
    <div class="back-link" onclick="document.getElementById('orders-list').style.display='block';document.getElementById('order-detail').style.display='none'">
      <i class="ti ti-arrow-left"></i> Все заказы
    </div>
    <div style="font-size:20px;font-weight:500;color:#fff;margin-bottom:18px">Заказ #${o.id}</div>
    <div class="detail-grid">
      <div>
        <div class="panel">
          <div class="panel-head"><h2>Состав заказа</h2></div>
          <table>
            <thead><tr><th>Товар</th><th>Цена</th><th>Кол-во</th><th>Сумма</th></tr></thead>
            <tbody>${o.items.map(i=>`<tr><td class="td-name">${i.name}</td><td>${fmt(i.price/i.qty)}</td><td>${i.qty}</td><td style="color:var(--gold)">${fmt(i.price)}</td></tr>`).join('')}</tbody>
          </table>
          <div style="text-align:right;padding:14px 18px;font-size:18px;font-weight:500;color:var(--gold)">Итого: ${fmt(o.total)}</div>
        </div>
      </div>
      <div>
        <div class="panel" style="margin-bottom:14px">
          <div class="panel-head"><h2>Клиент</h2></div>
          <div class="panel-body">
            <div class="detail-row"><span class="detail-lbl">Имя</span><span class="detail-val">${o.name}</span></div>
            <div class="detail-row"><span class="detail-lbl">Email</span><span class="detail-val" style="font-size:12px">${o.email}</span></div>
            <div class="detail-row"><span class="detail-lbl">Телефон</span><span class="detail-val">${o.phone}</span></div>
            <div class="detail-row"><span class="detail-lbl">Адрес</span><span class="detail-val" style="font-size:12px;text-align:right;max-width:160px">${o.address||'—'}</span></div>
          </div>
        </div>
        <div class="panel">
          <div class="panel-head"><h2>Статус заказа</h2></div>
          <div class="panel-body">
            <div style="margin-bottom:10px">${statusBadge(o.status)}</div>
            <select class="status-select" id="status-sel-${o.id}">
              ${['new','paid','shipped','delivered','canceled'].map(s=>`<option value="${s}"${o.status===s?' selected':''}>${statusMap[s]}</option>`).join('')}
            </select>
            <a href="orders.php?id=${o.id}" class="btn btn-gold" style="width:100%;margin-top:10px;justify-content:center">Открыть в админке</a>
          </div>
        </div>
      </div>
    </div>`;
}

// ── Products ──
function renderProducts(list){
  const g=document.getElementById('prod-grid');
  g.innerHTML=list.map(p=>`
    <div class="product-card">
      <div style="font-size:13px;font-weight:500;color:#fff;margin-bottom:4px">${p.name}</div>
      <span class="cat-tag">${p.cat}</span>
      <div class="product-price">${fmt(p.price)}${p.discount?` <small style="font-size:12px;color:var(--green)">-${p.discount}%</small>`:''}</div>
      <div class="product-stock">Остаток: <strong style="color:${p.stock<=6?'var(--red)':p.stock<=15?'var(--gold)':'#fff'}">${p.stock} шт.</strong></div>
      <div style="font-size:11px;color:var(--text2);margin-top:2px">Просмотров: ${p.views}</div>
      <div class="product-actions">
        <a href="products.php?action=edit&id=${p.id}" class="btn btn-sm btn-gold"><i class="ti ti-edit" style="font-size:12px"></i>Изменить</a>
      </div>
    </div>`).join('');
}
function filterProducts(){
  const q=document.getElementById('prod-search').value.toLowerCase();
  const c=document.getElementById('prod-cat').value;
  renderProducts(products.filter(p=>p.name.toLowerCase().includes(q)&&(!c||p.cat===c)));
}

// ── Users ──
function renderUsers(list){
  const tb=document.getElementById('users-tbody');
  tb.innerHTML=list.map(u=>`
    <tr>
      <td>
        <div class="user-row">
          <div class="user-avatar">${initials(u.name)}</div>
          <span class="td-name">${u.name}</span>
        </div>
      </td>
      <td style="color:var(--text2);font-size:12px">${u.email}</td>
      <td><span class="badge ${u.role==='admin'?'badge-paid':'badge-shipped'}">${u.role==='admin'?'Админ':'Клиент'}</span></td>
      <td style="color:var(--text2)">${u.phone||'—'}</td>
      <td style="text-align:center;color:${u.orders>4?'var(--gold)':'var(--text)'}">${u.orders}</td>
      <td style="color:var(--text2);font-size:12px">${u.date.split('-').reverse().join('.')}</td>
    </tr>`).join('');
}
function filterUsers(){
  const q=document.getElementById('user-search').value.toLowerCase();
  const r=document.getElementById('user-role').value;
  renderUsers(users.filter(u=>(u.name+u.email).toLowerCase().includes(q)&&(!r||u.role===r)));
}

// ── Reviews ──
let reviewFilter='';
function renderReviews(list,status){
  const filtered=status?list.filter(r=>r.status===status):list;
  const tb=document.getElementById('reviews-tbody');
  tb.innerHTML=filtered.map(r=>`
    <tr>
      <td class="td-name">${r.product}</td>
      <td style="color:var(--text2)">${r.user}</td>
      <td><div class="review-stars">${stars(r.rating)}</div></td>
      <td><div class="review-text">"${r.text}"</div></td>
      <td><span class="badge badge-${r.status}">${r.status==='pending'?'Ожидает':r.status==='approved'?'Одобрен':'Отклонён'}</span></td>
      <td>
        ${r.status!=='approved'?`<a href="reviews.php" class="btn btn-sm" style="color:var(--green);border-color:var(--green)">✓ Одобрить</a> `:''}
        ${r.status!=='rejected'?`<a href="reviews.php" class="btn btn-sm" style="color:var(--red);border-color:var(--red)">✗ Отклонить</a>`:''}
      </td>
    </tr>`).join('');
}
function filterReviews(status,btn){
  reviewFilter=status;
  document.querySelectorAll('.filter-btn').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  renderReviews(reviewsData,status);
}

// ── Analytics ──
let chartsInited=false;
function initAnalytics(){
  if(chartsInited)return;chartsInited=true;

  const topProd=products.slice().sort((a,b)=>b.views-a.views).slice(0,5);
  new Chart(document.getElementById('topProdChart'),{
    type:'bar',
    data:{labels:topProd.map(p=>p.name.split(' ').slice(0,2).join(' ')),datasets:[{label:'Просмотры',data:topProd.map(p=>p.views),backgroundColor:'#c9a84c'}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{ticks:{color:'#888',font:{size:10}}},y:{ticks:{color:'#888'}}}}
  });

  const catCounts={};
  products.forEach(p=>{catCounts[p.cat]=(catCounts[p.cat]||0)+1});
  const catLabels=Object.keys(catCounts);
  const catData=Object.values(catCounts);
  const colors=['#c9a84c','#5dcaa5','#85b7eb','#f09595','#e8c96e','#afa9ec','#888780'];
  new Chart(document.getElementById('catChart'),{
    type:'doughnut',
    data:{labels:catLabels,datasets:[{data:catData,backgroundColor:colors.slice(0,catLabels.length)}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'right',labels:{color:'#888',font:{size:11}}}}}
  });

  new Chart(document.getElementById('ordersChart'),{
    type:'line',
    data:{labels:['1 мая','5 мая','10 мая','15 мая','20 мая','25 мая','28 мая'],datasets:[{label:'Заказы',data:[3,7,5,12,8,15,9],borderColor:'#c9a84c',backgroundColor:'rgba(201,168,76,.1)',fill:true,tension:.4}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{ticks:{color:'#888',font:{size:11}}},y:{ticks:{color:'#888'}}}}
  });
}

function showToast(msg){
  const t=document.getElementById('toast');
  t.textContent=msg;t.classList.add('show');
  setTimeout(()=>t.classList.remove('show'),2200);
}

// ── Init ──
const statusCounts={new:0,paid:0,shipped:0,delivered:0,canceled:0};
orders.forEach(o=>{if(statusCounts[o.status]!==undefined)statusCounts[o.status]++});

(function init(){
  renderDashOrders();
  renderLowStock();

  const months=['Янв','Фев','Мар','Апр','Май','Июн'];
  const revData=[];
  for(let i=5;i>=0;i--){const d=new Date();d.setMonth(d.getMonth()-i);revData.push(Math.round(<?= $realStats['revenue'] ?>/6*(0.7+Math.random()*0.6)));}

  new Chart(document.getElementById('revenueChart'),{
    type:'bar',
    data:{labels:months,datasets:[{label:'Выручка',data:revData,backgroundColor:'#c9a84c'}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{x:{ticks:{color:'#888'}},y:{ticks:{color:'#888',callback:v=>v/1000+'к'}}}}
  });
  new Chart(document.getElementById('statusChart'),{
    type:'doughnut',
    data:{labels:['Новых','Оплачено','Отправлено','Доставлено','Отменено'],datasets:[{data:[statusCounts.new,statusCounts.paid,statusCounts.shipped,statusCounts.delivered,statusCounts.canceled],backgroundColor:['#85b7eb','#5dcaa5','#c9a84c','#2a9d5c','#cc3333']}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'right',labels:{color:'#888',font:{size:11}}}}}
  });
})();
</script>
</body>
</html>
