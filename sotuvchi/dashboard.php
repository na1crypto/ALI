<?php
session_start();

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: ../auth/login.php");
    exit;
}

require_once "../config/dokon_db.php";
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'sotuvchi') {
    header("Location: ../auth/login.php");
    exit;
}

$st = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM settings WHERE id=1"));

// Kategoriyalarni bazadan tortamiz
$categories = mysqli_query($conn, "SELECT * FROM categories ORDER BY name ASC");

// Mahsulotlarni tortamiz
$products = mysqli_query($conn, "SELECT * FROM products WHERE quantity > 0 ORDER BY name ASC");
?>
<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="utf-8">
    <title>POS Terminal | <?= htmlspecialchars($st['store_name'] ?? 'SMART POS') ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/css/bootstrap.min.css">
    
    <link rel="stylesheet" href="pos_style.css">
</head>
<body>

<nav class="pos-navbar no-print">
    <div class="d-flex align-items-center gap-2">
        <div class="logo-text">
            <i class="fas fa-cash-register mr-1"></i><?= htmlspecialchars($st['store_name'] ?? 'SMART POS') ?>
        </div>
        <div class="clock-badge d-none d-sm-block ml-2">
            <i class="far fa-clock mr-1 text-primary"></i><span id="clock">00:00</span>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2">
        <div class="font-weight-bold text-dark px-2 mr-2">
            <i class="fas fa-user-circle text-muted" style="font-size: 20px; vertical-align: middle;"></i> 
            <span class="d-none d-sm-inline"><?= htmlspecialchars($_SESSION['name'] ?? 'Sotuvchi') ?></span>
        </div>
        <button class="btn btn-light border rounded-pill font-weight-bold text-muted px-3" onclick="toggleFullScreen()" title="To'liq ekran">
            <i class="fas fa-expand"></i>
        </button>
        <a href="?logout=1" class="btn btn-danger rounded-pill px-3 font-weight-bold shadow-sm" title="Chiqish">
            <i class="fas fa-power-off"></i>
        </a>
    </div>
</nav>

