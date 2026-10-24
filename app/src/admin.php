<?php
require __DIR__ . '/common.php';
$error = '';
$action = text('action');
$form = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($action !== 'login' && !$admin) fail(403, 'Bạn cần đăng nhập quản trị để thực hiện thao tác này.');
    csrf_check();
    if ($action === 'login') {
        $stmt = $pdo->prepare("SELECT id, password_hash FROM users WHERE username=? AND role='admin'");
        $stmt->execute([text('username')]);
        $account = $stmt->fetch();
        $loginPassword = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
        if ($account && strlen($loginPassword) <= 72 && password_verify($loginPassword, $account['password_hash'] ?? '')) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = $account['id'];
            $_SESSION['admin_last_seen'] = time();
            $_SESSION['csrf'] = bin2hex(random_bytes(24));
            redirect_to('/admin.php', 'Đăng nhập thành công.');
        }
        $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
        http_response_code(401);
    } elseif ($action === 'logout') {
        unset($_SESSION['admin_id'], $_SESSION['admin_last_seen']);
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
        redirect_to('/admin.php', 'Bạn đã đăng xuất.');
    } else {
        try {
            if ($action === 'save_recipe') {
                $form = ['id' => (int)($_POST['id'] ?? 0), 'title' => text('title'), 'ingredients' => text('ingredients'), 'instructions' => text('instructions'),
                    'category_id' => (int)($_POST['category_id'] ?? 0), 'cooking_minutes' => (int)($_POST['cooking_minutes'] ?? 0), 'servings' => (int)($_POST['servings'] ?? 0)];
                foreach (['title' => 255, 'ingredients' => 10000, 'instructions' => 10000] as $field => $max) {
                    if ($form[$field] === '' || chars($form[$field]) === false || chars($form[$field]) > $max) throw new InvalidArgumentException('Điền đủ tên món, nguyên liệu và cách làm trong giới hạn cho phép.');
                }
                if ($form['cooking_minutes'] < 1 || $form['cooking_minutes'] > 1440 || $form['servings'] < 1 || $form['servings'] > 100) throw new InvalidArgumentException('Thời gian từ 1–1440 phút, khẩu phần từ 1–100 người.');
                $values = [$form['title'], $form['ingredients'], $form['instructions'], $form['category_id'], $admin['id'], $form['cooking_minutes'], $form['servings']];
                if ($form['id'] > 0) {
                    $stmt = $pdo->prepare('SELECT id FROM recipes WHERE id=?');
                    $stmt->execute([$form['id']]);
                    if (!$stmt->fetch()) fail(404, 'Công thức không tồn tại.');
                    $values[] = $form['id'];
                    $stmt = $pdo->prepare('UPDATE recipes SET title=?, ingredients=?, instructions=?, category_id=?, user_id=?, cooking_minutes=?, servings=? WHERE id=?');
                } else $stmt = $pdo->prepare('INSERT INTO recipes (title, ingredients, instructions, category_id, user_id, cooking_minutes, servings) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $stmt->execute($values);
                redirect_to('/admin.php', 'Đã lưu và chia sẻ công thức lên trang người dùng.');
            } elseif ($action === 'delete_recipe') {
                $stmt = $pdo->prepare('DELETE FROM recipes WHERE id=?');
                $stmt->execute([(int)($_POST['id'] ?? 0)]);
                redirect_to('/admin.php', 'Đã xóa công thức và các bình luận của món này.');
            } elseif ($action === 'add_category') {
                $name = text('category_name'); $description = text('description');
                if ($name === '' || chars($name) === false || chars($name) > 100 || chars($description) === false || chars($description) > 255) throw new InvalidArgumentException('Tên danh mục từ 1–100 ký tự, mô tả tối đa 255 ký tự.');
                $stmt = $pdo->prepare('INSERT INTO categories (name, description) VALUES (?, ?)');
                $stmt->execute([$name, $description]);
                redirect_to('/admin.php', 'Đã thêm danh mục.');
            } elseif ($action === 'delete_comment') {
                $stmt = $pdo->prepare('DELETE FROM comments WHERE id=?');
                $stmt->execute([(int)($_POST['id'] ?? 0)]);
                redirect_to('/admin.php#comments', 'Đã xóa bình luận.');
            } else fail(400, 'Thao tác không hợp lệ.');
        } catch (InvalidArgumentException $e) {
            $error = $e->getMessage(); http_response_code(422);
        } catch (PDOException $e) {
            error_log('Thao tác quản trị thất bại: ' . $e->getMessage());
            $error = 'Không lưu được dữ liệu. Danh mục có thể đã tồn tại hoặc thông tin được chọn không hợp lệ.';
            http_response_code(422);
        }
    }
}

