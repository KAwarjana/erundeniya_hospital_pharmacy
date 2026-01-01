/**
 * Thermal Receipt Printer - JavaScript Integration
 * Professional thermal receipt printing for browser
 */

class ThermalReceiptPrinter {
    constructor(paperWidth = 32) {
        this.paperWidth = paperWidth; // 32 for 58mm, 48 for 80mm
        this.companyInfo = {
            name_sinhala: 'එ. ඩබ්. ඩි. ඒරන්දෙණිය',
            name_english: 'E. W. D. Erundeniya',
            tagline: 'හෙළ ඖෂධාල',
            tagline_english: 'Quality Ayurvedic Products & Medicines',
            address: 'A/55 ඉරිදේව, පදුක්කදෙනිය, අංගිරිය.',
            tel: 'Tel: +94 77 936 6308',
            email: 'Email: info@erundeniyaayu.lk'
        };
    }

    // ============================================
    // MEDICAL BILL GENERATOR
    // ============================================
    generateMedicalBill(billData) {
        let receipt = this.getHeader();
        receipt += this.addDivider();

        // Receipt details
        receipt += this.addLine("Receipt No:", this.pad(billData.bill_number, 15, ' ', 'left'));
        receipt += this.addLine("Date:", this.formatDate(billData.created_at));
        receipt += this.addLine("Cashier:", billData.created_by_name || 'Evon Technologies');
        
        if (billData.payment_status) {
            receipt += this.addLine("Payment:", billData.payment_status);
        }

        receipt += this.addDivider();

        // Patient Info
        if (billData.patient_name) {
            receipt += this.addLine("Patient:", billData.patient_name);
            receipt += this.addLine("Mobile:", billData.patient_mobile);
        }

        if (billData.appointment_number) {
            receipt += this.addLine("Apt. No:", billData.appointment_number);
        }

        receipt += this.addDivider();

        // Items header
        receipt += this.addItemHeader();

        // Bill items
        let subtotal = 0;
        let itemCount = 0;
        
        // Doctor Fee
        if (billData.doctor_fee && billData.doctor_fee > 0) {
            const price = parseFloat(billData.doctor_fee);
            subtotal += price;
            itemCount++;
            receipt += this.addItem("මාධෝ සිංහ", 1, price, price);
        }

        // Medicine Cost
        if (billData.medicine_cost && billData.medicine_cost > 0) {
            const price = parseFloat(billData.medicine_cost);
            subtotal += price;
            itemCount++;
            receipt += this.addItem("බෙහෙත්", 1, price, price);
        }

        // Other Charges
        if (billData.other_charges && billData.other_charges > 0) {
            const price = parseFloat(billData.other_charges);
            subtotal += price;
            itemCount++;
            receipt += this.addItem("වෙනත් ගාස්තු", 1, price, price);
        }

        receipt += this.addDivider();

        // Totals
        receipt += this.addTotal("Subtotal:", subtotal);

        if (billData.discount_amount && billData.discount_amount > 0) {
            receipt += this.addTotal("Discount:", -parseFloat(billData.discount_amount));
        }

        receipt += this.addDivider('=');

        const finalTotal = subtotal - (parseFloat(billData.discount_amount) || 0);
        receipt += this.addTotal("TOTAL:", finalTotal, true);

        receipt += this.addDivider('=');

        // Payment details (if available)
        if (billData.paid_amount) {
            receipt += this.addTotal("Paid:", parseFloat(billData.paid_amount));
            const change = parseFloat(billData.paid_amount) - finalTotal;
            if (change > 0) {
                receipt += this.addTotal("Change:", change);
            }
        }

        receipt += this.addDivider();

        // Total items
        receipt += this.addLine("Total Items:", this.pad(itemCount.toString(), 15, ' ', 'left'));

        receipt += this.addDivider();
        receipt += this.getFooter();

        return receipt;
    }

