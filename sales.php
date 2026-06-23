<?php
require_once 'includes/auth.php';
require_once 'config/db.php';
require_once 'includes/functions.php';

$customers = $pdo->query("SELECT id, first_name, last_name, phone FROM customers ORDER BY first_name")->fetchAll();
$invoiceNo = generateInvoiceNo();
include 'includes/header.php';
?>
<h2>Point of Sale</h2>
<div class="row">
    <div class="col-md-7">
        <div class="card mb-3">
            <div class="card-body">
                <div class="input-group">
                    <input type="text" id="productSearch" class="form-control" placeholder="Search by SKU or Name...">
                    <button class="btn btn-outline-secondary" id="searchBtn" type="button"><i class="bi bi-search"></i></button>
                </div>
                <div id="searchResults" class="mt-2" style="max-height:200px; overflow-y:auto;"></div>
            </div>
        </div>

        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span>Current Sale</span>
                <span>Invoice: <strong id="invoiceDisplay"><?= htmlspecialchars($invoiceNo) ?></strong></span>
            </div>
            <div class="card-body">
                <table class="table table-bordered" id="cartTable">
                    <thead><tr><th>Product</th><th>Qty</th><th>Price</th><th>Total</th><th></th></tr></thead>
                    <tbody id="cartBody"></tbody>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Subtotal</strong></td>
                            <td id="subtotal">0.00</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Discount</strong></td>
                            <td>
                                <input type="number" id="discountInput" class="form-control form-control-sm" value="0" step="0.01" style="width:140px;">
                            </td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Tax (0%)</strong></td>
                            <td id="taxDisplay">0.00</td>
                            <td></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Grand Total</strong></td>
                            <td id="grandTotal"><strong>0.00</strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>

                <div class="row mt-3">
                    <div class="col-md-6">
                        <select id="customerSelect" class="form-select">
                            <option value="">Walk-in Customer</option>
                            <?php foreach ($customers as $c): ?>
                                <option value="<?= (int)$c['id'] ?>">
                                    <?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name'] . ' (' . ($c['phone'] ?? '') . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-6 text-end">
                        <button class="btn btn-success" id="completeSaleBtn" type="button"><i class="bi bi-check-circle"></i> Complete Sale</button>
                        <button class="btn btn-danger" id="clearCartBtn" type="button"><i class="bi bi-trash"></i> Clear</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="card">
            <div class="card-header">Receipt Preview</div>
            <div class="card-body receipt" id="receiptPreview" style="background:#fff; color:#000; min-height:300px;">
                <div class="text-center">
                    <img src="assets/DELIGOS%20LOGO.png" class="receipt-logo" alt="Deligos Company">
                    <h5>DELIGOS COMPANY</h5>
                    <p>
                        Invoice: <span id="receiptInvoice"><?= htmlspecialchars($invoiceNo) ?></span><br>
                        <span id="receiptDate"><?= htmlspecialchars(date('Y-m-d H:i')) ?></span><br>
                        Cashier: <span id="receiptCashier"><?= htmlspecialchars($_SESSION['full_name'] ?? '') ?></span>
                    </p>
                </div>
                <hr>
                <table class="table table-sm">
                    <thead><tr><th>Item</th><th>Qty</th><th>Price</th></tr></thead>
                    <tbody id="receiptItems"></tbody>
                </table>
                <hr>
                <p>Discount: KSh <span id="receiptDiscount">0.00</span></p>
                <p><strong>Total: KSh <span id="receiptTotal">0.00</span></strong></p>
                <p><small>Thank you!</small></p>
            </div>
            <div class="card-footer">
                <button class="btn btn-secondary btn-sm" id="printReceiptBtn" type="button"><i class="bi bi-printer"></i> Print Receipt</button>
                <button class="btn btn-success btn-sm" id="downloadReceiptBtn" type="button"><i class="bi bi-file-earmark-pdf"></i> Download PDF</button>
            </div>
        </div>
    </div>
</div>

<script>
let cart = [];
let invoiceNo = document.getElementById('invoiceDisplay').textContent;
let lastReceiptData = null;

function generateInvoiceNo() {
    let now = new Date();
    let date = now.getFullYear().toString() + String(now.getMonth() + 1).padStart(2, '0') + String(now.getDate()).padStart(2, '0');
    return 'INV-' + date + '-' + Math.floor(Math.random() * 9000 + 1000);
}

function fmt(n){ return (Number(n) || 0).toFixed(2); }

function receiptHasItems() {
    return cart.length > 0 || (lastReceiptData && lastReceiptData.items.length > 0);
}

function getReceiptData() {
    if (cart.length === 0 && lastReceiptData) {
        return lastReceiptData;
    }

    let discount = parseFloat($('#receiptDiscount').text()) || 0;
    let total = parseFloat($('#receiptTotal').text()) || 0;

    return {
        invoice: $('#receiptInvoice').text().trim(),
        date: $('#receiptDate').text().trim(),
        cashier: $('#receiptCashier').text().trim(),
        discount,
        total,
        items: cart.map(item => ({
            name: item.name,
            qty: item.qty,
            total: item.price * item.qty
        }))
    };
}

function renderReceiptData(data) {
    $('#receiptInvoice').text(data.invoice);
    $('#receiptDate').text(data.date);
    $('#receiptCashier').text(data.cashier);
    $('#receiptDiscount').text(fmt(data.discount));
    $('#receiptTotal').text(fmt(data.total));

    let receiptItems = $('#receiptItems');
    receiptItems.empty();
    data.items.forEach(item => {
        receiptItems.append(`<tr><td>${item.name}</td><td>${item.qty}</td><td>KSh ${fmt(item.total)}</td></tr>`);
    });
}

function escapePdfText(text) {
    return String(text)
        .replace(/[^\x20-\x7E]/g, '')
        .replace(/\\/g, '\\\\')
        .replace(/\(/g, '\\(')
        .replace(/\)/g, '\\)');
}

function pdfText(x, y, text, size = 10, color = '0 0 0', font = 'F1') {
    return `${color} rg\nBT /${font} ${size} Tf ${x.toFixed(2)} ${y.toFixed(2)} Td (${escapePdfText(text)}) Tj ET\n`;
}

function pdfCenteredText(centerX, y, text, size = 10, color = '0 0 0', font = 'F1') {
    const approximateWidth = String(text).length * size * 0.6;
    return pdfText(centerX - (approximateWidth / 2), y, text, size, color, font);
}

function pdfLine(x1, y1, x2, y2) {
    return `0.75 0.75 0.75 RG ${x1.toFixed(2)} ${y1.toFixed(2)} m ${x2.toFixed(2)} ${y2.toFixed(2)} l S\n`;
}

function pdfBox(x, y, w, h, fill) {
    return `${fill} rg ${x.toFixed(2)} ${y.toFixed(2)} ${w.toFixed(2)} ${h.toFixed(2)} re f\n`;
}

function pdfImage(name, x, y, w, h) {
    return `q ${w.toFixed(2)} 0 0 ${h.toFixed(2)} ${x.toFixed(2)} ${y.toFixed(2)} cm /${name} Do Q\n`;
}

function bytesToHex(bytes) {
    let hex = '';
    bytes.forEach(byte => {
        hex += byte.toString(16).padStart(2, '0');
    });
    return hex;
}

function loadReceiptLogoForPdf() {
    return new Promise((resolve, reject) => {
        const img = new Image();
        img.onload = function() {
            const canvas = document.createElement('canvas');
            canvas.width = img.naturalWidth;
            canvas.height = img.naturalHeight;
            const ctx = canvas.getContext('2d');
            ctx.fillStyle = '#ffffff';
            ctx.fillRect(0, 0, canvas.width, canvas.height);
            ctx.drawImage(img, 0, 0);

            const base64 = canvas.toDataURL('image/jpeg', 0.95).split(',')[1];
            const binary = atob(base64);
            const bytes = new Uint8Array(binary.length);
            for (let i = 0; i < binary.length; i++) {
                bytes[i] = binary.charCodeAt(i);
            }

            resolve({
                width: canvas.width,
                height: canvas.height,
                hex: bytesToHex(bytes)
            });
        };
        img.onerror = reject;
        img.src = 'assets/DELIGOS%20LOGO.png';
    });
}

async function makeReceiptPdf(data) {
    const logo = await loadReceiptLogoForPdf();
    const mm = 72 / 25.4;
    const pageW = 210 * mm;
    const pageH = 297 * mm;
    const receiptSize = 80 * mm;
    const receiptX = 0;
    const receiptTop = pageH;
    const receiptBottom = pageH - receiptSize;
    const pad = 10;
    const centerX = receiptX + (receiptSize / 2);
    const textLeft = receiptX + pad;
    const textRight = receiptX + receiptSize - pad;
    let y = receiptTop - 12;
    let content = '';

    content += pdfBox(receiptX, receiptBottom, receiptSize, receiptSize, '1 1 1');

    const logoW = 54;
    const logoH = logoW * (logo.height / logo.width);
    content += pdfImage('Logo', centerX - (logoW / 2), y - logoH, logoW, logoH);
    y -= logoH + 8;
    content += pdfCenteredText(centerX, y, 'DELIGOS COMPANY', 10, '0 0 0', 'F2');
    y -= 12;
    content += pdfCenteredText(centerX, y, `Invoice: ${data.invoice}`, 7);
    y -= 9;
    content += pdfCenteredText(centerX, y, data.date, 7);
    y -= 9;
    content += pdfCenteredText(centerX, y, `Cashier: ${data.cashier}`, 7);
    y -= 10;
    content += pdfLine(textLeft, y, textRight, y);
    y -= 10;

    content += pdfText(textLeft, y, 'Item', 7, '0 0 0', 'F2');
    content += pdfText(receiptX + 142, y, 'Qty', 7, '0 0 0', 'F2');
    content += pdfText(receiptX + 174, y, 'Price', 7, '0 0 0', 'F2');
    y -= 8;
    content += pdfLine(textLeft, y, textRight, y);
    y -= 9;

    const footerHeight = 42;
    const rowHeight = 9;
    const maxRows = Math.max(0, Math.floor((y - (receiptBottom + footerHeight)) / rowHeight));
    const visibleItems = data.items.slice(0, maxRows);
    visibleItems.forEach(item => {
        let name = item.name.length > 22 ? item.name.substring(0, 19) + '...' : item.name;
        content += pdfText(textLeft, y, name, 6);
        content += pdfText(receiptX + 146, y, item.qty, 6);
        content += pdfText(receiptX + 174, y, `KSh ${fmt(item.total)}`, 6);
        y -= rowHeight;
    });

    if (visibleItems.length < data.items.length) {
        content += pdfText(textLeft, y, `+ ${data.items.length - visibleItems.length} more item(s)`, 6);
    }

    y = receiptBottom + 34;
    content += pdfLine(textLeft, y, textRight, y);
    y -= 10;
    content += pdfText(textLeft, y, `Discount: KSh ${fmt(data.discount)}`, 7);
    y -= 10;
    content += pdfText(textLeft, y, `Total: KSh ${fmt(data.total)}`, 8, '0 0 0', 'F2');
    y -= 14;
    content += pdfText(textLeft, y, 'Thank you!', 7);

    const objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [6 0 R] /Count 1 >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Courier >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold >>',
        `<< /Type /XObject /Subtype /Image /Width ${logo.width} /Height ${logo.height} /ColorSpace /DeviceRGB /BitsPerComponent 8 /Filter [/ASCIIHexDecode /DCTDecode] /Length ${logo.hex.length + 1} >>\nstream\n${logo.hex}>\nendstream`,
        `<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ${pageW} ${pageH}] /Resources << /Font << /F1 3 0 R /F2 4 0 R >> /XObject << /Logo 5 0 R >> >> /Contents 7 0 R >>`,
        `<< /Length ${content.length} >>\nstream\n${content}endstream`
    ];

    let pdf = '%PDF-1.4\n';
    const offsets = [0];
    objects.forEach((object, index) => {
        offsets.push(pdf.length);
        pdf += `${index + 1} 0 obj\n${object}\nendobj\n`;
    });

    const xrefOffset = pdf.length;
    pdf += `xref\n0 ${objects.length + 1}\n`;
    pdf += '0000000000 65535 f \n';
    for (let i = 1; i <= objects.length; i++) {
        pdf += `${String(offsets[i]).padStart(10, '0')} 00000 n \n`;
    }
    pdf += `trailer\n<< /Size ${objects.length + 1} /Root 1 0 R >>\nstartxref\n${xrefOffset}\n%%EOF`;

    return new Blob([pdf], { type: 'application/pdf' });
}

