<?php
require_once 'db.php';
$id = $_GET['id'] ?? null;
$food = ['name_th' => '', 'category' => '', 'image' => ''];
$recipes = [];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM foods WHERE id = ?");
    $stmt->execute([$id]);
    $food = $stmt->fetch();
    if (!$food) {
        header("Location: index.php");
        exit;
    }
    $stmtR = $pdo->prepare("SELECT * FROM recipes WHERE food_id = ?");
    $stmtR->execute([$id]);
    $recipes = $stmtR->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name_th = $_POST['name_th'];
    $category = $_POST['category'];

    // --- ส่วนจัดการการอัปโหลดรูปภาพ ---
    $image_name = $food['image']; // ใช้รูปเดิมเป็นค่าเริ่มต้น
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['image']['tmp_name'];
        $original_name = $_FILES['image']['name'];
        $ext = pathinfo($original_name, PATHINFO_EXTENSION);

        // ตรวจสอบนามสกุลไฟล์เพื่อป้องกันความปลอดภัย
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array(strtolower($ext), $allowed)) {
            // สร้างชื่อไฟล์ใหม่เพื่อไม่ให้ซ้ำกันด้วย time()
            $image_name = time() . '_' . uniqid() . '.' . $ext;

            // สร้างโฟลเดอร์ uploads อัตโนมัติถ้ายังไม่มี
            if (!is_dir('uploads')) {
                mkdir('uploads', 0777, true);
            }

            // ย้ายไฟล์เข้าโฟลเดอร์ uploads
            if (move_uploaded_file($file_tmp, "uploads/" . $image_name)) {
                // ถ้าเป็นการแก้ไขและมีรูปภาพเก่าอยู่ ให้ลบรูปเก่าออกก่อน
                if ($id && !empty($food['image']) && file_exists("uploads/" . $food['image'])) {
                    unlink("uploads/" . $food['image']);
                }
            }
        }
    }

    if ($id) {
        // --- ส่วนแก้ไข (Update) ---
        $stmt = $pdo->prepare("UPDATE foods SET name_th = ?, category = ?, image = ? WHERE id = ?");
        $stmt->execute([$name_th, $category, $image_name, $id]);

        $pdo->prepare("DELETE FROM recipes WHERE food_id = ?")->execute([$id]);
        $food_id = $id;
    } else {
        // --- ส่วนเพิ่มใหม่ (Create) ---
        $stmt = $pdo->prepare("INSERT INTO foods (name_th, category, image) VALUES (?, ?, ?)");
        $stmt->execute([$name_th, $category, $image_name]);
        $food_id = $pdo->lastInsertId();
    }

    if (isset($_POST['recipes']) && is_array($_POST['recipes'])) {
        $stmtRecipe = $pdo->prepare("INSERT INTO recipes (food_id, recipe_name, quantity, unit_name) VALUES (?, ?, ?, ?)");
        foreach ($_POST['recipes'] as $r) {
            if (!empty($r['recipe_name']) && !empty($r['quantity'])) {
                $stmtRecipe->execute([$food_id, $r['recipe_name'], $r['quantity'], $r['unit_name']]);
            }
        }
    }
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="UTF-8">
    <title><?= $id ? 'แก้ไข' : 'เพิ่ม' ?>เมนูอาหาร</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container mt-5" style="max-width: 700px;">
        <div class="card shadow">
            <div class="card-header bg-dark text-white">
                <h4 class="mb-0"><?= $id ? '✏️ แก้ไขข้อมูลเมนูอาหาร' : '➕ เพิ่มข้อมูลเมนูอาหารใหม่' ?></h4>
            </div>
            <div class="card-body">
                <form action="" method="POST" enctype="multipart/form-data">
                    <h5 class="text-primary border-bottom pb-2">ข้อมูลอาหาร</h5>
                    <div class="mb-3">
                        <label class="form-label">ชื่ออาหาร (ภาษาไทย) <span class="text-danger">*</span></label>
                        <input type="text" name="name_th" class="form-control"
                            value="<?= htmlspecialchars($food['name_th']) ?>" required placeholder="เช่น ผัดไทยกุ้งสด">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">หมวดหมู่ <span class="text-danger">*</span></label>
                        <select name="category" class="form-select" required>
                            <option value="">-- เลือกหมวดหมู่ --</option>
                            <option value="อาหารคาว" <?= $food['category'] == 'อาหารคาว' ? 'selected' : '' ?>>อาหารคาว
                            </option>
                            <option value="อาหารหวาน" <?= $food['category'] == 'อาหารหวาน' ? 'selected' : '' ?>>อาหารหวาน
                            </option>
                            <option value="เครื่องดื่ม" <?= $food['category'] == 'เครื่องดื่ม' ? 'selected' : '' ?>>
                                เครื่องดื่ม
                            </option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="form-label">รูปภาพอาหาร (JPG, PNG, WEBP)</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <?php if ($id && !empty($food['image']) && file_exists("uploads/" . $food['image'])): ?>
                            <div class="mt-2">
                                <p class="text-muted small mb-1">รูปภาพปัจจุบัน:</p>
                                <img src="uploads/<?= htmlspecialchars($food['image']) ?>" class="img-thumbnail"
                                    style="max-width: 150px;">
                            </div>
                        <?php endif; ?>
                    </div>

                    <h5 class="text-primary border-bottom pb-2 d-flex justify-content-between align-items-center">
                        สูตรและวัตถุดิบ (Recipe)
                        <button type="button" class="btn btn-sm btn-outline-success" id="add-recipe-btn">+
                            เพิ่มแถววัตถุดิบ</button>
                    </h5>

                    <div id="recipe-container">
                        <?php if (empty($recipes)): ?>
                            <div class="row g-2 mb-2 recipe-row">
                                <div class="col-6">
                                    <input type="text" name="recipes[0][recipe_name]" class="form-control"
                                        placeholder="ชื่อวัตถุดิบ เช่น เส้นเล็ก">
                                </div>
                                <div class="col-3">
                                    <input type="number" step="0.01" name="recipes[0][quantity]" class="form-control"
                                        placeholder="ปริมาณ เช่น 150">
                                </div>
                                <div class="col-3">
                                    <input type="text" name="recipes[0][unit_name]" class="form-control"
                                        placeholder="หน่วย เช่น กรัม">
                                </div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($recipes as $index => $r): ?>
                                <div class="row g-2 mb-2 recipe-row">
                                    <div class="col-6">
                                        <input type="text" name="recipes[<?= $index ?>][recipe_name]" class="form-control"
                                            value="<?= htmlspecialchars($r['recipe_name']) ?>" required>
                                    </div>
                                    <div class="col-3">
                                        <input type="number" step="0.01" name="recipes[<?= $index ?>][quantity]"
                                            class="form-control" value="<?= $r['quantity'] ?>" required>
                                    </div>
                                    <div class="col-2">
                                        <input type="text" name="recipes[<?= $index ?>][unit_name]" class="form-control"
                                            value="<?= htmlspecialchars($r['unit_name']) ?>" required>
                                    </div>
                                    <div class="col-1 text-end">
                                        <button type="button" class="btn btn-danger btn-sm w-100"
                                            onclick="this.closest('.recipe-row').remove();">X</button>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="mt-4 pt-3 border-top d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-secondary">ยกเลิก</a>
                        <button type="submit" class="btn btn-success">💾 บันทึกข้อมูล</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        // ฟังก์ชันสำหรับจัดเรียง index ของ input ใหม่ทุกครั้งที่มีการเพิ่มหรือลบ
        function reindexRecipes() {
            const rows = document.querySelectorAll('#recipe-container .recipe-row');
            rows.forEach((row, index) => {
                // ปรับชื่อ input ของวัตถุดิบ
                const nameInput = row.querySelector('input[name*="[recipe_name]"]');
                if (nameInput) nameInput.name = `recipes[${index}][recipe_name]`;

                // ปรับชื่อ input ของปริมาณ
                const qtyInput = row.querySelector('input[name*="[quantity]"]');
                if (qtyInput) qtyInput.name = `recipes[${index}][quantity]`;

                // ปรับชื่อ input ของหน่วย
                const unitInput = row.querySelector('input[name*="[unit_name]"]');
                if (unitInput) unitInput.name = `recipes[${index}][unit_name]`;
            });
        }

        // ฟังก์ชันสำหรับลบแถว (สร้างแยกมาเพื่อให้สามารถสั่ง reindex ต่อได้ทันที)
        function removeRecipeRow(button) {
            button.closest('.recipe-row').remove();
            reindexRecipes(); // เรียงลำดับ index ใหม่ทันที ข้อมูลที่พิมพ์ค้างไว้จะไม่เอ๋อ
        }

        // เหตุการณ์กดปุ่มเพิ่มแถววัตถุดิบ
        document.getElementById('add-recipe-btn').addEventListener('click', function () {
            const container = document.getElementById('recipe-container');
            const newRow = document.createElement('div');
            newRow.className = 'row g-2 mb-2 recipe-row';

            // ใช้ฟังก์ชัน removeRecipeRow(this) ในปุ่มลบแทนอันเดิม
            newRow.innerHTML = `
            <div class="col-6">
                <input type="text" name="recipes[TEMP][recipe_name]" class="form-control" placeholder="ชื่อวัตถุดิบ" required>
            </div>
            <div class="col-3">
                <input type="number" step="0.01" name="recipes[TEMP][quantity]" class="form-control" placeholder="ปริมาณ" required>
            </div>
            <div class="col-2">
                <input type="text" name="recipes[TEMP][unit_name]" class="form-control" placeholder="หน่วย" required>
            </div>
            <div class="col-1 text-end">
                <button type="button" class="btn btn-danger btn-sm w-100" onclick="removeRecipeRow(this)">X</button>
            </div>
        `;

            container.appendChild(newRow);
            reindexRecipes(); // อัปเดตให้แถวใหม่ได้ index ที่ถูกต้องล่าสุดเสมอ โดยไม่กระทบค่าที่พิมพ์ไว้ในแถวเก่า
        });
    </script>
</body>

</html>