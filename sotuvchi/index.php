<?php
session_start();
require_once "../config/dokon_db.php";
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'sotuvchi') {
    header("Location: ../auth/login.php");
    exit;
}

$st = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM settings WHERE id=1"));
$products = mysqli_query($conn, "SELECT * FROM products WHERE quantity > 0 ORDER BY name ASC");

include "includes/header.php"; 
?>

<div class="pos-layout no-print">
    <div class="left-pane">
        <div class="search-wrapper">
            <i class="fas fa-barcode search-icon"></i>
            <input type="text" id="searchProduct" class="search-box" placeholder="Shtrix-kodni skanerlang yoki qidiruv (Enter)..." autofocus autocomplete="off">
        </div>
        
        <div class="category-ribbon">
            <div class="cat-btn active" onclick="filterCat('all', this)">Barchasi</div>
            <div class="cat-btn" onclick="filterCat('drink', this)">Ichimliklar</div>
            <div class="cat-btn" onclick="filterCat('snack', this)">Shirinliklar</div>
            <div class="cat-btn" onclick="filterCat('food', this)">Oziq-ovqat</div>
        </div>

        <div class="products-grid" id="productList">
            <?php while($p = mysqli_fetch_assoc($products)): ?>
            <div class="product-card item-card" data-name="<?= strtolower($p['name']) ?>" onclick="addToCart(<?= $p['id'] ?>, '<?= addslashes($p['name']) ?>', <?= $p['price'] ?>, <?= $p['quantity'] ?>)">
                <div class="stock-badge"><?= $p['quantity'] ?> ta</div>
                <div class="p-name"><?= $p['name'] ?></div>
                <div class="p-price"><?= number_format($p['price'], 0, '.', ' ') ?> <small class="text-muted" style="font-size: 12px;">uzs</small></div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <div class="right-pane">
        <div class="cart-header">
            <h2 class="cart-title">Savat</h2>
            
            <div class="d-flex gap-2">
                <button class="btn btn-warning font-weight-bold shadow-sm" onclick="holdCurrentCart()" title="Mijoz puli qolib ketsa, savatni kutishga oling">
                    <i class="fas fa-pause mr-1"></i> Kutish
                </button>
                <button class="btn btn-info font-weight-bold shadow-sm ml-2" onclick="showHeldCartsModal()">
                    <i class="fas fa-list mr-1"></i> (<span id="heldCartsCount">0</span>)
                </button>
                <button class="btn btn-light border ml-2 text-danger" onclick="clearCart()" title="Tozalash"><i class="fas fa-trash-alt"></i></button>
            </div>
        </div>
        
        <div class="cart-items" id="cartContent"></div>
        
        <div class="cart-footer">
            <div class="calc-row">
                <span>Oraliq summa:</span>
                <span id="subTotalStr" class="font-weight-bold text-dark">0</span>
            </div>
            
            <div class="calc-row discount">
                <span>Chegirma (%): <br><small class="text-muted" style="font-size:11px;" id="discountSumStr">0 UZS</small></span>
                <div class="d-flex align-items-center">
                    <input type="number" id="discountInput" value="0" min="0" max="100" oninput="renderCart()" onfocus="this.select()">
                    <span class="ml-1 font-weight-bold">%</span>
                </div>
            </div>
            
            <div class="total-row">
                <div class="total-label">Jami To'lov</div>
                <div class="total-amount" id="totalSumStr">0</div>
            </div>
            
            <button class="btn-pay-modern" onclick="openPaymentModal()">
                <div class="pay-text"><i class="fas fa-wallet" style="font-size: 24px;"></i> TO'LOV</div>
                <div class="pay-hotkey"><i class="fas fa-keyboard mr-1"></i> F2</div>
            </button>
        </div>
    </div>
</div>

<?php include "includes/footer.php"; ?>