async function printReceiptOnly() {
    if (!receiptHasItems()) {
        alert('Receipt is empty.');
        return;
    }

    const data = getReceiptData();
    const blob = await makeReceiptPdf(data);
    const printUrl = URL.createObjectURL(blob);
    const existingFrame = document.getElementById('receiptPrintFrame');
    if (existingFrame) {
        existingFrame.remove();
    }

    const frame = document.createElement('iframe');
    frame.id = 'receiptPrintFrame';
    frame.style.position = 'fixed';
    frame.style.right = '0';
    frame.style.bottom = '0';
    frame.style.width = '0';
    frame.style.height = '0';
    frame.style.border = '0';
    frame.src = printUrl;
    frame.onload = function() {
        frame.contentWindow.focus();
        frame.contentWindow.print();
        setTimeout(function() {
            URL.revokeObjectURL(printUrl);
        }, 30000);
    };
    document.body.appendChild(frame);
}

async function downloadReceiptPdf() {
    if (!receiptHasItems()) {
        alert('Receipt is empty.');
        return;
    }

    const data = getReceiptData();
    const blob = await makeReceiptPdf(data);
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = `${data.invoice || 'receipt'}.pdf`;
    document.body.appendChild(link);
    link.click();
    URL.revokeObjectURL(link.href);
    link.remove();
}

