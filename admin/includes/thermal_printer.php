<?php

/**
 * Thermal Receipt Printer Functions
 * Professional 58mm/80mm thermal receipt generator
 * Compatible with ESC/POS printers
 */

class ThermalReceiptPrinter
{
    // Paper width settings (characters per line)
    const WIDTH_58MM = 32; // 58mm paper = ~32 characters
    const WIDTH_80MM = 48; // 80mm paper = ~48 characters

    private $paperWidth;
    private $companyInfo;

    public function __construct($paperWidth = self::WIDTH_58MM)
    {
        $this->paperWidth = $paperWidth;
        $this->companyInfo = [
            'name_sinhala' => 'එ. ඩබ්. ඩි. ඒරන්දෙණිය',
            'name_english' => 'E. W. D. Erundeniya',
            'tagline' => 'හෙළ ඖෂධාල',
            'tagline_english' => 'Quality Ayurvedic Products & Medicines',
            'address' => 'A/55 ඉරිදේව, පදුක්කදෙනිය, අංගිරිය.',
            'tel' => 'Tel: +94 77 936 6308',
            'email' => 'Email: info@erundeniyaayu.lk'
        ];
    }

    /**
     * Generate Medical Bill Receipt
     */
    public function generateMedicalBill($billData)
    {
        $receipt = $this->getHeader();
        $receipt .= $this->addDivider();

        // Receipt details
        $receipt .= $this->addLine("Receipt No:", str_pad($billData['bill_number'], 15, ' ', STR_PAD_LEFT), true);
        $receipt .= $this->addLine("Date:", date('d M Y, h:i A', strtotime($billData['created_at'])));
        $receipt .= $this->addLine("Cashier:", $billData['created_by_name'] ?? 'Evon Technologies');

        if (!empty($billData['payment_status'])) {
            $receipt .= $this->addLine("Payment:", $billData['payment_status']);
        }

        $receipt .= $this->addDivider();

        // Patient Info
        if (!empty($billData['patient_name'])) {
            $receipt .= $this->addLine("Patient:", $billData['patient_name']);
            $receipt .= $this->addLine("Mobile:", $billData['patient_mobile']);
        }

        if (!empty($billData['appointment_number'])) {
            $receipt .= $this->addLine("Apt. No:", $billData['appointment_number']);
        }

        $receipt .= $this->addDivider();

        // Items header
        $receipt .= $this->addItemHeader();

        // Bill items
        $subtotal = 0;

        // Doctor Fee
        if (isset($billData['doctor_fee']) && $billData['doctor_fee'] > 0) {
            $qty = 1;
            $price = floatval($billData['doctor_fee']);
            $total = $price * $qty;
            $subtotal += $total;
            $receipt .= $this->addItem("මාධෝ සිංහ", $qty, $price, $total);
        }

        // Medicine Cost
        if (isset($billData['medicine_cost']) && $billData['medicine_cost'] > 0) {
            $qty = 1;
            $price = floatval($billData['medicine_cost']);
            $total = $price * $qty;
            $subtotal += $total;
            $receipt .= $this->addItem("බෙහෙත්", $qty, $price, $total);
        }

        // Other Charges
        if (isset($billData['other_charges']) && $billData['other_charges'] > 0) {
            $qty = 1;
            $price = floatval($billData['other_charges']);
            $total = $price * $qty;
            $subtotal += $total;
            $receipt .= $this->addItem("වෙනත් ගාස්තු", $qty, $price, $total);
        }

        $receipt .= $this->addDivider();

        // Totals
        $receipt .= $this->addTotal("Subtotal:", $subtotal);

        if (isset($billData['discount_amount']) && $billData['discount_amount'] > 0) {
            $receipt .= $this->addTotal("Discount:", -floatval($billData['discount_amount']));
        }

        $receipt .= $this->addDivider('=');

        $finalTotal = $subtotal - (floatval($billData['discount_amount'] ?? 0));
        $receipt .= $this->addTotal("TOTAL:", $finalTotal, true);

        $receipt .= $this->addDivider('=');

        // Payment details
        if (isset($billData['paid_amount'])) {
            $receipt .= $this->addTotal("Paid:", floatval($billData['paid_amount']));
            $change = floatval($billData['paid_amount']) - $finalTotal;
            if ($change > 0) {
                $receipt .= $this->addTotal("Change:", $change);
            }
        }

        $receipt .= $this->addDivider();

        // Total items count
        $totalItems = 0;
        if (isset($billData['doctor_fee']) && $billData['doctor_fee'] > 0) $totalItems++;
        if (isset($billData['medicine_cost']) && $billData['medicine_cost'] > 0) $totalItems++;
        if (isset($billData['other_charges']) && $billData['other_charges'] > 0) $totalItems++;

        $receipt .= $this->addLine("Total Items:", str_pad($totalItems, 15, ' ', STR_PAD_LEFT), true);

        $receipt .= $this->addDivider();
        $receipt .= $this->getFooter();

        return $receipt;
    }