    // ============================================
    // OPD TREATMENT BILL GENERATOR
    // ============================================
    generateOPDBill(billData) {
        let receipt = this.getHeader();
        receipt += this.addDivider();

        // Receipt details
        receipt += this.addLine("Bill No:", this.pad(billData.bill_number, 15, ' ', 'left'));
        receipt += this.addLine("Date:", this.formatDate(billData.created_at));
        receipt += this.addLine("Cashier:", billData.created_by_name || 'Evon Technologies');
        
        if (billData.payment_status) {
            receipt += this.addLine("Payment:", billData.payment_status);
        }

        receipt += this.addDivider();

        // Patient Info
        receipt += this.addLine("Patient:", billData.patient_name);
        receipt += this.addLine("Mobile:", billData.patient_mobile);

        receipt += this.addDivider();

        // Items header
        receipt += this.addItemHeader();

        // Treatment items
        let subtotal = 0;
        let itemCount = 0;

        if (billData.treatments_data) {
            const treatments = typeof billData.treatments_data === 'string' 
                ? JSON.parse(billData.treatments_data) 
                : billData.treatments_data;

            treatments.forEach(treatment => {
                const qty = parseInt(treatment.quantity || 1);
                const price = parseFloat(treatment.price);
                const total = price * qty;
                subtotal += total;
                itemCount++;
                
                receipt += this.addItem(treatment.name, qty, price, total);
            });
        }

        receipt += this.addDivider();

        // Totals
        receipt += this.addTotal("Subtotal:", subtotal);

        if (billData.discount_amount && billData.discount_amount > 0) {
            receipt += this.addTotal("Discount:", -parseFloat(billData.discount_amount));
        }

        receipt += this.addDivider('=');

        const finalTotal = parseFloat(billData.final_amount) || (subtotal - (parseFloat(billData.discount_amount) || 0));
        receipt += this.addTotal("TOTAL:", finalTotal, true);

        receipt += this.addDivider('=');

        // Payment details (if available)
        if (billData.paid_amount) {
            receipt += this.addTotal("Paid:", parseFloat(billData.paid_amount));
            const change = parseFloat(billData.paid_amount) - finalTotal;
            if (change > 0) {
                receipt += this.addTotal("Change:", change);
            }
        }

        receipt += this.addDivider();

        // Total items
        receipt += this.addLine("Total Items:", this.pad(itemCount.toString(), 15, ' ', 'left'));

        receipt += this.addDivider();
        receipt += this.getFooter();

        return receipt;
    }

    // ============================================
    // PRESCRIPTION GENERATOR
    // ============================================
    generatePrescription(prescriptionData) {
        let receipt = this.getHeader();
        receipt += this.addDivider();

        // Prescription details
        const presNo = 'PRES' + String(prescriptionData.id).padStart(3, '0');
        receipt += this.addLine("Prescription:", this.pad(presNo, 12, ' ', 'left'));
        receipt += this.addLine("Date:", this.formatDate(prescriptionData.created_at));

        receipt += this.addDivider();

        // Patient Info
        receipt += this.addLine("Patient:", prescriptionData.patient_name);
        receipt += this.addLine("Mobile:", prescriptionData.patient_mobile);

        if (prescriptionData.appointment_number) {
            receipt += this.addLine("Apt. No:", prescriptionData.appointment_number);
        }

        receipt += this.addDivider();

        // Prescription text
        receipt += this.centerText("PRESCRIPTION");
        receipt += "\n";

        const prescriptionLines = prescriptionData.prescription_text.split("\n");
        prescriptionLines.forEach(line => {
            receipt += this.wrapText(line);
        });

        receipt += this.addDivider();

        // Doctor signature
        receipt += "\n";
        receipt += this.centerText("Dr. H.D.P. Darshani");
        receipt += this.centerText("Ayurvedic Physician");

        receipt += this.addDivider();
        receipt += this.getFooter();

        return receipt;
    }