function updateTotals() {
    let subtotal = 0;
    cart.forEach(i => subtotal += i.price * i.qty);

    let discount = parseFloat($('#discountInput').val()) || 0;
    let tax = 0;
    let grand = subtotal - discount + tax;

    $('#subtotal').text(fmt(subtotal));
    $('#taxDisplay').text(fmt(tax));
    $('#grandTotal').text(fmt(grand));
    $('#receiptTotal').text(fmt(grand));
    $('#receiptDiscount').text(fmt(discount));
}

function renderCart() {
    let tbody = $('#cartBody');
    let subtotal = 0;
    tbody.empty();

    if (cart.length > 0) {
        lastReceiptData = null;
        $('#receiptInvoice').text(invoiceNo);
        $('#receiptDate').text(new Date().toLocaleString());
    }

    cart.forEach((item, index) => {
        let total = item.price * item.qty;
        subtotal += total;
        tbody.append(`
            <tr>
                <td>${item.name}</td>
                <td>
                    <button class="btn btn-sm btn-outline-secondary qty-minus" data-index="${index}" type="button">-</button>
                    ${item.qty}
                    <button class="btn btn-sm btn-outline-secondary qty-plus" data-index="${index}" type="button">+</button>
                </td>
                <td>KSh ${item.price.toFixed(2)}</td>
                <td>KSh ${total.toFixed(2)}</td>
                <td><button class="btn btn-sm btn-danger remove-item" data-index="${index}" type="button"><i class="bi bi-x"></i></button></td>
            </tr>
        `);
    });

    // Receipt
    let receiptItems = $('#receiptItems');
    receiptItems.empty();
    cart.forEach(item => {
        receiptItems.append(`<tr><td>${item.name}</td><td>${item.qty}</td><td>KSh ${((item.price * item.qty).toFixed(2))}</td></tr>`);
    });

    $('#subtotal').text(fmt(subtotal));
    updateTotals();
}