    /**
     * Generate OPD Treatment Bill
     */
    public function generateOPDBill($billData)
    {
        $receipt = $this->getHeader();
        $receipt .= $this->addDivider();

        // Receipt details
        $receipt .= $this->addLine("Bill No:", str_pad($billData['bill_number'], 15, ' ', STR_PAD_LEFT), true);
        $receipt .= $this->addLine("Date:", date('d M Y, h:i A', strtotime($billData['created_at'])));
        $receipt .= $this->addLine("Cashier:", $billData['created_by_name'] ?? 'Evon Technologies');

        if (!empty($billData['payment_status'])) {
            $receipt .= $this->addLine("Payment:", $billData['payment_status']);
        }

        $receipt .= $this->addDivider();

        // Patient Info
        $receipt .= $this->addLine("Patient:", $billData['patient_name']);
        $receipt .= $this->addLine("Mobile:", $billData['patient_mobile']);

        $receipt .= $this->addDivider();

        // Items header
        $receipt .= $this->addItemHeader();

        // Treatment items
        $subtotal = 0;
        $totalItems = 0;

        if (isset($billData['treatments_data'])) {
            $treatments = is_string($billData['treatments_data'])
                ? json_decode($billData['treatments_data'], true)
                : $billData['treatments_data'];

            foreach ($treatments as $treatment) {
                $qty = intval($treatment['quantity'] ?? 1);
                $price = floatval($treatment['price']);
                $total = $price * $qty;
                $subtotal += $total;
                $totalItems++;

                $receipt .= $this->addItem($treatment['name'], $qty, $price, $total);
            }
        }

        $receipt .= $this->addDivider();

        // Totals
        $receipt .= $this->addTotal("Subtotal:", $subtotal);

        if (isset($billData['discount_amount']) && $billData['discount_amount'] > 0) {
            $receipt .= $this->addTotal("Discount:", -floatval($billData['discount_amount']));
        }

        $receipt .= $this->addDivider('=');

        $finalTotal = floatval($billData['final_amount'] ?? ($subtotal - floatval($billData['discount_amount'] ?? 0)));
        $receipt .= $this->addTotal("TOTAL:", $finalTotal, true);

        $receipt .= $this->addDivider('=');

        // Payment details
        if (isset($billData['paid_amount'])) {
            $receipt .= $this->addTotal("Paid:", floatval($billData['paid_amount']));
            $change = floatval($billData['paid_amount']) - $finalTotal;
            if ($change > 0) {
                $receipt .= $this->addTotal("Change:", $change);
            }
        }

        $receipt .= $this->addDivider();

        // Total items count
        $receipt .= $this->addLine("Total Items:", str_pad($totalItems, 15, ' ', STR_PAD_LEFT), true);

        $receipt .= $this->addDivider();
        $receipt .= $this->getFooter();

        return $receipt;
    }

    /**
     * Generate Prescription Receipt
     */
    public function generatePrescription($prescriptionData)
    {
        $receipt = $this->getHeader();
        $receipt .= $this->addDivider();

        // Receipt details
        $presNo = 'PRES' . str_pad($prescriptionData['id'], 3, '0', STR_PAD_LEFT);
        $receipt .= $this->addLine("Prescription:", str_pad($presNo, 12, ' ', STR_PAD_LEFT), true);
        $receipt .= $this->addLine("Date:", date('d M Y, h:i A', strtotime($prescriptionData['created_at'])));

        $receipt .= $this->addDivider();

        // Patient Info
        $receipt .= $this->addLine("Patient:", $prescriptionData['patient_name']);
        $receipt .= $this->addLine("Mobile:", $prescriptionData['patient_mobile']);

        if (!empty($prescriptionData['appointment_number'])) {
            $receipt .= $this->addLine("Apt. No:", $prescriptionData['appointment_number']);
        }

        $receipt .= $this->addDivider();

        // Prescription text
        $receipt .= $this->centerText("PRESCRIPTION");
        $receipt .= "\n";

        $prescriptionLines = explode("\n", $prescriptionData['prescription_text']);
        foreach ($prescriptionLines as $line) {
            $receipt .= $this->wrapText($line);
        }

        $receipt .= $this->addDivider();

        // Doctor signature
        $receipt .= "\n";
        $receipt .= $this->centerText("Dr. H.D.P. Darshani");
        $receipt .= $this->centerText("Ayurvedic Physician");

        $receipt .= $this->addDivider();
        $receipt .= $this->getFooter();

        return $receipt;
    }