    // ============================================
    // HELPER METHODS
    // ============================================
    getHeader() {
        let header = "";
        header += this.centerText(this.companyInfo.name_sinhala);
        header += this.centerText(this.companyInfo.tagline);
        header += this.centerText(this.companyInfo.name_english);
        header += this.centerText(this.companyInfo.tagline_english);
        header += this.centerText(this.companyInfo.address);
        header += this.centerText(this.companyInfo.tel);
        header += this.centerText(this.companyInfo.email);
        return header;
    }

    getFooter() {
        let footer = "\n";
        footer += this.centerText("Thank You for Your Purchase!");
        footer += this.centerText("Please visit us again");
        footer += this.centerText("For inquiries: info@erundeniyaayu.lk");
        footer += this.centerText("Tel: +94 77 936 6308");
        footer += "\n";
        footer += this.addDivider();
        footer += this.centerText("© 2025. W. E. Erundeniya and Sons");
        footer += this.centerText("All rights reserved");
        footer += this.centerText("All Goods once sold can't be returned.");
        footer += this.centerText("Powered By www.evontech.lk");
        footer += "\n\n\n";
        return footer;
    }

    centerText(text) {
        const textLength = this.getTextLength(text);
        const padding = Math.floor((this.paperWidth - textLength) / 2);
        return ' '.repeat(Math.max(0, padding)) + text + "\n";
    }

    addLine(label, value = '') {
        if (!value) {
            return label + "\n";
        }

        const labelLength = this.getTextLength(label);
        const valueLength = this.getTextLength(value);
        const spaces = this.paperWidth - labelLength - valueLength;
        return label + ' '.repeat(Math.max(1, spaces)) + value + "\n";
    }

    addDivider(char = '-') {
        return char.repeat(this.paperWidth) + "\n";
    }

    addItemHeader() {
        let header = "";
        if (this.paperWidth === 32) {
            // 58mm format
            header += "Item" + ' '.repeat(10) + "Qty" + " Price" + " Total\n";
        } else {
            // 80mm format
            header += "Item" + ' '.repeat(16) + "Qty" + ' '.repeat(3) + "Price" + ' '.repeat(5) + "Total\n";
        }
        header += this.addDivider();
        return header;
    }

    addItem(name, qty, price, total) {
        let item = "";
        
        if (this.paperWidth === 32) {
            // 58mm format
            const maxNameLength = 14;
            if (this.getTextLength(name) > maxNameLength) {
                name = name.substring(0, maxNameLength - 1) + '.';
            }
            
            const nameStr = this.pad(name, maxNameLength);
            const qtyStr = this.pad(qty.toString(), 3, ' ', 'left');
            const priceStr = this.pad(this.formatMoney(price), 6, ' ', 'left');
            const totalStr = this.pad(this.formatMoney(total), 7, ' ', 'left');
            
            item += nameStr + ' ' + qtyStr + priceStr + totalStr + "\n";
        } else {
            // 80mm format
            const maxNameLength = 20;
            if (this.getTextLength(name) > maxNameLength) {
                name = name.substring(0, maxNameLength - 1) + '.';
            }
            
            const nameStr = this.pad(name, maxNameLength);
            const qtyStr = this.pad(qty.toString(), 5, ' ', 'left');
            const priceStr = this.pad(this.formatMoney(price), 10, ' ', 'left');
            const totalStr = this.pad(this.formatMoney(total), 11, ' ', 'left');
            
            item += nameStr + ' ' + qtyStr + priceStr + totalStr + "\n";
        }
        
        return item;
    }

    addTotal(label, amount) {
        const amountStr = 'Rs. ' + this.formatMoney(amount);
        const labelLength = this.getTextLength(label);
        const amountLength = this.getTextLength(amountStr);
        const spaces = this.paperWidth - labelLength - amountLength;
        return label + ' '.repeat(Math.max(1, spaces)) + amountStr + "\n";
    }