$categories = $recipes = $comments = [];
if ($admin) {
    $categories = $pdo->query('SELECT id, name FROM categories ORDER BY id')->fetchAll();
    $recipes = $pdo->query('SELECT r.id, r.title, c.name AS category_name FROM recipes r JOIN categories c ON c.id=r.category_id ORDER BY r.created_at DESC, r.id DESC')->fetchAll();
    $comments = $pdo->query('SELECT cm.*, r.title FROM comments cm JOIN recipes r ON r.id=cm.recipe_id ORDER BY cm.created_at DESC, cm.id DESC LIMIT 30')->fetchAll();
    if (!$form && (int)($_GET['edit'] ?? 0) > 0) {
        $stmt = $pdo->prepare('SELECT * FROM recipes WHERE id=?');
        $stmt->execute([(int)$_GET['edit']]);
        $form = $stmt->fetch();
        if (!$form) fail(404, 'Công thức không tồn tại.');
    }
}
?>
<!doctype html>
<html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Quản trị · Bếp Nhà</title><link rel="stylesheet" href="/style.css"></head>
<body><header><div class="header-row"><div><h1><a href="/admin.php">Bếp Nhà · Quản trị</a></h1><p>Tạo và chia sẻ công thức nấu ăn</p></div><nav aria-label="Điều hướng"><a href="/">Xem trang người dùng</a></nav></div></header>
<main>
<?php if ($flash): ?><p class="success" role="status"><?= h($flash) ?></p><?php endif; ?>
<?php if ($error): ?><p class="notice" role="alert"><?= h($error) ?></p><?php endif; ?>
<?php if (!$admin): ?>
<section class="card login"><h2>Đăng nhập quản trị</h2><p class="meta">Dành cho người quản lý nội dung Bếp Nhà.</p>
<form method="post" action="/admin.php"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="login">
<label for="username">Tên đăng nhập</label><input id="username" name="username" maxlength="50" autocomplete="username" value="<?= h(text('username')) ?>" required>
<label for="password">Mật khẩu</label><input id="password" name="password" type="password" maxlength="72" autocomplete="current-password" required>
<button type="submit">Đăng nhập</button></form><a href="/">Quay lại xem công thức</a></section>
<?php else: ?>
<section class="toolbar"><div><h2>Quản lý công thức</h2><p class="meta">Xin chào, <?= h($admin['fullname']) ?></p></div><form method="post" action="/admin.php"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><button type="submit" name="action" value="logout">Đăng xuất</button></form></section>
<div class="layout admin-layout"><section>
<section class="card" id="recipe-form"><h2><?= !empty($form['id']) ? 'Sửa công thức' : 'Tạo công thức mới' ?></h2>
<form method="post" action="/admin.php"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="save_recipe"><input type="hidden" name="id" value="<?= (int)($form['id'] ?? 0) ?>">
<label for="title">Tên món ăn</label><input id="title" name="title" maxlength="255" value="<?= h($form['title'] ?? '') ?>" required>
<label for="category_id">Danh mục</label><select id="category_id" name="category_id" required><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= ($form['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select>
<div class="row"><div><label for="cooking_minutes">Thời gian (phút)</label><input type="number" id="cooking_minutes" name="cooking_minutes" min="1" max="1440" value="<?= (int)($form['cooking_minutes'] ?? 30) ?>" required></div><div><label for="servings">Số người ăn</label><input type="number" id="servings" name="servings" min="1" max="100" value="<?= (int)($form['servings'] ?? 2) ?>" required></div></div>
<label for="ingredients">Nguyên liệu</label><textarea id="ingredients" name="ingredients" rows="5" maxlength="10000" required><?= h($form['ingredients'] ?? '') ?></textarea>
<label for="instructions">Cách làm</label><textarea id="instructions" name="instructions" rows="6" maxlength="10000" required><?= h($form['instructions'] ?? '') ?></textarea>
<button type="submit"><?= !empty($form['id']) ? 'Lưu thay đổi' : 'Đăng và chia sẻ công thức' ?></button><?php if (!empty($form['id'])): ?><a href="/admin.php">Hủy chỉnh sửa</a><?php endif; ?></form></section>
<section class="card"><h2>Thêm danh mục</h2><form method="post" action="/admin.php"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="add_category"><label for="category_name">Tên danh mục</label><input id="category_name" name="category_name" maxlength="100" required><label for="description">Mô tả ngắn</label><input id="description" name="description" maxlength="255"><button type="submit">Thêm danh mục</button></form></section>
</section><section>
<section class="card"><h2>Công thức đã chia sẻ <span class="badge"><?= count($recipes) ?> món</span></h2>
<div class="table-wrap"><table><thead><tr><th>Công thức</th><th>Thao tác</th></tr></thead><tbody>
<?php foreach ($recipes as $r): ?><tr><td><a href="/?id=<?= (int)$r['id'] ?>"><?= h($r['title']) ?></a><br><small><?= h($r['category_name']) ?></small></td><td><div class="actions"><a href="/admin.php?edit=<?= (int)$r['id'] ?>#recipe-form">Sửa</a><form method="post" action="/admin.php" onsubmit="return confirm('Xóa công thức và các bình luận của món này?')"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="danger" type="submit" name="action" value="delete_recipe">Xóa</button></form></div></td></tr><?php endforeach; ?>
<?php if (!$recipes): ?><tr><td colspan="2">Chưa có công thức. Hãy đăng món đầu tiên.</td></tr><?php endif; ?>
</tbody></table></div></section>
<section class="card" id="comments"><h2>Bình luận gần đây</h2><p class="meta">Tối đa 30 bình luận mới nhất. Bạn có thể xóa nội dung không phù hợp.</p>
<?php if (!$comments): ?><p>Chưa có bình luận.</p><?php endif; ?>
<?php foreach ($comments as $comment): ?><article class="comment"><strong><?= h($comment['author_name']) ?></strong><p class="meta"><a href="/?id=<?= (int)$comment['recipe_id'] ?>#comments"><?= h($comment['title']) ?></a> · <?= h($comment['created_at']) ?></p><p><?= nl2br(h($comment['content'])) ?></p><div class="actions"><form method="post" action="/admin.php"><input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="id" value="<?= (int)$comment['id'] ?>"><button class="danger" type="submit" name="action" value="delete_comment">Xóa bình luận</button></form></div></article><?php endforeach; ?>
</section></section></div>
<?php endif; ?>
</main><footer>Bếp Nhà · Trang quản trị — Đề 29</footer></body></html>