// Search products
function searchProducts(q) {
    if (!q || q.length < 2) { $('#searchResults').html(''); return; }
    $.ajax({
        url: 'ajax/search_products.php',
        method: 'GET',
        data: { q },
        dataType: 'json',
        success: function(data) {
            let html = '';
            data.forEach(p => {
                html += `
                    <div class="d-flex justify-content-between border-bottom p-2 align-items-center">
                        <span><strong>${p.name}</strong> (${p.sku}) - KSh ${parseFloat(p.price).toFixed(2)} | Stock: ${p.stock_qty}</span>
                        <button class="btn btn-sm btn-primary add-to-cart" type="button" data-id="${p.id}" data-name="${p.name}" data-price="${p.price}" data-stock="${p.stock_qty}">+ Add</button>
                    </div>
                `;
            });
            $('#searchResults').html(html || '<div class="text-muted">No products found.</div>');
        }
    });
}

$('#productSearch').on('keyup', function(){ searchProducts($(this).val()); });
$('#searchBtn').on('click', function(){ searchProducts($('#productSearch').val()); });

// Add to cart
$(document).on('click', '.add-to-cart', function() {
    let id = $(this).data('id');
    let name = $(this).data('name');
    let price = parseFloat($(this).data('price'));
    let stock = parseInt($(this).data('stock'));

    let existing = cart.find(item => item.id === id);
    if (existing) {
        if (existing.qty < stock) existing.qty++;
        else alert('Not enough stock!');
    } else {
        if (stock > 0) cart.push({ id, name, price, qty: 1 });
        else alert('Out of stock!');
    }
    renderCart();
});