    wrapText(text, indent = 0) {
        const maxWidth = this.paperWidth - indent;
        let wrapped = "";
        const words = text.split(' ');
        let line = ' '.repeat(indent);
        
        words.forEach(word => {
            if (this.getTextLength(line + word) <= maxWidth) {
                line += word + ' ';
            } else {
                wrapped += line.trim() + "\n";
                line = ' '.repeat(indent) + word + ' ';
            }
        });
        
        wrapped += line.trim() + "\n";
        return wrapped;
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        const day = String(date.getDate()).padStart(2, '0');
        const month = date.toLocaleDateString('en-US', { month: 'short' });
        const year = date.getFullYear();
        const hours = String(date.getHours()).padStart(2, '0');
        const minutes = String(date.getMinutes()).padStart(2, '0');
        const ampm = date.getHours() >= 12 ? 'PM' : 'AM';
        
        return `${day} ${month} ${year}, ${hours}:${minutes} ${ampm}`;
    }

    formatMoney(amount) {
        return parseFloat(amount).toFixed(2);
    }

    pad(str, length, char = ' ', direction = 'right') {
        str = String(str);
        const currentLength = this.getTextLength(str);
        
        if (currentLength >= length) {
            return str;
        }
        
        const padding = char.repeat(length - currentLength);
        return direction === 'left' ? padding + str : str + padding;
    }

    getTextLength(text) {
        // Handle Sinhala and other Unicode characters
        // Approximate length calculation
        let length = 0;
        for (let i = 0; i < text.length; i++) {
            const code = text.charCodeAt(i);
            // Sinhala Unicode range: 0x0D80 - 0x0DFF
            if (code >= 0x0D80 && code <= 0x0DFF) {
                length += 1.5; // Sinhala characters are slightly wider
            } else {
                length += 1;
            }
        }
        return Math.ceil(length);
    }

    // ============================================
    // PRINT METHODS
    // ============================================
    printToWindow(receiptText) {
        const printWindow = window.open('', '', 'height=600,width=400');
        printWindow.document.write(`
            <html>
            <head>
                <title>Print Receipt</title>
                <style>
                    @page { margin: 0; }
                    body {
                        font-family: 'Courier New', monospace;
                        font-size: 12px;
                        line-height: 1.3;
                        margin: 0;
                        padding: 10px;
                        white-space: pre-wrap;
                        word-wrap: break-word;
                    }
                    @media print {
                        body { margin: 0; padding: 0; }
                    }
                </style>
            </head>
            <body>${receiptText}</body>
            </html>
        `);
        printWindow.document.close();
        printWindow.focus();
        setTimeout(() => {
            printWindow.print();
            printWindow.close();
        }, 250);
    }

    downloadAsText(receiptText, filename = 'receipt.txt') {
        const blob = new Blob([receiptText], { type: 'text/plain;charset=utf-8' });
        const url = window.URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        window.URL.revokeObjectURL(url);
    }
}

// ============================================
// INTEGRATION FUNCTIONS FOR YOUR PAGES
// ============================================

/**
 * Print Medical Bill (create_bill.php)
 */
function printThermalMedicalBill(billData) {
    const printer = new ThermalReceiptPrinter(32); // 58mm
    const receipt = printer.generateMedicalBill(billData);
    printer.printToWindow(receipt);
}

/**
 * Print OPD Bill (opd.php)
 */
function printThermalOPDBill(billData) {
    const printer = new ThermalReceiptPrinter(32); // 58mm
    const receipt = printer.generateOPDBill(billData);
    printer.printToWindow(receipt);
}

/**
 * Print Prescription (prescription.php)
 */
function printThermalPrescription(prescriptionData) {
    const printer = new ThermalReceiptPrinter(32); // 58mm
    const receipt = printer.generatePrescription(prescriptionData);
    printer.printToWindow(receipt);
}

// Export for use in other scripts
if (typeof module !== 'undefined' && module.exports) {
    module.exports = {
        ThermalReceiptPrinter,
        printThermalMedicalBill,
        printThermalOPDBill,
        printThermalPrescription
    };
}