<div class="pos-layout">
    
    <div class="left-pane">
        <div class="search-wrapper">
            <i class="fas fa-barcode search-icon"></i>
            <input type="text" id="searchProduct" class="search-box" placeholder="Skaner yoki qidiruv (Enter)..." autofocus autocomplete="off">
        </div>
        
        <div class="category-ribbon">
            <div class="cat-btn active" onclick="filterCat('all', this)">Barchasi</div>
            <?php if ($categories): while($cat = mysqli_fetch_assoc($categories)): ?>
                <div class="cat-btn" onclick="filterCat('<?= $cat['id'] ?>', this)"><?= htmlspecialchars($cat['name']) ?></div>
            <?php endwhile; endif; ?>
        </div>

        <div class="products-grid" id="productList">
            <?php while($p = mysqli_fetch_assoc($products)): ?>
            <div class="product-card item-card" 
                 data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>" 
                 data-category="<?= $p['category_id'] ?? 0 ?>"
                 data-price="<?= $p['price'] ?>"
                 data-optom="<?= $p['optom_price'] > 0 ? $p['optom_price'] : $p['price'] ?>"
                 onclick="addToCart(<?= $p['id'] ?>, '<?= addslashes(htmlspecialchars($p['name'])) ?>', <?= $p['price'] ?>, <?= $p['optom_price'] ?? 0 ?>, <?= $p['quantity'] ?>)">
                 
                <div class="stock-badge"><?= $p['quantity'] ?></div>
                <div class="p-name"><?= htmlspecialchars($p['name']) ?></div>
                <div class="p-price">
                    <span class="p-price-val"><?= number_format($p['price'], 0, '.', ' ') ?></span> 
                    <small style="font-size: 10px; color: inherit; opacity: 0.7;">uzs</small>
                </div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <div class="right-pane">
        
        <div class="cart-header">
            <div class="d-flex align-items-center">
                <h2 class="cart-title m-0 mr-3">Savat</h2>
                <button class="btn btn-outline-danger font-weight-bold px-3 py-1 optom-btn" id="optomBtnBtn" onclick="toggleOptomMode()">
                    <i class="fas fa-boxes mr-1"></i> OPTOM: O'CHIQ
                </button>
            </div>

            <div class="d-flex gap-1 mt-2 mt-sm-0">
                <button class="btn btn-warning btn-sm font-weight-bold shadow-sm rounded-lg px-2" onclick="holdCurrentCart()" title="Kutish">
                    <i class="fas fa-pause"></i>
                </button>
                <button class="btn btn-info btn-sm font-weight-bold shadow-sm rounded-lg px-2" onclick="showHeldCartsModal()">
                    <i class="fas fa-list"></i> <span id="heldCartsCount">0</span>
                </button>
                <button class="btn btn-light btn-sm border text-danger rounded-lg px-2 ml-1" onclick="clearCart()" title="Tozalash">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        </div>
        
        <div class="cart-items" id="cartContent"></div>
        
        <div class="cart-footer">
            <div class="calc-row">
                <span>Oraliq summa:</span>
                <span id="subTotalStr" class="font-weight-bold text-dark">0</span>
            </div>
            <div class="calc-row">
                <span>Dollar kursi ($1):</span>
                <input type="number" id="exchangeRate" value="12600" class="calc-input text-primary" oninput="renderCart(); updateNumpadDisplay();" onfocus="this.select()">
            </div>
            <div class="calc-row">
                <span class="text-danger">Chegirma (%):</span>
                <div class="d-flex align-items-center">
                    <small class="text-danger mr-2" id="discountSumStr">0</small>
                    <input type="number" id="discountInput" value="0" min="0" max="100" class="calc-input danger" oninput="renderCart()" onfocus="this.select()">
                </div>
            </div>
            
            <div class="total-row">
                <div class="total-label">Jami</div>
                <div class="text-right">
                    <div class="total-amount" id="totalSumStr">0</div>
                    <div class="total-usd" id="totalSumUSD">$0.00</div>
                </div>
            </div>
            
            <button class="btn-pay-modern" onclick="openPaymentModal()">
                <div class="pay-text"><i class="fas fa-wallet" style="font-size: 20px;"></i> TO'LOV</div>
                <div class="pay-hotkey"><i class="fas fa-keyboard mr-1"></i> F2</div>
            </button>
        </div>
    </div>
</div>

