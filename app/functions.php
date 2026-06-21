<?php
/**
 * app/functions.php — All helper functions
 */

declare(strict_types=1);

// ── Output sanitization ────────────────────────────────────
function e(mixed $v): string
{
    return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// ── CSRF ───────────────────────────────────────────────────
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $token = $_POST['csrf_token'] ?? '';
    if (!hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('CSRF token mismatch');
    }
}

// ── Auth helpers ───────────────────────────────────────────
function auth(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_admin(): bool
{
    return (auth()['role'] ?? '') === 'admin';
}

function require_auth(): void
{
    if (!auth()) {
        header('Location: ' . SITE_URL . '/index.php?page=login&redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
}

function require_admin(): void
{
    if (!auth()) {
        header('Location: ' . SITE_URL . '/index.php?page=login&redirect=' . urlencode($_SERVER['REQUEST_URI']));
        exit;
    }
    if (!is_admin()) {
        http_response_code(403);
        die('Доступ запрещён');
    }
}

// ── Slug generator ─────────────────────────────────────────
function make_slug(string $str): string
{
    $str = mb_strtolower($str, 'UTF-8');
    $tr  = [
        'а'=>'a','б'=>'b','в'=>'v','г'=>'g','д'=>'d','е'=>'e','ё'=>'yo','ж'=>'zh',
        'з'=>'z','и'=>'i','й'=>'j','к'=>'k','л'=>'l','м'=>'m','н'=>'n','о'=>'o',
        'п'=>'p','р'=>'r','с'=>'s','т'=>'t','у'=>'u','ф'=>'f','х'=>'h','ц'=>'ts',
        'ч'=>'ch','ш'=>'sh','щ'=>'sch','ъ'=>'','ы'=>'y','ь'=>'','э'=>'e','ю'=>'yu','я'=>'ya',
    ];
    $str = strtr($str, $tr);
    $str = preg_replace('/[^a-z0-9]+/', '-', $str);
    return trim($str, '-');
}

// ── Price helpers ──────────────────────────────────────────
function final_price(float $price, int $discount): float
{
    if ($discount <= 0) return $price;
    return round($price * (1 - $discount / 100), 2);
}

function fmt_price(float $price): string
{
    return number_format($price, 2, ',', ' ') . ' Br';
}

// ── Flash messages ─────────────────────────────────────────
function flash(string $type, string $msg): void
{
    $_SESSION['flash'][] = ['type' => $type, 'msg' => $msg];
}

function get_flash(): array
{
    $msgs = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $msgs;
}

// ── Pagination ─────────────────────────────────────────────
function paginate(int $total, int $page, int $per): array
{
    $per   = max(1, $per); // prevent division by zero
    $pages = max(1, (int)ceil($total / $per));
    $page  = max(1, min($page, $pages));
    return [
        'total'  => $total,
        'pages'  => $pages,
        'page'   => $page,
        'offset' => ($page - 1) * $per,
        'per'    => $per,
    ];
}

// ── Product queries ────────────────────────────────────────
function get_products(array $opts = []): array
{
    $where  = ['p.is_active = 1'];
    $params = [];

    if (!empty($opts['category_id'])) {
        $where[]  = 'p.category_id = :cat';
        $params[':cat'] = $opts['category_id'];
    }
    if (!empty($opts['search'])) {
        $where[]   = '(p.name LIKE :s OR p.description LIKE :s2)';
        $params[':s']  = '%' . $opts['search'] . '%';
        $params[':s2'] = '%' . $opts['search'] . '%';
    }
    if (isset($opts['price_min']) && $opts['price_min'] !== '') {
        $where[]  = 'p.price >= :pmin';
        $params[':pmin'] = $opts['price_min'];
    }
    if (isset($opts['price_max']) && $opts['price_max'] !== '') {
        $where[]  = 'p.price <= :pmax';
        $params[':pmax'] = $opts['price_max'];
    }
    if (!empty($opts['in_stock'])) {
        $where[] = 'p.stock > 0';
    }
    if (!empty($opts['tag_id'])) {
        $where[]  = 'EXISTS (SELECT 1 FROM product_tags pt WHERE pt.product_id=p.id AND pt.tag_id=:tag)';
        $params[':tag'] = $opts['tag_id'];
    }
    if (!empty($opts['discount_only'])) {
        $where[] = 'p.discount > 0';
    }

    $order = match ($opts['sort'] ?? '') {
        'price_asc'  => 'p.price ASC',
        'price_desc' => 'p.price DESC',
        'popular'    => 'p.views DESC',
        default      => 'p.created_at DESC',
    };

    $sql = "SELECT p.*, c.name AS cat_name,
                   (SELECT filename FROM product_images WHERE product_id=p.id ORDER BY sort_order LIMIT 1) AS main_image,
                   ROUND(p.price*(1-p.discount/100),2) AS final_price
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY $order";

    // Count total
    $sqlCount = "SELECT COUNT(*) FROM products p WHERE " . implode(' AND ', $where);
    $stCount  = db()->prepare($sqlCount);
    $stCount->execute($params);
    $total = (int)$stCount->fetchColumn();

    // Pagination
    $per  = (int)($opts['per'] ?? PER_PAGE_DEF);
    $page = (int)($opts['page'] ?? 1);
    $pag  = paginate($total, $page, $per);

    $sql .= " LIMIT {$pag['per']} OFFSET {$pag['offset']}";
    $st   = db()->prepare($sql);
    $st->execute($params);

    return ['rows' => $st->fetchAll(), 'pag' => $pag];
}

function get_product_by_slug(string $slug): ?array
{
    $st = db()->prepare(
        "SELECT p.*, c.name AS cat_name, c.slug AS cat_slug,
                ROUND(p.price*(1-p.discount/100),2) AS final_price
         FROM products p
         LEFT JOIN categories c ON c.id=p.category_id
         WHERE p.slug=:slug AND p.is_active=1 LIMIT 1"
    );
    $st->execute([':slug' => $slug]);
    return $st->fetch() ?: null;
}

function get_product_images(int $id): array
{
    $st = db()->prepare("SELECT * FROM product_images WHERE product_id=:id ORDER BY sort_order");
    $st->execute([':id' => $id]);
    return $st->fetchAll();
}

function get_product_tags(int $id): array
{
    $st = db()->prepare(
        "SELECT t.* FROM tags t
         JOIN product_tags pt ON pt.tag_id=t.id
         WHERE pt.product_id=:id"
    );
    $st->execute([':id' => $id]);
    return $st->fetchAll();
}

function get_related_products(int $productId, array $tagIds, int $limit = 4): array
{
    if (empty($tagIds)) return [];
    $in  = implode(',', array_fill(0, count($tagIds), '?'));
    $sql = "SELECT DISTINCT p.*, ROUND(p.price*(1-p.discount/100),2) AS final_price,
                   (SELECT filename FROM product_images WHERE product_id=p.id ORDER BY sort_order LIMIT 1) AS main_image
            FROM products p
            JOIN product_tags pt ON pt.product_id=p.id
            WHERE pt.tag_id IN ($in) AND p.id != ? AND p.is_active=1
            ORDER BY p.views DESC LIMIT $limit";
    $params = array_merge($tagIds, [$productId]);
    $st = db()->prepare($sql);
    $st->execute($params);
    return $st->fetchAll();
}

// ── Categories & Tags ──────────────────────────────────────
function get_categories(): array
{
    return db()->query("SELECT * FROM categories ORDER BY sort_order")->fetchAll();
}

function get_tags(): array
{
    return db()->query("SELECT * FROM tags ORDER BY name")->fetchAll();
}

// ── Cart ───────────────────────────────────────────────────
function get_cart_id(): int
{
    $user = auth();
    if ($user) {
        $st = db()->prepare("SELECT id FROM carts WHERE user_id=:uid");
        $st->execute([':uid' => $user['id']]);
        $cart = $st->fetch();
        if (!$cart) {
            db()->prepare("INSERT INTO carts (user_id) VALUES (:uid)")->execute([':uid' => $user['id']]);
            return (int)db()->lastInsertId();
        }
        return (int)$cart['id'];
    }
    // guest
    $sessId = session_id();
    $st = db()->prepare("SELECT id FROM carts WHERE session_id=:sid AND user_id IS NULL");
    $st->execute([':sid' => $sessId]);
    $cart = $st->fetch();
    if (!$cart) {
        db()->prepare("INSERT INTO carts (session_id) VALUES (:sid)")->execute([':sid' => $sessId]);
        return (int)db()->lastInsertId();
    }
    return (int)$cart['id'];
}

function get_cart_items(int $cartId): array
{
    $st = db()->prepare(
        "SELECT ci.*, p.name, p.price, p.discount, p.stock, p.slug,
                ROUND(p.price*(1-p.discount/100),2) AS final_price,
                (SELECT filename FROM product_images WHERE product_id=p.id ORDER BY sort_order LIMIT 1) AS main_image
         FROM cart_items ci
         JOIN products p ON p.id=ci.product_id
         WHERE ci.cart_id=:cid AND p.is_active=1"
    );
    $st->execute([':cid' => $cartId]);
    return $st->fetchAll();
}

function cart_count(): int
{
    try {
        $cid = get_cart_id();
        $st  = db()->prepare("SELECT COALESCE(SUM(qty),0) FROM cart_items WHERE cart_id=:cid");
        $st->execute([':cid' => $cid]);
        return (int)$st->fetchColumn();
    } catch (\Throwable $e) {
        return 0;
    }
}

function cart_add(int $productId, int $qty = 1): void
{
    $cid = get_cart_id();
    // Check stock
    $st = db()->prepare("SELECT stock FROM products WHERE id=:id AND is_active=1");
    $st->execute([':id' => $productId]);
    $prod = $st->fetch();
    if (!$prod || $prod['stock'] < 1) { flash('error', 'Товар недоступен или нет в наличии'); return; }

    $st = db()->prepare("SELECT id, qty FROM cart_items WHERE cart_id=:c AND product_id=:p");
    $st->execute([':c' => $cid, ':p' => $productId]);
    $item = $st->fetch();
    if ($item) {
        $newQty = min($item['qty'] + $qty, $prod['stock']);
        db()->prepare("UPDATE cart_items SET qty=:q WHERE id=:id")->execute([':q'=>$newQty,':id'=>$item['id']]);
    } else {
        db()->prepare("INSERT INTO cart_items (cart_id,product_id,qty) VALUES (:c,:p,:q)")
             ->execute([':c'=>$cid,':p'=>$productId,':q'=>min($qty,$prod['stock'])]);
    }
    flash('success', 'Товар добавлен в корзину');
}

function cart_update(int $itemId, int $qty): void
{
    $cid = get_cart_id();
    if ($qty <= 0) {
        db()->prepare("DELETE FROM cart_items WHERE id=:id AND cart_id=:c")->execute([':id'=>$itemId,':c'=>$cid]);
        return;
    }
    // cap to stock
    $st = db()->prepare("SELECT p.stock FROM cart_items ci JOIN products p ON p.id=ci.product_id WHERE ci.id=:id");
    $st->execute([':id'=>$itemId]);
    $row = $st->fetch();
    $qty = $row ? min($qty, $row['stock']) : $qty;
    db()->prepare("UPDATE cart_items SET qty=:q WHERE id=:id AND cart_id=:c")->execute([':q'=>$qty,':id'=>$itemId,':c'=>$cid]);
}

function cart_remove(int $itemId): void
{
    $cid = get_cart_id();
    db()->prepare("DELETE FROM cart_items WHERE id=:id AND cart_id=:c")->execute([':id'=>$itemId,':c'=>$cid]);
}

function cart_merge_session(int $userId): void
{
    // After login, merge guest cart into user cart
    $sessId = session_id();
    $st = db()->prepare("SELECT id FROM carts WHERE session_id=:sid AND user_id IS NULL");
    $st->execute([':sid' => $sessId]);
    $guestCart = $st->fetch();
    if (!$guestCart) return;

    $stUser = db()->prepare("SELECT id FROM carts WHERE user_id=:uid");
    $stUser->execute([':uid'=>$userId]);
    $userCart = $stUser->fetch();

    if ($userCart) {
        // Move items
        $items = db()->prepare("SELECT * FROM cart_items WHERE cart_id=:cid");
        $items->execute([':cid' => $guestCart['id']]);
        foreach ($items->fetchAll() as $item) {
            $ex = db()->prepare("SELECT id,qty FROM cart_items WHERE cart_id=:c AND product_id=:p");
            $ex->execute([':c'=>$userCart['id'],':p'=>$item['product_id']]);
            $exist = $ex->fetch();
            if ($exist) {
                db()->prepare("UPDATE cart_items SET qty=qty+:q WHERE id=:id")->execute([':q'=>$item['qty'],':id'=>$exist['id']]);
            } else {
                db()->prepare("INSERT INTO cart_items (cart_id,product_id,qty) VALUES (:c,:p,:q)")
                     ->execute([':c'=>$userCart['id'],':p'=>$item['product_id'],':q'=>$item['qty']]);
            }
        }
        db()->prepare("DELETE FROM carts WHERE id=:id")->execute([':id'=>$guestCart['id']]);
    } else {
        db()->prepare("UPDATE carts SET user_id=:uid, session_id=NULL WHERE id=:id")
             ->execute([':uid'=>$userId,':id'=>$guestCart['id']]);
    }
}

// ── Orders ─────────────────────────────────────────────────
function place_order(array $data): int|false
{
    $cid   = get_cart_id();
    $items = get_cart_items($cid);
    if (empty($items)) return false;

    $pdo = db();
    try {
        $pdo->beginTransaction();

        // Verify stock & calc total
        $total = 0;
        foreach ($items as $item) {
            $st = $pdo->prepare("SELECT stock FROM products WHERE id=:id FOR UPDATE");
            $st->execute([':id'=>$item['product_id']]);
            $prod = $st->fetch();
            if (!$prod || $prod['stock'] < $item['qty']) {
                throw new \RuntimeException('Товар "' . $item['name'] . '" больше не доступен в нужном количестве');
            }
            $total += $item['final_price'] * $item['qty'];
        }

        // Insert order
        $st = $pdo->prepare(
            "INSERT INTO orders (user_id,name,email,phone,address,comment,total)
             VALUES (:uid,:name,:email,:phone,:address,:comment,:total)"
        );
        $st->execute([
            ':uid'     => auth() ? auth()['id'] : null,
            ':name'    => $data['name'],
            ':email'   => $data['email'],
            ':phone'   => $data['phone'],
            ':address' => $data['address'],
            ':comment' => $data['comment'] ?? null,
            ':total'   => $total,
        ]);
        $orderId = (int)$pdo->lastInsertId();

        // Insert items & deduct stock
        foreach ($items as $item) {
            $pdo->prepare(
                "INSERT INTO order_items (order_id,product_id,name,price,qty)
                 VALUES (:oid,:pid,:name,:price,:qty)"
            )->execute([':oid'=>$orderId,':pid'=>$item['product_id'],':name'=>$item['name'],
                        ':price'=>$item['final_price'],':qty'=>$item['qty']]);
            $pdo->prepare("UPDATE products SET stock=stock-:q WHERE id=:id")
                ->execute([':q'=>$item['qty'],':id'=>$item['product_id']]);
        }

        // Clear cart
        $pdo->prepare("DELETE FROM cart_items WHERE cart_id=:cid")->execute([':cid'=>$cid]);
        $pdo->commit();

        // Auto-create CRM lead from order
        try {
            $orderData = array_merge($data, ['id' => $orderId, 'user_id' => auth() ? auth()['id'] : null]);
            $exists = db()->prepare("SELECT id FROM crm_leads WHERE email=:e AND source='order' LIMIT 1");
            $exists->execute([':e' => $data['email']]);
            if (!$exists->fetch()) {
                crm_create_lead_from_order($orderData);
            }
        } catch (\Throwable) {}

        return $orderId;
    } catch (\Throwable $e) {
        $pdo->rollBack();
        flash('error', 'Ошибка оформления заказа: ' . e($e->getMessage()));
        return false;
    }
}

// ── File upload ────────────────────────────────────────────
function upload_product_image(array $file, int $productId): ?string
{
    $allowed = ['image/jpeg','image/png','image/webp'];
    $maxSize = MAX_FILE_MB * 1024 * 1024;

    if (!in_array($file['type'], $allowed, true))          { flash('error','Только jpg/png/webp'); return null; }
    if ($file['size'] > $maxSize)                          { flash('error','Файл > ' . MAX_FILE_MB . 'MB'); return null; }
    if ($file['error'] !== UPLOAD_ERR_OK)                  { flash('error','Ошибка загрузки файла'); return null; }

    // Verify image
    $info = @getimagesize($file['tmp_name']);
    if (!$info) { flash('error','Файл не является изображением'); return null; }

    $ext  = match($file['type']) { 'image/png'=>'png', 'image/webp'=>'webp', default=>'jpg' };
    $dir  = UPLOAD_DIR . $productId . '/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $name = uniqid('img_', true) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], $dir . $name)) { flash('error','Не удалось сохранить файл'); return null; }
    return $name;
}

// ── Wishlist ───────────────────────────────────────────────
function wishlist_toggle(int $userId, int $productId): bool
{
    $st = db()->prepare("SELECT 1 FROM wishlist WHERE user_id=:u AND product_id=:p");
    $st->execute([':u'=>$userId,':p'=>$productId]);
    if ($st->fetchColumn()) {
        db()->prepare("DELETE FROM wishlist WHERE user_id=:u AND product_id=:p")->execute([':u'=>$userId,':p'=>$productId]);
        return false;
    }
    db()->prepare("INSERT INTO wishlist (user_id,product_id) VALUES (:u,:p)")->execute([':u'=>$userId,':p'=>$productId]);
    return true;
}

function wishlist_ids(int $userId): array
{
    $st = db()->prepare("SELECT product_id FROM wishlist WHERE user_id=:u");
    $st->execute([':u'=>$userId]);
    return array_column($st->fetchAll(), 'product_id');
}

// ── CRM ─────────────────────────────────────────────────────
function crm_create_lead(array $data): int
{
    $st = db()->prepare(
        "INSERT INTO crm_leads (name, email, phone, source, status, notes, user_id, assigned_to)
         VALUES (:name, :email, :phone, :source, :status, :notes, :user_id, :assigned_to)"
    );
    $st->execute([
        ':name'        => $data['name'],
        ':email'       => $data['email'] ?? null,
        ':phone'       => $data['phone'] ?? null,
        ':source'      => $data['source'] ?? 'site',
        ':status'      => $data['status'] ?? 'new',
        ':notes'       => $data['notes'] ?? null,
        ':user_id'     => $data['user_id'] ?? null,
        ':assigned_to' => $data['assigned_to'] ?? null,
    ]);
    return (int)db()->lastInsertId();
}

function crm_create_lead_from_order(array $order): int
{
    return crm_create_lead([
        'name'    => $order['name'],
        'email'   => $order['email'],
        'phone'   => $order['phone'],
        'source'  => 'order',
        'status'  => 'customer',
        'notes'   => 'Клиент оформил заказ #' . $order['id'],
        'user_id' => $order['user_id'] ? (int)$order['user_id'] : null,
    ]);
}

// ── Reviews ────────────────────────────────────────────────
function get_reviews(int $productId): array
{
    $st = db()->prepare(
        "SELECT r.*, u.name AS user_name FROM reviews r
         LEFT JOIN users u ON u.id=r.user_id
         WHERE r.product_id=:pid AND r.status='approved'
         ORDER BY r.created_at DESC"
    );
    $st->execute([':pid'=>$productId]);
    return $st->fetchAll();
}

// ── Misc ───────────────────────────────────────────────────
function product_image_url(int $productId, ?string $filename): string
{
    if (!$filename) return SITE_URL . '/css/no-image.svg';
    return UPLOAD_URL . $productId . '/' . rawurlencode($filename);
}

function redirect(string $url): never
{
    header('Location: ' . $url);
    exit;
}
