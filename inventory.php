<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
include 'includes/header.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    $sku = trim($_POST['sku']);
    $name = trim($_POST['name']);
    $price = (float)$_POST['price'];
    $cost = isset($_POST['cost']) && $_POST['cost'] !== '' ? (float)$_POST['cost'] : 0;
    $stock = (int)$_POST['stock_qty'];
    $reorder = isset($_POST['reorder_level']) ? (int)$_POST['reorder_level'] : 5;

    $stmt = $pdo->prepare("INSERT INTO products (sku, name, price, cost, stock_qty, reorder_level)
                           VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$sku, $name, $price, $cost, $stock, $reorder]);
    header('Location: inventory.php');
    exit;
}

if (isset($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
    header('Location: inventory.php');
    exit;
}
?>
<h2>Inventory</h2>
<div class="row">
    <div class="col-md-4">
        <div class="card">
            <div class="card-header">Add Product</div>
            <div class="card-body">
                <form method="POST">
                    <div class="mb-2"><input name="sku" class="form-control" placeholder="SKU" required></div>
                    <div class="mb-2"><input name="name" class="form-control" placeholder="Product Name" required></div>
                    <div class="mb-2"><input name="price" type="number" step="0.01" class="form-control" placeholder="Price" required></div>
                    <div class="mb-2"><input name="cost" type="number" step="0.01" min="0" class="form-control" placeholder="Cost"></div>
                    <div class="mb-2"><input name="stock_qty" type="number" class="form-control" placeholder="Stock Qty" required></div>
                    <div class="mb-2"><input name="reorder_level" type="number" class="form-control" placeholder="Reorder Level" value="5"></div>
                    <button type="submit" name="add_product" class="btn btn-primary">Add Product</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header">Product List</div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead><tr><th>SKU</th><th>Name</th><th>Price</th><th>Cost</th><th>Stock</th><th>Reorder</th><th></th></tr></thead>
                    <tbody>
                    <?php
                    $products = $pdo->query("SELECT * FROM products ORDER BY id DESC");
                    while ($p = $products->fetch()): ?>
                        <tr>
                            <td><?= htmlspecialchars($p['sku']) ?></td>
                            <td><?= htmlspecialchars($p['name']) ?></td>
                            <td>KSh <?= number_format((float)$p['price'], 2) ?></td>
                            <td>KSh <?= number_format((float)($p['cost'] ?? 0), 2) ?></td>
                            <td><?= (int)$p['stock_qty'] ?></td>
                            <td><?= (int)$p['reorder_level'] ?></td>
                            <td>
                                <a href="inventory.php?delete=<?= (int)$p['id'] ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete?')">Delete</a>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php include 'includes/footer.php'; ?>

