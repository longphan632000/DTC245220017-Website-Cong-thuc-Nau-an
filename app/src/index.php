<?php
session_start();
function h($value) { return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8'); }
function text($key) { return trim(is_string($_POST[$key] ?? null) ? $_POST[$key] : ''); }

$host = getenv('DB_HOST') ?: 'mysql';
$db = getenv('DB_NAME') ?: 'recipe_db';
$user = getenv('DB_USER') ?: 'recipe_user';
$password = getenv('DB_PASSWORD') ?: '';
try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    error_log('Kết nối MySQL thất bại: ' . $e->getMessage());
    http_response_code(503);
    exit('Chưa kết nối được cơ sở dữ liệu. Hãy kiểm tra service mysql.');
}

// Token ngăn website khác gửi biểu mẫu thay người đang mở trang demo.
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24));
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($_SESSION['csrf'], text('csrf'))) {
        http_response_code(403);
        exit('Biểu mẫu không hợp lệ. Vui lòng tải lại trang.');
    }
    try {
        if (isset($_POST['save_recipe'])) {
            $title = text('title');
            $ingredients = text('ingredients');
            $instructions = text('instructions');
            $minutes = (int)($_POST['cooking_minutes'] ?? 0);
            $servings = (int)($_POST['servings'] ?? 0);
            if ($title === '' || strlen($title) > 255 || $ingredients === '' || $instructions === '' || $minutes < 1 || $minutes > 1440 || $servings < 1 || $servings > 100) {
                throw new InvalidArgumentException('Điền đủ tên món, nguyên liệu, cách làm, thời gian 1–1440 phút và khẩu phần 1–100 người.');
            }
            $values = [$title, $ingredients, $instructions, (int)$_POST['category_id'], (int)$_POST['user_id'], $minutes, $servings];
            $id = (int)($_POST['id'] ?? 0);
            if ($id > 0) {
                $values[] = $id;
                $stmt = $pdo->prepare('UPDATE recipes SET title=?, ingredients=?, instructions=?, category_id=?, user_id=?, cooking_minutes=?, servings=? WHERE id=?');
            } else {
                $stmt = $pdo->prepare('INSERT INTO recipes (title, ingredients, instructions, category_id, user_id, cooking_minutes, servings) VALUES (?, ?, ?, ?, ?, ?, ?)');
            }
            $stmt->execute($values);
        } elseif (isset($_POST['delete_recipe'])) {
            $stmt = $pdo->prepare('DELETE FROM recipes WHERE id=?');
            $stmt->execute([(int)$_POST['id']]);
        } elseif (isset($_POST['add_category'])) {
            if (text('category_name') === '' || strlen(text('category_name')) > 100 || strlen(text('description')) > 255) {
                throw new InvalidArgumentException('Tên danh mục cần có 1–100 ký tự, mô tả tối đa 255 ký tự.');
            }
            $stmt = $pdo->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
            $stmt->execute([text('category_name'), text('description')]);
        }
        header('Location: /', true, 303);
        exit;
    } catch (InvalidArgumentException $e) {
        $error = $e->getMessage();
        http_response_code(422);
    } catch (PDOException $e) {
        error_log('Thao tác dữ liệu thất bại: ' . $e->getMessage());
        $error = 'Không lưu được dữ liệu. Danh mục có thể đã tồn tại hoặc thông tin được chọn không hợp lệ.';
        http_response_code(422);
    }
}