    // ============================================
    // HELPER FUNCTIONS
    // ============================================

    private function getHeader()
    {
        $header = "";

        // Company name in Sinhala (bold)
        $header .= $this->centerText($this->companyInfo['name_sinhala'], true);

        // Tagline in Sinhala
        $header .= $this->centerText($this->companyInfo['tagline']);

        // English name and tagline
        $header .= $this->centerText($this->companyInfo['name_english']);
        $header .= $this->centerText($this->companyInfo['tagline_english']);

        // Address
        $header .= $this->centerText($this->companyInfo['address']);

        // Contact info
        $header .= $this->centerText($this->companyInfo['tel']);
        $header .= $this->centerText($this->companyInfo['email']);

        return $header;
    }

    private function getFooter()
    {
        $footer = "";
        $footer .= "\n";
        $footer .= $this->centerText("Thank You for Your Purchase!");
        $footer .= $this->centerText("Please visit us again");
        $footer .= $this->centerText("For inquiries: info@erundeniyaayu.lk");
        $footer .= $this->centerText("Tel: +94 77 936 6308");
        $footer .= "\n";
        $footer .= $this->addDivider();
        $footer .= $this->centerText("© 2025. W. E. Erundeniya and Sons");
        $footer .= $this->centerText("All rights reserved");
        $footer .= $this->centerText("All Goods once sold can't be returned.");
        $footer .= $this->centerText("Powered By www.evontech.lk");
        $footer .= "\n\n\n";

        return $footer;
    }

    private function centerText($text, $bold = false)
    {
        $padding = floor(($this->paperWidth - mb_strlen($text)) / 2);
        $padding = max(0, $padding);

        $centered = str_repeat(' ', $padding) . $text . "\n";

        return $centered;
    }

    private function addLine($label, $value = '', $bold = false)
    {
        if (empty($value)) {
            return $label . "\n";
        }

        $labelLength = mb_strlen($label);
        $valueLength = mb_strlen($value);
        $spaces = $this->paperWidth - $labelLength - $valueLength;
        $spaces = max(1, $spaces);

        return $label . str_repeat(' ', $spaces) . $value . "\n";
    }

    private function addDivider($char = '-')
    {
        return str_repeat($char, $this->paperWidth) . "\n";
    }

    private function addItemHeader()
    {
        $header = "";

        if ($this->paperWidth == self::WIDTH_58MM) {
            // 58mm: Item(14) Qty(4) Price(6) Total(7)
            $header .= "Item" . str_repeat(' ', 10) . "Qty" . " Price" . " Total\n";
        } else {
            // 80mm: Item(20) Qty(6) Price(10) Total(11)
            $header .= "Item" . str_repeat(' ', 16) . "Qty" . str_repeat(' ', 3) . "Price" . str_repeat(' ', 5) . "Total\n";
        }

        $header .= $this->addDivider();

        return $header;
    }

    private function addItem($name, $qty, $price, $total)
    {
        $item = "";

        if ($this->paperWidth == self::WIDTH_58MM) {
            // Truncate item name if too long
            $maxNameLength = 14;
            if (mb_strlen($name) > $maxNameLength) {
                $name = mb_substr($name, 0, $maxNameLength - 1) . '.';
            }

            $nameStr = str_pad($name, $maxNameLength);
            $qtyStr = str_pad($qty, 3, ' ', STR_PAD_LEFT);
            $priceStr = str_pad(number_format($price, 2), 6, ' ', STR_PAD_LEFT);
            $totalStr = str_pad(number_format($total, 2), 7, ' ', STR_PAD_LEFT);

            $item .= $nameStr . ' ' . $qtyStr . $priceStr . $totalStr . "\n";
        } else {
            // 80mm format
            $maxNameLength = 20;
            if (mb_strlen($name) > $maxNameLength) {
                $name = mb_substr($name, 0, $maxNameLength - 1) . '.';
            }

            $nameStr = str_pad($name, $maxNameLength);
            $qtyStr = str_pad($qty, 5, ' ', STR_PAD_LEFT);
            $priceStr = str_pad(number_format($price, 2), 10, ' ', STR_PAD_LEFT);
            $totalStr = str_pad(number_format($total, 2), 11, ' ', STR_PAD_LEFT);

            $item .= $nameStr . ' ' . $qtyStr . $priceStr . $totalStr . "\n";
        }

        return $item;
    }