<div class="modal fade" id="paymentModal" tabindex="-1" aria-hidden="true" data-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <button type="button" class="close position-absolute" style="top: 15px; right: 20px; font-size: 35px; z-index: 10; outline: none;" data-dismiss="modal">&times;</button>
            <div class="pay-split">
                
                <div class="pay-left">
                    <h6 class="text-uppercase text-muted font-weight-bold mb-2">To'lanishi kerak:</h6>
                    <h1 class="pay-amount-huge" id="modalTotalSum">0</h1>
                    <h4 class="pay-amount-usd" id="modalTotalSumUSD">$0.00</h4>
                    
                    <div class="d-flex mb-4 bg-light p-1" style="border-radius: 14px; border: 1px solid #e2e8f0;">
                        <button class="btn flex-fill font-weight-bold py-2" id="btnNaqd" onclick="setMethod('naqd')" style="border-radius: 10px;">
                            <i class="fas fa-money-bill-wave mr-2"></i> Naqd
                        </button>
                        <button class="btn flex-fill font-weight-bold py-2 text-muted" id="btnKarta" onclick="setMethod('karta')" style="border-radius: 10px;">
                            <i class="fas fa-credit-card mr-2"></i> Karta
                        </button>
                    </div>

                    <label class="font-weight-bold text-muted mb-2">Mijoz bergan summa:</label>
                    <input type="text" class="payment-input-box" id="receivedCash" placeholder="0" readonly>
                    
                    <div class="row mx-0 gap-2 mb-3">
                        <button class="btn btn-light border font-weight-bold py-2 col rounded-lg" onclick="numPadExact()">Aniq</button>
                        <button class="btn btn-light border font-weight-bold py-2 col rounded-lg" onclick="numPadAdd(50000)">50K</button>
                        <button class="btn btn-light border font-weight-bold py-2 col rounded-lg" onclick="numPadAdd(100000)">100K</button>
                        <button class="btn btn-light border font-weight-bold py-2 col rounded-lg" onclick="numPadAdd(100000)">200K</button>
                        <button class="btn btn-success font-weight-bold py-2 col rounded-lg" onclick="numPadAddUSD(100)">$100</button>
                    </div>

                    <div class="change-box" id="changeBoxArea">
                        <span class="font-weight-bold text-uppercase d-block mb-1">Qaytim</span>
                        <h2 class="amount" id="changeAmount">0</h2>
                    </div>
                </div>
                
                <div class="pay-right">
                    <div class="numpad-grid">
                        <button class="numpad-btn" onclick="numPadPress(7)">7</button>
                        <button class="numpad-btn" onclick="numPadPress(8)">8</button>
                        <button class="numpad-btn" onclick="numPadPress(9)">9</button>
                        <button class="numpad-btn" onclick="numPadPress(4)">4</button>
                        <button class="numpad-btn" onclick="numPadPress(5)">5</button>
                        <button class="numpad-btn" onclick="numPadPress(6)">6</button>
                        <button class="numpad-btn" onclick="numPadPress(1)">1</button>
                        <button class="numpad-btn" onclick="numPadPress(2)">2</button>
                        <button class="numpad-btn" onclick="numPadPress(3)">3</button>
                        <button class="numpad-btn btn-clear-num" onclick="numPadClear()">C</button>
                        <button class="numpad-btn" onclick="numPadPress(0)">0</button>
                        <button class="numpad-btn" onclick="numPadPress('000')">000</button>
                    </div>
                    <button class="btn-confirm-pay" onclick="confirmCheckout()">
                        TASDIQLASH <i class="fas fa-check-circle ml-2"></i>
                    </button>
                </div>
                
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="heldCartsModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" style="border-radius: 20px; border: none;">
            <div class="modal-header border-0 pb-0 pt-3 px-4">
                <h5 class="font-weight-bold">Kutilayotgan Savatlar</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body p-4" id="heldCartsList"></div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.1/dist/js/bootstrap.bundle.min.js"></script>

<script>
let cart = []; 
let heldCarts = [];
let subTotal = 0; 
let finalTotal = 0; 
let selectedPaymentMethod = 'naqd';
let numpadValue = "";
let activeCategory = 'all';
let isOptomMode = false;

setInterval(() => { $('#clock').text(new Date().toLocaleTimeString('uz-UZ', {hour: '2-digit', minute:'2-digit'})); }, 1000);
$(document).ready(function(){ $('#searchProduct').focus(); renderCart(); });

function getRate() { return parseFloat($('#exchangeRate').val()) || 12600; }
function toggleFullScreen() {
    if (!document.fullscreenElement) { document.documentElement.requestFullscreen(); } 
    else { if (document.exitFullscreen) { document.exitFullscreen(); } }
}
function playBeep() {
    try {
        let ctx = new (window.AudioContext || window.webkitAudioContext)();
        let osc = ctx.createOscillator(); let gain = ctx.createGain();
        osc.connect(gain); gain.connect(ctx.destination);
        osc.type = 'sine'; osc.frequency.setValueAtTime(900, ctx.currentTime);
        gain.gain.setValueAtTime(0.05, ctx.currentTime);
        osc.start(); osc.stop(ctx.currentTime + 0.08);
    } catch(e) {}
}

