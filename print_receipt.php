<?php
require_once 'auth.php';
Auth::requireAuth();

$saleId = $_GET['sale_id'] ?? 0;
$paidAmount = floatval($_GET['paid'] ?? 0);
$changeAmount = floatval($_GET['change'] ?? 0);

if ($saleId <= 0) {
    die('Invalid sale ID');
}

$conn = getDBConnection();

// Get sale details
$stmt = $conn->prepare("SELECT 
    s.*,
    c.name as customer_name,
    c.contact_no,
    c.address,
    u.full_name as user_name
FROM sales s
LEFT JOIN customers c ON s.customer_id = c.customer_id
LEFT JOIN users u ON s.user_id = u.user_id
WHERE s.sale_id = ?");

$stmt->bind_param("i", $saleId);
$stmt->execute();
$saleResult = $stmt->get_result();

if ($saleResult->num_rows === 0) {
    die('Sale not found');
}

$sale = $saleResult->fetch_assoc();

// Get sale items with unit information
$stmt = $conn->prepare("SELECT 
    si.*,
    p.product_name,
    p.generic_name,
    p.unit,
    pb.batch_no
FROM sale_items si
JOIN product_batches pb ON si.batch_id = pb.batch_id
JOIN products p ON pb.product_id = p.product_id
WHERE si.sale_id = ?
ORDER BY p.product_name");

$stmt->bind_param("i", $saleId);
$stmt->execute();
$items = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt - #<?php echo str_pad($saleId, 5, '0', STR_PAD_LEFT); ?></title>
    <link rel="shortcut icon" href="assets/images/logof1.png">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            max-width: 80mm;
            margin: 0 auto !important;
            /* Force horizontal center with important */
            padding: 10mm;
            background: #fff;
            font-size: 12px;
            display: block;
            /* Ensure block display */
            width: 80mm;
        }

        .receipt {
            width: 100%;
            margin: 0 auto !important;
            /* Force receipt centering */
        }

        /* Add this wrapper to ensure centering */
        .receipt-wrapper {
            width: 100%;
            display: flex;
            justify-content: center;
        }

        /* Rest of your existing CSS remains exactly the same */
        .header {
            text-align: center;
            border-bottom: 2px dashed #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }

        .header h1 {
            font-size: 18px;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .header p {
            font-size: 11px;
            margin: 3px 0;
        }

        .info-section {
            margin-bottom: 15px;
            font-size: 12px;
            line-height: 1.6;
        }

        .info-row {
            display: flex;
            justify-content: space-between;
            margin: 3px 0;
        }

        .info-label {
            font-weight: bold;
        }

        .divider {
            border-top: 1px dashed #000;
            margin: 10px 0;
        }

        .divider-double {
            border-top: 2px solid #000;
            margin: 10px 0;
        }

        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            font-size: 11px;
        }

        .items-table th {
            border-bottom: 1px solid #000;
            padding: 5px 2px;
            text-align: left;
            font-weight: bold;
        }

        .items-table td {
            padding: 5px 2px;
            vertical-align: top;
        }

        .item-row {
            border-bottom: 1px dotted #ddd;
        }

        .item-name {
            font-weight: bold;
        }

        .item-details {
            font-size: 10px;
            color: #666;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .totals-section {
            margin-top: 10px;
            font-size: 12px;
        }

        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
        }

        .total-row.grand-total {
            font-size: 14px;
            font-weight: bold;
            border-top: 2px solid #000;
            padding-top: 8px;
            margin-top: 5px;
        }

        .payment-section {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px dashed #000;
        }

        .footer {
            text-align: center;
            margin-top: 20px;
            border-top: 2px dashed #000;
            padding-top: 10px;
            font-size: 11px;
        }

        .footer p {
            margin: 5px 0;
        }

        .thank-you {
            font-weight: bold;
            font-size: 13px;
            margin: 10px 0;
        }

        .barcode {
            text-align: center;
            margin: 15px 0;
            font-size: 12px;
            font-weight: bold;
            letter-spacing: 3px;
        }

        .print-button {
            text-align: center;
            margin: 20px 0;
        }

        .print-button button {
            padding: 10px 20px;
            margin: 0 5px;
            font-size: 14px;
            cursor: pointer;
            border: 1px solid #333;
            background: #fff;
            border-radius: 3px;
        }

        .print-button button:hover {
            background: #f0f0f0;
        }

        @media print {
            body {
                margin: 0;
                padding: 5mm;
            }

            .print-button {
                display: none;
            }

            @page {
                size: 80mm auto;
                margin: 0;
            }
        }
    </style>
</head>

<body>
    <div class="print-button">
        <button onclick="window.print()">🖨️ Print</button>
        <button onclick="window.close()">✖️ Close</button>
    </div>

    <div class="receipt-wrapper">
        <div class="receipt">
            <!-- Header -->
            <div class="header">
                <h1>Erundeniya Ayurveda Hospital</h1>
                <p>A/55 Wedagedara, Erundeniya,</p>
                <p>Amithirigala, North.</p>
                <p>Tel: +94 71 291 9408</p>
                <p>Email: info@erundeniyaayurveda.lk</p>
            </div>

            <!-- Receipt Info -->
            <div class="info-section">
                <div class="info-row">
                    <span class="info-label">Receipt No:</span>
                    <span>#<?php echo str_pad($saleId, 5, '0', STR_PAD_LEFT); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date:</span>
                    <span><?php echo date('d M Y, h:i A', strtotime($sale['sale_date'])); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Cashier:</span>
                    <span><?php echo htmlspecialchars($sale['user_name']); ?></span>
                </div>
                <div class="info-row">
                    <span class="info-label">Payment:</span>
                    <span><?php echo strtoupper($sale['payment_type']); ?></span>
                </div>
            </div>

            <?php if ($sale['customer_name'] || !empty($_GET['customer_name'])): ?>
                <div class="divider"></div>
                <div class="info-section">
                    <div class="info-row">
                        <span class="info-label">Customer:</span>
                        <span><?php echo htmlspecialchars($sale['customer_name'] ?? 'Walk-in Customer'); ?></span>
                    </div>
                    <?php if ($sale['contact_no']): ?>
                        <div class="info-row">
                            <span class="info-label">Mobile:</span>
                            <span><?php echo htmlspecialchars($sale['contact_no']); ?></span>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <div class="divider-double"></div>

            <!-- Items Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th style="width: 45%;">Item</th>
                        <th style="width: 15%;" class="text-center">Qty</th>
                        <th style="width: 18%;" class="text-right">Price</th>
                        <th style="width: 22%;" class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $itemCount = 0;
                    while ($item = $items->fetch_assoc()):
                        $itemCount++;
                    ?>
                        <tr class="item-row">
                            <td>
                                <div class="item-name"><?php echo htmlspecialchars($item['product_name']); ?></div>
                                <?php if ($item['generic_name']): ?>
                                    <div class="item-details"><?php echo htmlspecialchars($item['generic_name']); ?></div>
                                <?php endif; ?>
                            </td>
                            <td class="text-center">
                                <?php
                                // Display quantity with unit
                                $qty = floatval($item['quantity']);
                                echo $qty;
                                ?>
                            </td>
                            <td class="text-right"><?php echo number_format($item['unit_price'], 2); ?></td>
                            <td class="text-right"><?php echo number_format($item['total_price'], 2); ?></td>
                        </tr>
                        
                    <?php endwhile; ?>
                </tbody>
            </table>

            <!-- Totals Section -->
            <div class="totals-section">
                <div class="total-row">
                    <span>Subtotal:</span>
                    <span>Rs. <?php echo number_format($sale['total_amount'], 2); ?></span>
                </div>
                <?php if ($sale['discount'] > 0): ?>
                    <div class="total-row">
                        <span>Discount:</span>
                        <span>- Rs. <?php echo number_format($sale['discount'], 2); ?></span>
                    </div>
                <?php endif; ?>
                <div class="total-row grand-total">
                    <span>TOTAL:</span>
                    <span>Rs. <?php echo number_format($sale['net_amount'], 2); ?></span>
                </div>
            </div>

            <!-- Payment Details (for Cash only) -->
            <?php if ($sale['payment_type'] === 'cash' && $paidAmount > 0): ?>
                <div class="payment-section">
                    <div class="total-row">
                        <span class="info-label">Paid:</span>
                        <span>Rs. <?php echo number_format($paidAmount, 2); ?></span>
                    </div>
                    <div class="total-row">
                        <span class="info-label">Change:</span>
                        <span>Rs. <?php echo number_format($changeAmount, 2); ?></span>
                    </div>
                </div>
            <?php endif; ?>

            <div class="divider"></div>

            <!-- Additional Info -->
            <div class="info-section" style="font-size: 10px;">
                <div class="info-row">
                    <span>Total Items:</span>
                    <span><?php echo $itemCount; ?></span>
                </div>
                <?php if ($sale['payment_type'] === 'credit'): ?>
                    <div style="margin-top: 5px; font-style: italic; color: #666;">
                        * Credit Payment - Please settle within 30 days
                    </div>
                <?php endif; ?>
            </div>

            <div class="divider"></div>

            <!-- Barcode -->
            <div class="barcode">
                *<?php echo str_pad($saleId, 5, '0', STR_PAD_LEFT); ?>*
            </div>

            <!-- Footer -->
            <div class="footer">
                <p class="thank-you">Thank You for Your Visit!</p>
                <p>Please visit us again</p>
                <p style="margin-top: 10px; font-size: 10px;">
                    For inquiries: info@erundeniyaayurveda.lk<br>
                    Tel: +94 71 291 9408
                </p>
                <div class="divider" style="margin-top: 10px;"></div>
                <p style="font-size: 9px; margin-top: 10px;">
                    © <?php echo date('Y'); ?>&nbsp;Erundeniya Ayurveda Hospital<br>
                    All rights reserved<br>
                    All payments made to Erundeniya Ayurveda Hospital are non-refundable.
                </p>
                <!-- <br/> -->
                <p style="font-size: 10px; margin-top: 10px;">
                    Powered By <strong>www.evotech.lk</strong>
                </p>
            </div>
        </div>
    </div>

    <script>
        // Auto-print on load (optional)
        // window.onload = function() {
        //     window.print();
        // }

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                e.preventDefault();
                window.print();
            }
            if (e.key === 'Escape') {
                window.close();
            }
        });
    </script>
</body>

</html>