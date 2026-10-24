<?php
require __DIR__ . '/common.php';
$error = '';
$commentName = text('author_name');
$commentContent = text('content');

if (isset($_GET['edit'])) fail(403, 'Chỉnh sửa công thức chỉ có trong trang quản trị.');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Trang công khai chỉ nhận bình luận, kể cả khi client tự tạo POST.
    if (text('action') !== 'add_comment' || isset($_POST['save_recipe']) || isset($_POST['delete_recipe']) || isset($_POST['add_category'])) {
        fail(403, 'Bạn chỉ được xem và bình luận. Quản lý công thức cần đăng nhập trang quản trị.');
    }
    csrf_check();
    $recipeId = filter_var($_POST['recipe_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$recipeId) fail(422, 'Công thức không hợp lệ.');
    $stmt = $pdo->prepare('SELECT id FROM recipes WHERE id=?');
    $stmt->execute([$recipeId]);
    if (!$stmt->fetch()) fail(404, 'Công thức không tồn tại.');
    if ($commentName === '' || chars($commentName) === false || chars($commentName) > 100
        || $commentContent === '' || chars($commentContent) === false || chars($commentContent) > 2000) {
        $error = 'Nhập tên tối đa 100 ký tự và nội dung bình luận từ 1 đến 2000 ký tự.';
        http_response_code(422);
    } else {
        try {
            $stmt = $pdo->prepare('INSERT INTO comments (recipe_id, author_name, content) VALUES (?, ?, ?)');
            $stmt->execute([$recipeId, $commentName, $commentContent]);
            redirect_to('/?id=' . $recipeId . '#comments', 'Đã gửi bình luận của bạn.');
        } catch (PDOException $e) {
            error_log('Lưu bình luận thất bại: ' . $e->getMessage());
            $error = 'Chưa gửi được bình luận. Vui lòng thử lại.';
            http_response_code(422);
        }
    }
}

$categories = $pdo->query('SELECT c.*, COUNT(r.id) AS total_recipes FROM categories c LEFT JOIN recipes r ON r.category_id=c.id GROUP BY c.id ORDER BY c.id')->fetchAll();
$q = trim(is_string($_GET['q'] ?? null) ? $_GET['q'] : '');
if (chars($q) === false || chars($q) > 200) fail(422, 'Từ khóa tối đa 200 ký tự.');
$category = (int)($_GET['category'] ?? 0);
$detail = $recipeId ?? (int)($_GET['id'] ?? 0);
$sql = 'SELECT r.*, c.name AS category_name, u.fullname, (SELECT COUNT(*) FROM comments cm WHERE cm.recipe_id=r.id) AS comment_count FROM recipes r JOIN categories c ON c.id=r.category_id JOIN users u ON u.id=r.user_id WHERE 1=1';
$params = [];
if ($q !== '' && !$detail) { $sql .= ' AND (r.title LIKE ? OR r.ingredients LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }
if ($category > 0 && !$detail) { $sql .= ' AND r.category_id=?'; $params[] = $category; }
if ($detail > 0) { $sql .= ' AND r.id=?'; $params[] = $detail; }
$stmt = $pdo->prepare($sql . ' ORDER BY r.created_at DESC, r.id DESC');
$stmt->execute($params);
$recipes = $stmt->fetchAll();
if ($detail > 0 && !$recipes) http_response_code(404);
$comments = [];
if ($detail > 0 && $recipes) {
    $stmt = $pdo->prepare('SELECT author_name, content, created_at FROM comments WHERE recipe_id=? ORDER BY created_at DESC, id DESC LIMIT 100');
    $stmt->execute([$detail]);
    $comments = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="vi"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Bếp Nhà · Công thức nấu ăn</title><link rel="stylesheet" href="/style.css">
</head><body>
<header><div class="header-row"><div><h1><a href="/">Bếp Nhà · Công thức nấu ăn</a></h1><p>Chia sẻ món ngon cho bữa cơm gia đình</p></div><nav aria-label="Điều hướng"><a href="/">Khám phá</a><a href="/admin.php"><?= $admin ? 'Trang quản trị' : 'Quản trị viên' ?></a></nav></div></header>
<main>
<?php if ($flash): ?><p class="success" role="status"><?= h($flash) ?></p><?php endif; ?>
<?php if ($error): ?><p class="notice" role="alert"><?= h($error) ?></p><?php endif; ?>
<section class="card">
<form method="get"><label for="q">Tìm món ăn hoặc nguyên liệu</label>
<div class="row"><input id="q" name="q" value="<?= h($q) ?>" placeholder="Ví dụ: gà, gừng, đậu phụ…">
<select name="category" aria-label="Lọc theo danh mục"><option value="0">Tất cả danh mục</option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $category == $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option><?php endforeach; ?></select></div>
<button type="submit">Tìm công thức</button></form>
</section>
<div class="layout"><section>
<h2><?= $detail > 0 ? 'Chi tiết công thức' : 'Khám phá công thức' ?> <span class="badge"><?= count($recipes) ?> món</span></h2>
<?php if (!$recipes): ?><div class="card">Không tìm thấy công thức phù hợp. <a href="/">Xem tất cả món</a></div><?php endif; ?>
<?php foreach ($recipes as $r): ?>
<article class="card recipe">
<a class="badge" href="/?category=<?= (int)$r['category_id'] ?>"><?= h($r['category_name']) ?></a>
<h2><a href="/?id=<?= (int)$r['id'] ?>"><?= h($r['title']) ?></a></h2>
<p class="meta"><?= h($r['fullname']) ?> · <?= (int)$r['cooking_minutes'] ?> phút · <?= (int)$r['servings'] ?> người</p>
<h3>Nguyên liệu</h3><p><?= nl2br(h($r['ingredients'])) ?></p>
<h3>Cách làm</h3><p><?= nl2br(h($r['instructions'])) ?></p>
<a href="/?id=<?= (int)$r['id'] ?>#comments">Bình luận (<?= (int)$r['comment_count'] ?>)</a>
</article>
<?php endforeach; ?>
<?php if ($detail > 0 && $recipes): ?>
<section class="card" id="comments"><h2>Bình luận về món ăn</h2>
<form method="post" action="/?id=<?= $detail ?>#comments">
<input type="hidden" name="csrf" value="<?= h($_SESSION['csrf']) ?>"><input type="hidden" name="action" value="add_comment"><input type="hidden" name="recipe_id" value="<?= $detail ?>">
<label for="author_name">Tên của bạn</label><input id="author_name" name="author_name" value="<?= h($commentName) ?>" maxlength="100" autocomplete="name" required>
<label for="content">Nội dung bình luận</label><textarea id="content" name="content" rows="4" maxlength="2000" placeholder="Chia sẻ cảm nhận hoặc hỏi thêm về cách làm…" required><?= h($commentContent) ?></textarea>
<p class="meta">Bình luận sẽ hiển thị công khai cùng tên bạn nhập.</p><button type="submit">Gửi bình luận</button></form>
<h3>Bình luận mới nhất <span class="badge"><?= (int)$recipes[0]['comment_count'] ?></span></h3>
<?php if (!$comments): ?><p class="meta">Chưa có bình luận. Hãy là người đầu tiên chia sẻ cảm nhận!</p><?php endif; ?>
<?php foreach ($comments as $comment): ?><article class="comment"><strong><?= h($comment['author_name']) ?></strong><span class="meta"> · <?= h($comment['created_at']) ?></span><p><?= nl2br(h($comment['content'])) ?></p></article><?php endforeach; ?>
<?php if ((int)$recipes[0]['comment_count'] > 100): ?><p class="meta">Đang hiển thị 100 bình luận gần nhất.</p><?php endif; ?>
</section>
<?php endif; ?>
</section><aside>
<section class="card"><h2>Danh mục món ăn</h2><table><thead><tr><th>Danh mục</th><th>Số món</th></tr></thead><tbody><?php foreach ($categories as $c): ?><tr><td><a href="/?category=<?= (int)$c['id'] ?>"><?= h($c['name']) ?></a></td><td><?= (int)$c['total_recipes'] ?></td></tr><?php endforeach; ?></tbody></table></section>
<section class="card"><h2>Cùng vào bếp</h2><p>Tìm một món theo nguyên liệu đang có, chọn danh mục yêu thích và mở công thức để xem cách làm.</p><p>Bạn có thể để lại cảm nhận hoặc câu hỏi trong phần bình luận của từng món.</p></section>
</aside></div>
</main><footer>Bếp Nhà · Website công thức nấu ăn — Đề 29</footer>
</body></html>