$(document).on('click', '.qty-plus', function() {
    let idx = $(this).data('index');
    cart[idx].qty++;
    renderCart();
});

$(document).on('click', '.qty-minus', function() {
    let idx = $(this).data('index');
    if (cart[idx].qty > 1) cart[idx].qty--;
    else cart.splice(idx, 1);
    renderCart();
});

$(document).on('click', '.remove-item', function() {
    let idx = $(this).data('index');
    cart.splice(idx, 1);
    renderCart();
});

$('#clearCartBtn').on('click', function(){
    if (confirm('Clear cart?')) {
        cart = [];
        lastReceiptData = null;
        $('#discountInput').val(0);
        renderCart();
    }
});

$('#discountInput').on('input', updateTotals);
$('#printReceiptBtn').on('click', printReceiptOnly);
$('#downloadReceiptBtn').on('click', downloadReceiptPdf);

// Complete Sale
$('#completeSaleBtn').on('click', function() {
    if (cart.length === 0) { alert('Cart is empty!'); return; }

    let customer_id = $('#customerSelect').val() || null;
    let discount = parseFloat($('#discountInput').val()) || 0;
    let grand_total = parseFloat($('#grandTotal').text()) || 0;

    let saleData = {
        invoice_no: invoiceNo,
        customer_id: customer_id,
        discount: discount,
        grand_total: grand_total,
        items: cart.map(item => ({ product_id: item.id, qty: item.qty, unit_price: item.price }))
    };

    $.ajax({
        url: 'ajax/complete_sale.php',
        method: 'POST',
        contentType: 'application/json',
        data: JSON.stringify(saleData),
        dataType: 'json',
        success: function(res) {
            if (res.success) {
                alert('Sale completed! Invoice: ' + res.invoice_no);
                lastReceiptData = getReceiptData();
                cart = [];
                invoiceNo = generateInvoiceNo();
                $('#invoiceDisplay').text(invoiceNo);
                $('#receiptInvoice').text(invoiceNo);
                renderCart();
                $('#discountInput').val(0);
                $('#customerSelect').val('');
                updateTotals();
                renderReceiptData(lastReceiptData);
            } else {
                alert('Error: ' + (res.message || 'Unknown error'));
            }
        },
        error: function(){ alert('Server error.'); }
    });
});

// Initial render
renderCart();
</script>
<?php include 'includes/footer.php'; ?>