    private function addTotal($label, $amount, $bold = false)
    {
        $amountStr = 'Rs. ' . number_format($amount, 2);
        $labelLength = mb_strlen($label);
        $amountLength = mb_strlen($amountStr);
        $spaces = $this->paperWidth - $labelLength - $amountLength;
        $spaces = max(1, $spaces);

        return $label . str_repeat(' ', $spaces) . $amountStr . "\n";
    }

    private function wrapText($text, $indent = 0)
    {
        $maxWidth = $this->paperWidth - $indent;
        $wrapped = "";

        $words = explode(' ', $text);
        $line = str_repeat(' ', $indent);

        foreach ($words as $word) {
            if (mb_strlen($line . $word) <= $maxWidth) {
                $line .= $word . ' ';
            } else {
                $wrapped .= rtrim($line) . "\n";
                $line = str_repeat(' ', $indent) . $word . ' ';
            }
        }

        $wrapped .= rtrim($line) . "\n";

        return $wrapped;
    }
}

// ============================================
// USAGE EXAMPLES
// ============================================

/**
 * Example 1: Generate Medical Bill
 */
function printMedicalBill($billId)
{
    global $conn;

    // Fetch bill data from database
    $query = "SELECT b.*, 
              a.appointment_number,
              p.title, p.name as patient_name, p.mobile as patient_mobile,
              u.user_name as created_by_name
              FROM bills b
              LEFT JOIN appointment a ON b.appointment_id = a.id
              LEFT JOIN patient p ON a.patient_id = p.id
              LEFT JOIN user u ON b.created_by = u.id
              WHERE b.id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $billId);
    $stmt->execute();
    $result = $stmt->get_result();
    $billData = $result->fetch_assoc();

    if (!$billData) {
        return false;
    }

    // Generate receipt
    $printer = new ThermalReceiptPrinter(ThermalReceiptPrinter::WIDTH_58MM);
    $receipt = $printer->generateMedicalBill($billData);

    // Return receipt text for printing
    return $receipt;
}

/**
 * Example 2: Generate OPD Treatment Bill
 */
function printOPDBill($billId)
{
    global $conn;

    $query = "SELECT tb.*, u.user_name as created_by_name
              FROM treatment_bills tb
              LEFT JOIN user u ON tb.created_by = u.id
              WHERE tb.id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $billId);
    $stmt->execute();
    $result = $stmt->get_result();
    $billData = $result->fetch_assoc();

    if (!$billData) {
        return false;
    }

    // Generate receipt
    $printer = new ThermalReceiptPrinter(ThermalReceiptPrinter::WIDTH_58MM);
    $receipt = $printer->generateOPDBill($billData);

    return $receipt;
}

/**
 * Example 3: Generate Prescription
 */
function printPrescription($prescriptionId)
{
    global $conn;

    $query = "SELECT p.*,
              pt.title, pt.name as patient_name, pt.mobile as patient_mobile,
              a.appointment_number
              FROM prescriptions p
              LEFT JOIN patient pt ON p.patient_id = pt.id
              LEFT JOIN appointment a ON p.appointment_id = a.id
              WHERE p.id = ?";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $prescriptionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $prescriptionData = $result->fetch_assoc();

    if (!$prescriptionData) {
        return false;
    }

    // Generate receipt
    $printer = new ThermalReceiptPrinter(ThermalReceiptPrinter::WIDTH_58MM);
    $receipt = $printer->generatePrescription($prescriptionData);

    return $receipt;
}

/**
 * Print to thermal printer or browser
 */
function outputReceipt($receiptText, $outputMethod = 'browser')
{
    if ($outputMethod == 'browser') {
        // Output to browser for preview
        header('Content-Type: text/plain; charset=utf-8');
        echo $receiptText;
    } elseif ($outputMethod == 'file') {
        // Save to file
        $filename = 'receipt_' . time() . '.txt';
        file_put_contents($filename, $receiptText);
        return $filename;
    } elseif ($outputMethod == 'printer') {
        // Send to network printer (ESC/POS)
        // This requires printer IP/port configuration
        // Example: Send to network printer at 192.168.1.100:9100

        $printerIP = '192.168.1.100';
        $printerPort = 9100;

        $socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

        if (socket_connect($socket, $printerIP, $printerPort)) {
            socket_write($socket, $receiptText);
            socket_close($socket);
            return true;
        }

        return false;
    }
}