function filterCat(catId, element) { 
    $('.cat-btn').removeClass('active'); 
    $(element).addClass('active'); 
    activeCategory = catId;
    let searchValue = $('#searchProduct').val().toLowerCase().trim();

    $('.item-card').each(function() {
        let matchCat = (catId === 'all' || $(this).attr('data-category') == catId);
        let matchName = $(this).attr('data-name').indexOf(searchValue) > -1;
        $(this).toggle(matchCat && matchName);
    });
    $('#searchProduct').focus(); 
}

function toggleOptomMode() {
    isOptomMode = !isOptomMode; 
    let btn = $('#optomBtnBtn');
    if (isOptomMode) {
        btn.removeClass('btn-outline-danger').addClass('btn-danger active');
        btn.html('<i class="fas fa-boxes mr-1"></i> OPTOM: YOQILGAN');
    } else {
        btn.removeClass('btn-danger active').addClass('btn-outline-danger');
        btn.html('<i class="fas fa-boxes mr-1"></i> OPTOM: O\'CHIQ');
    }

    $('.item-card').each(function() {
        let normalPrice = parseFloat($(this).attr('data-price'));
        let optomPrice = parseFloat($(this).attr('data-optom'));
        let displayPrice = isOptomMode ? optomPrice : normalPrice;
        
        $(this).find('.p-price-val').text(displayPrice.toLocaleString('ru-RU').replace(/,/g, ' '));
        if(isOptomMode) { $(this).addClass('optom-mode'); } else { $(this).removeClass('optom-mode'); }
    });
    renderCart(); 
}

function addToCart(id, name, price, optom_price, stock) {
    let item = cart.find(x => x.id === id);
    if (item) {
        if (item.count < stock) { item.count++; playBeep(); } else { alert("Omborda yetarli emas!"); return; }
    } else { cart.push({id, name, price, optom_price, count: 1, stock: stock}); playBeep(); }
    renderCart(); $('#searchProduct').val('').focus();
}

function updateQty(index, change) {
    let item = cart[index];
    if(change > 0 && item.count >= item.stock) { alert("Yetarli emas!"); return; }
    item.count += change;
    if(item.count <= 0) cart.splice(index, 1);
    renderCart();
}

function removeFromCart(index) { cart.splice(index, 1); renderCart(); }

function clearCart() { 
    if(cart.length > 0 && confirm("Savat tozalansinmi?")) { 
        cart = []; 
        $('#discountInput').val(0); 
        if(isOptomMode) toggleOptomMode(); 
        renderCart(); 
    } 
}

function holdCurrentCart() {
    if(cart.length === 0) return alert("Savat bo'sh!");
    let time = new Date().toLocaleTimeString('uz-UZ', {hour: '2-digit', minute:'2-digit'});
    heldCarts.push({ name: "Mijoz " + time, items: [...cart], discount: $('#discountInput').val(), total: finalTotal });
    cart = []; $('#discountInput').val(0); 
    if(isOptomMode) toggleOptomMode(); 
    renderCart(); $('#heldCartsCount').text(heldCarts.length);
}

function showHeldCartsModal() {
    if(heldCarts.length === 0) return alert("Kutilayotgan cheklar yo'q.");
    let html = '';
    heldCarts.forEach((hc, index) => {
        html += `<div class="mb-3 p-3 border rounded"><div class="d-flex justify-content-between align-items-center">
            <div><h6 class="font-weight-bold mb-1 text-dark">${hc.name}</h6><span class="text-muted">${hc.items.length} xil | ${hc.total.toLocaleString()} UZS</span></div>
            <button class="btn btn-success btn-sm font-weight-bold" onclick="restoreCart(${index})">Ochish</button></div></div>`;
    });
    $('#heldCartsList').html(html); $('#heldCartsModal').modal('show');
}

function restoreCart(index) {
    if(cart.length > 0 && !confirm("Ochiq savdo o'chib ketadi. Davom etamizmi?")) return;
    let hc = heldCarts[index]; cart = [...hc.items]; $('#discountInput').val(hc.discount);
    heldCarts.splice(index, 1); $('#heldCartsCount').text(heldCarts.length);
    renderCart(); $('#heldCartsModal').modal('hide');
}