$users = $pdo->query('SELECT id, username, fullname FROM users ORDER BY id')->fetchAll();
$categories = $pdo->query('SELECT c.*, COUNT(r.id) AS total_recipes FROM categories c LEFT JOIN recipes r ON r.category_id=c.id GROUP BY c.id ORDER BY c.id')->fetchAll();
$q = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
$category = (int)($_GET['category'] ?? 0);
$detail = (int)($_GET['id'] ?? 0);
$sql = 'SELECT r.*, c.name AS category_name, u.fullname FROM recipes r JOIN categories c ON c.id=r.category_id JOIN users u ON u.id=r.user_id WHERE 1=1';
$params = [];
if ($q !== '') { $sql .= ' AND (r.title LIKE ? OR r.ingredients LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($category > 0) { $sql .= ' AND r.category_id=?'; $params[] = $category; }
if ($detail > 0) { $sql .= ' AND r.id=?'; $params[] = $detail; }
$stmt = $pdo->prepare($sql . ' ORDER BY r.created_at DESC, r.id DESC');
$stmt->execute($params);
$recipes = $stmt->fetchAll();
if ($detail > 0 && !$recipes) http_response_code(404);
$edit = null;
if ((int)($_GET['edit'] ?? 0) > 0) {
    $stmt = $pdo->prepare('SELECT * FROM recipes WHERE id=?');
    $stmt->execute([(int)$_GET['edit']]);
    $edit = $stmt->fetch();
}
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Đề 29 — Bếp Nhà · Công thức nấu ăn</title>
<style>
* { box-sizing: border-box; }
body { margin: 0; background: #f6f4ef; color: #24372d; font-family: system-ui, sans-serif; line-height: 1.6; }
a { color: #286347; } a:hover { color: #163b29; }
header { background: #214a36; color: white; padding: 22px max(24px, calc((100% - 1120px)/2)); }
header a { color: white; text-decoration: none; } header h1 { margin: 0; font-size: 28px; } header p { margin: 3px 0 0; color: #d2e3d6; }
main { max-width: 1152px; margin: 24px auto; padding: 0 16px; }
.layout { display: grid; grid-template-columns: minmax(0, 2fr) minmax(260px, 1fr); gap: 24px; }
.card { background: white; border: 1px solid #e0e4da; border-radius: 10px; padding: 22px; margin-bottom: 22px; }
h2, h3 { margin: 0 0 14px; } h2 { font-size: 23px; } h3 { font-size: 18px; }
label { display: block; margin: 0 0 5px; font-weight: 600; font-size: 14px; }
input, select, textarea, button { width: 100%; padding: 10px 12px; border: 1px solid #cbd5c9; border-radius: 6px; font: inherit; margin: 0 0 13px; }
textarea { resize: vertical; } button { background: #286347; color: white; cursor: pointer; border: 0; font-weight: 600; } button:hover { background: #1e4b35; }
.row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; } .row > * { min-width: 0; }
.badge { display: inline-block; background: #e8f0df; color: #35542b; padding: 3px 11px; border-radius: 20px; font-size: 13px; }
.meta, small { color: #637367; font-size: 14px; } .recipe h2 { margin: 10px 0; }
.recipe p { white-space: normal; } .recipe h3 { margin-top: 20px; }
.actions { display: flex; align-items: center; gap: 16px; margin-top: 22px; border-top: 1px solid #e5e8df; padding-top: 12px; }
.actions form { margin: 0; } .actions button { width: auto; margin: 0; background: #fff1ed; color: #983921; font-size: 14px; padding: 5px 12px; }
.notice { background: #fff1ed; color: #8a3321; padding: 12px; border-radius: 6px; }
table { border-collapse: collapse; width: 100%; font-size: 14px; margin-bottom: 20px; } th, td { text-align: left; padding: 9px 0; border-bottom: 1px solid #e5e8df; }
footer { text-align: center; color: #637367; padding: 12px 20px 28px; font-size: 14px; }
@media(max-width: 760px) { .layout, .row { grid-template-columns: 1fr; } header { padding: 20px; } }
</style>
</head>
<body>
<header><h1><a href="/">Bếp Nhà · Công thức nấu ăn</a></h1><p>Đề 29 — Chia sẻ món ngon cho bữa cơm gia đình</p></header>
<main>
<?php if ($error !== ''): ?><p class="notice" role="alert"><?= h($error) ?></p><?php endif; ?>
<section class="card">
<form method="get">
<label for="q">Tìm món ăn hoặc nguyên liệu</label>
<div class="row"><input id="q" name="q" value="<?= h($q) ?>" placeholder="Ví dụ: gà, gừng, đậu phụ…">
<select name="category" aria-label="Lọc theo danh mục"><option value="0">Tất cả danh mục</option><?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= $category == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></div>
<button type="submit">Tìm công thức</button>
</form>
</section>
<div class="layout">
<section>
<h2><?= $detail > 0 ? 'Chi tiết công thức' : 'Khám phá công thức' ?> <span class="badge"><?= count($recipes) ?> món</span></h2>
<?php if (!$recipes): ?><div class="card">Không tìm thấy công thức phù hợp. <a href="/">Xem tất cả món</a></div><?php endif; ?>
<?php foreach ($recipes as $r): ?>
<article class="card recipe">
<a class="badge" href="/?category=<?= $r['category_id'] ?>"><?= h($r['category_name']) ?></a>
<h2><a href="/?id=<?= $r['id'] ?>"><?= h($r['title']) ?></a></h2>
<p class="meta"><?= h($r['fullname']) ?> · <?= (int)$r['cooking_minutes'] ?> phút · <?= (int)$r['servings'] ?> người</p>
<h3>Nguyên liệu</h3><p><?= nl2br(h($r['ingredients'])) ?></p>
<h3>Cách làm</h3><p><?= nl2br(h($r['instructions'])) ?></p>
<div class="actions"><a href="/?edit=<?= $r['id'] ?>#recipe-form">Sửa công thức</a>
<form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="id" value="<?= $r['id'] ?>"><button name="delete_recipe" type="submit">Xóa công thức</button></form></div>
</article>
<?php endforeach; ?>
</section>
<aside>
<section class="card" id="recipe-form">
<h2><?= $edit ? 'Sửa công thức' : 'Chia sẻ công thức' ?></h2>
<form method="post">
<input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="id" value="<?= (int)($edit['id'] ?? 0) ?>">
<label for="title">Tên món ăn</label><input id="title" name="title" maxlength="255" value="<?= h($edit['title'] ?? '') ?>" required>
<label for="category_id">Danh mục</label><select id="category_id" name="category_id" required><?php foreach ($categories as $c): ?><option value="<?= $c['id'] ?>" <?= ($edit['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select>
<label for="user_id">Người chia sẻ</label><select id="user_id" name="user_id"><?php foreach ($users as $u): ?><option value="<?= $u['id'] ?>" <?= ($edit['user_id'] ?? 0) == $u['id'] ? 'selected' : '' ?>><?= h($u['fullname']) ?></option><?php endforeach; ?></select>
<div class="row"><div><label for="cooking_minutes">Thời gian (phút)</label><input type="number" id="cooking_minutes" name="cooking_minutes" min="1" max="1440" value="<?= (int)($edit['cooking_minutes'] ?? 30) ?>" required></div><div><label for="servings">Số người ăn</label><input type="number" id="servings" name="servings" min="1" max="100" value="<?= (int)($edit['servings'] ?? 2) ?>" required></div></div>
<label for="ingredients">Nguyên liệu — mỗi dòng một loại</label><textarea id="ingredients" name="ingredients" rows="4" required><?= h($edit['ingredients'] ?? '') ?></textarea>
<label for="instructions">Cách làm — ghi từng bước</label><textarea id="instructions" name="instructions" rows="5" required><?= h($edit['instructions'] ?? '') ?></textarea>
<button type="submit" name="save_recipe"><?= $edit ? 'Lưu thay đổi' : 'Đăng công thức' ?></button>
<?php if ($edit): ?><a href="/">Hủy chỉnh sửa</a><?php endif; ?>
</form>
</section>
<section class="card">
<h2>Danh mục món ăn</h2><table><thead><tr><th>Danh mục</th><th>Số món</th></tr></thead><tbody><?php foreach ($categories as $c): ?><tr><td><a href="/?category=<?= $c['id'] ?>"><?= h($c['name']) ?></a></td><td><?= $c['total_recipes'] ?></td></tr><?php endforeach; ?></tbody></table>
<form method="post"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><label for="category_name">Tên danh mục mới</label><input id="category_name" name="category_name" maxlength="100" required><label for="description">Mô tả ngắn</label><input id="description" name="description" maxlength="255"><button type="submit" name="add_category">Thêm danh mục</button></form>
</section>
</aside>
</div>
</main>
<footer>Bếp Nhà · Website công thức nấu ăn — Đề 29</footer>
</body>
</html>