function renderCart() {
    let html = ''; subTotal = 0; 
    if(cart.length === 0) {
        html = `<div class="text-center mt-5 pt-4 text-muted" style="opacity: 0.4;"><i class="fas fa-shopping-basket mb-3" style="font-size: 50px;"></i><h6>Savat bo'sh</h6></div>`;
        $('#discountInput').val(0);
    } else {
        cart.forEach((item, index) => {
            let currentPrice = (isOptomMode && item.optom_price > 0) ? item.optom_price : item.price;
            let sum = currentPrice * item.count; subTotal += sum;
            let itemClass = isOptomMode ? 'optom-item' : '';
            html += `<div class="cart-item ${itemClass}">
                <div class="cart-item-header">
                    <div class="cart-item-title">${item.name} <br><small class="text-muted">(${currentPrice.toLocaleString()} UZS)</small></div>
                    <button class="btn-remove" onclick="removeFromCart(${index})"><i class="fas fa-times-circle"></i></button>
                </div>
                <div class="cart-controls">
                    <div class="qty-control"><button class="qty-btn" onclick="updateQty(${index}, -1)">-</button><input type="text" class="qty-input" value="${item.count}" readonly><button class="qty-btn" onclick="updateQty(${index}, 1)">+</button></div>
                    <div class="item-total">${sum.toLocaleString()}</div>
                </div>
            </div>`;
        });
    }
    $('#cartContent').html(html);
    
    let discountPercent = parseFloat($('#discountInput').val()) || 0;
    if(discountPercent > 100) discountPercent = 100; if(discountPercent < 0) discountPercent = 0;
    let discountAmt = (subTotal * discountPercent) / 100;
    finalTotal = subTotal - discountAmt;
    let usdTotal = finalTotal / getRate();
    
    $('#subTotalStr').text(subTotal.toLocaleString()); $('#discountSumStr').text(discountAmt.toLocaleString() + ' UZS');
    $('#totalSumStr').text(finalTotal.toLocaleString()); $('#totalSumUSD').text('~ $' + usdTotal.toFixed(2));
    let cartDiv = document.getElementById("cartContent"); cartDiv.scrollTop = cartDiv.scrollHeight;
}

$('#searchProduct').on('keyup', function(e) {
    let value = $(this).val().toLowerCase().trim();
    if(e.key === 'Enter' && value !== '') {
        e.preventDefault();
        $.ajax({
            url: "api.php?action=search_product", method: "POST", data: { query: value },
            success: function(response) {
                let res = typeof response === 'object' ? response : JSON.parse(response);
                if(res.length === 1) { 
                    let p = res[0]; addToCart(p.id, p.name, parseFloat(p.price), parseFloat(p.optom_price || 0), parseInt(p.quantity)); 
                } else if(res.length === 0) { alert("Topilmadi: " + value); $('#searchProduct').val('').focus(); }
            }
        }); return;
    }
    $(".item-card").filter(function() {
        let matchName = $(this).attr('data-name').indexOf(value) > -1;
        let matchCat = (activeCategory === 'all' || $(this).attr('data-category') == activeCategory);
        $(this).toggle(matchName && matchCat);
    });
});

function openPaymentModal() {
    if (cart.length === 0) return alert("Savat bo'sh!");
    let usdTotal = finalTotal / getRate();
    $('#modalTotalSum').text(finalTotal.toLocaleString()); $('#modalTotalSumUSD').text('~ $' + usdTotal.toFixed(2));
    setMethod('naqd'); numpadValue = ""; updateNumpadDisplay(); $('#paymentModal').modal('show'); 
}

function setMethod(method) {
    selectedPaymentMethod = method;
    if(method === 'naqd') {
        $('#btnNaqd').addClass('bg-white shadow-sm text-dark').removeClass('text-muted');
        $('#btnKarta').removeClass('bg-white shadow-sm text-dark').addClass('text-muted');
        numpadValue = ""; updateNumpadDisplay(); $('.numpad-btn, .btn-usd').prop('disabled', false);
    } else {
        $('#btnKarta').addClass('bg-white shadow-sm text-dark').removeClass('text-muted');
        $('#btnNaqd').removeClass('bg-white shadow-sm text-dark').addClass('text-muted');
        numpadValue = finalTotal.toString(); updateNumpadDisplay(); $('.numpad-btn, .btn-usd').prop('disabled', true); 
    }
}

function numPadPress(val) { if(selectedPaymentMethod === 'karta') return; numpadValue += val; updateNumpadDisplay(); }
function numPadClear() { numpadValue = ""; updateNumpadDisplay(); }
function numPadAdd(amount) { if(selectedPaymentMethod === 'karta') return; let current = parseInt(numpadValue) || 0; numpadValue = (current + amount).toString(); updateNumpadDisplay(); }
function numPadExact() { if(selectedPaymentMethod === 'karta') return; numpadValue = finalTotal.toString(); updateNumpadDisplay(); }
function numPadAddUSD(usdAmount) { if(selectedPaymentMethod === 'karta') return; let current = parseInt(numpadValue) || 0; let converted = usdAmount * getRate(); numpadValue = (current + converted).toString(); updateNumpadDisplay(); }

function updateNumpadDisplay() {
    let num = parseInt(numpadValue) || 0;
    if(numpadValue === "") $('#receivedCash').val(""); else $('#receivedCash').val(num.toLocaleString());
    let change = num - finalTotal; 
    let box = $('#changeBoxArea');
    if(change >= 0) { 
        $('#changeAmount').text(change.toLocaleString()).removeClass('text-danger').addClass('text-success'); 
        box.removeClass('short').find('.text-uppercase').text('Qaytim').removeClass('text-danger').addClass('text-success');
    } else { 
        $('#changeAmount').text("Yetarli emas").removeClass('text-success').addClass('text-danger'); 
        box.addClass('short').find('.text-uppercase').text('Summa').removeClass('text-success').addClass('text-danger');
    }
}

$(document).keydown(function(e) {
    if (e.key === 'Enter' && $('#paymentModal').is(':visible')) { e.preventDefault(); confirmCheckout(); }
    if (e.key === 'F2') { e.preventDefault(); openPaymentModal(); }
});

function confirmCheckout() {
    let received = parseInt(numpadValue) || 0;
    if (selectedPaymentMethod === 'naqd' && received < finalTotal) { alert("Mijoz bergan summa yetarli emas!"); return; }
    let discountAmt = (subTotal * (parseFloat($('#discountInput').val()) || 0)) / 100;
    
    let checkoutItems = cart.map(item => {
        let finalItemPrice = (isOptomMode && item.optom_price > 0) ? item.optom_price : item.price;
        return { id: item.id, name: item.name, price: finalItemPrice, count: item.count };
    });

    $.ajax({
        url: "api.php?action=complete_sale", method: "POST",
        data: { items: JSON.stringify(checkoutItems), payment_method: selectedPaymentMethod, discount: discountAmt },
        success: function(response) {
            let res = typeof response === 'object' ? response : JSON.parse(response);
            if(res.status === 'success') {
                $('#paymentModal').modal('hide'); 
                if(res.sale_id) { window.open('chek.php?id=' + res.sale_id, 'Chek', 'width=450,height=700'); } 
                else { alert("Savdo tugadi, lekin chek raqami kelmadi!"); }
                setTimeout(function() { 
                    cart = []; 
                    $('#discountInput').val(0); 
                    if(isOptomMode) toggleOptomMode(); 
                    $('#searchProduct').focus(); 
                }, 1000);
            } else alert("Xato: " + res.message);
        }, error: function() { alert("Server xatosi!"); }
    });
}
</script>
</body>
</html>