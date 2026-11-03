<?php

namespace Chanthoeun\FilamentDocumentBuilder\Support;

class LayoutTemplates
{
    public static function getOptions(): array
    {
        return [
            'invoice' => 'Professional Invoice (A4)',
            'receipt' => 'POS Cash Receipt (Narrow)',
            'certificate' => 'Certificate of Completion (Landscape)',
        ];
    }

    public static function getTemplate(string $type): string
    {
        return match ($type) {
            'invoice' => self::getInvoice(),
            'receipt' => self::getReceipt(),
            'certificate' => self::getCertificate(),
            default => '',
        };
    }

    protected static function getInvoice(): string
    {
        return <<<'HTML'
<div style="font-family: sans-serif; font-size: 13px; color: #333; max-width: 800px; margin: 0 auto;">
    
    <!-- Header Section -->
    <table style="width: 100%; border: none; margin-bottom: 30px;">
        <tbody>
            <tr>
                <td style="width: 50%; vertical-align: top; border: none;">
                    <h1 style="color: #2c3e50; margin: 0; font-size: 32px;">INVOICE</h1>
                    <p style="margin: 5px 0 0; color: #7f8c8d;">#INV-{{ id }}<br>Date: {{ created_at }}</p>
                </td>
                <td style="width: 50%; text-align: right; vertical-align: top; border: none;">
                    <div style="width: 150px; height: 50px; background-color: #eee; display: inline-block; text-align: center; line-height: 50px; font-weight: bold; color: #999;">YOUR LOGO</div>
                    <p style="margin: 10px 0 0;">123 Business Road<br>Phnom Penh, Cambodia<br>hello@company.com</p>
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Billing Details -->
    <table style="width: 100%; border: none; margin-bottom: 30px;">
        <tbody>
            <tr>
                <td style="width: 50%; vertical-align: top; border: none;">
                    <h3 style="color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 5px; margin-bottom: 10px;">Billed To:</h3>
                    <strong>{{ customer.name }}</strong><br>
                    {{ customer.address }}<br>
                    {{ customer.phone }}<br>
                    {{ customer.email }}
                </td>
                <td style="width: 50%; vertical-align: top; text-align: right; border: none;">
                    <h3 style="color: #2c3e50; border-bottom: 2px solid #eee; padding-bottom: 5px; margin-bottom: 10px;">Payment Details:</h3>
                    <strong>Bank:</strong> ABA Bank<br>
                    <strong>Account Name:</strong> Your Company<br>
                    <strong>Account No:</strong> 000 111 222<br>
                    <strong>Due Date:</strong> {{ due_date }}
                </td>
            </tr>
        </tbody>
    </table>

    <!-- Items Table -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 30px;">
        <thead>
            <tr style="background-color: #2c3e50; color: #ffffff;">
                <th style="padding: 10px; text-align: left; border: 1px solid #2c3e50;">Description</th>
                <th style="padding: 10px; text-align: center; border: 1px solid #2c3e50; width: 15%;">Qty</th>
                <th style="padding: 10px; text-align: right; border: 1px solid #2c3e50; width: 20%;">Unit Price</th>
                <th style="padding: 10px; text-align: right; border: 1px solid #2c3e50; width: 20%;">Total</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;">{{ item_1_name }}</td>
                <td style="padding: 10px; text-align: center; border: 1px solid #ddd;">{{ item_1_qty }}</td>
                <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">${{ item_1_price }}</td>
                <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">${{ item_1_total }}</td>
            </tr>
            <tr style="background-color: #f9f9f9;">
                <td style="padding: 10px; border: 1px solid #ddd;">{{ item_2_name }}</td>
                <td style="padding: 10px; text-align: center; border: 1px solid #ddd;">{{ item_2_qty }}</td>
                <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">${{ item_2_price }}</td>
                <td style="padding: 10px; text-align: right; border: 1px solid #ddd;">${{ item_2_total }}</td>
            </tr>
        </tbody>
    </table>

    <!-- Totals -->
    <table style="width: 40%; float: right; border-collapse: collapse; border: none;">
        <tbody>
            <tr>
                <td style="padding: 8px; text-align: left; font-weight: bold; border-bottom: 1px solid #ddd; border-left: none; border-right: none; border-top: none;">Subtotal:</td>
                <td style="padding: 8px; text-align: right; border-bottom: 1px solid #ddd; border-left: none; border-right: none; border-top: none;">${{ subtotal }}</td>
            </tr>
            <tr>
                <td style="padding: 8px; text-align: left; font-weight: bold; border-bottom: 1px solid #ddd; border-left: none; border-right: none; border-top: none;">Tax (10%):</td>
                <td style="padding: 8px; text-align: right; border-bottom: 1px solid #ddd; border-left: none; border-right: none; border-top: none;">${{ tax }}</td>
            </tr>
            <tr>
                <td style="padding: 12px; text-align: left; font-weight: bold; font-size: 16px; color: #2c3e50; background-color: #ecf0f1; border: none;">Total:</td>
                <td style="padding: 12px; text-align: right; font-weight: bold; font-size: 16px; color: #2c3e50; background-color: #ecf0f1; border: none;">${{ total }}</td>
            </tr>
        </tbody>
    </table>
    <div style="clear: both;"></div>

    <!-- Footer Note -->
    <div style="margin-top: 50px; text-align: center; color: #7f8c8d; border-top: 1px solid #eee; padding-top: 15px;">
        Thank you for your business!
    </div>
</div>
HTML;
    }

    protected static function getReceipt(): string
    {
        return <<<'HTML'
<div style="font-family: monospace; font-size: 12px; color: #000; width: 100%; max-width: 300px; margin: 0 auto; text-align: center;">
    
    <h2 style="margin: 0; font-size: 18px;">YOUR STORE NAME</h2>
    <p style="margin: 5px 0 15px;">123 Market Street<br>Tel: 012 345 678</p>
    
    <div style="border-top: 1px dashed #000; border-bottom: 1px dashed #000; padding: 10px 0; margin-bottom: 15px; text-align: left;">
        Receipt: #{{ receipt_no }}<br>
        Date: {{ created_at }}<br>
        Cashier: {{ cashier_name }}
    </div>

    <table style="width: 100%; border-collapse: collapse; text-align: left; margin-bottom: 15px; border: none;">
        <thead>
            <tr>
                <th style="padding-bottom: 5px; border-bottom: 1px solid #000; border-top: none; border-left: none; border-right: none;">Item</th>
                <th style="padding-bottom: 5px; text-align: center; border-bottom: 1px solid #000; border-top: none; border-left: none; border-right: none;">Qty</th>
                <th style="padding-bottom: 5px; text-align: right; border-bottom: 1px solid #000; border-top: none; border-left: none; border-right: none;">Amt</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td style="padding: 5px 0; border: none;">{{ item_name }}</td>
                <td style="padding: 5px 0; text-align: center; border: none;">{{ item_qty }}</td>
                <td style="padding: 5px 0; text-align: right; border: none;">${{ item_total }}</td>
            </tr>
        </tbody>
    </table>

    <table style="width: 100%; border-collapse: collapse; text-align: right; margin-bottom: 20px; border: none;">
        <tbody>
            <tr>
                <td style="padding: 2px 0; border: none;">Subtotal:</td>
                <td style="padding: 2px 0; border: none;">${{ subtotal }}</td>
            </tr>
            <tr style="font-size: 14px; font-weight: bold;">
                <td style="padding: 5px 0; border: none;">TOTAL:</td>
                <td style="padding: 5px 0; border: none;">${{ total }}</td>
            </tr>
            <tr>
                <td style="padding: 2px 0; border: none;">Cash:</td>
                <td style="padding: 2px 0; border: none;">${{ cash_received }}</td>
            </tr>
            <tr>
                <td style="padding: 2px 0; border: none;">Change:</td>
                <td style="padding: 2px 0; border: none;">${{ change }}</td>
            </tr>
        </tbody>
    </table>

    <div style="border-top: 1px dashed #000; padding-top: 10px;">
        * THANK YOU PLEASE COME AGAIN *
    </div>
</div>
HTML;
    }

    protected static function getCertificate(): string
    {
        return <<<'HTML'
<div style="font-family: serif; color: #333; max-width: 1000px; margin: 0 auto; text-align: center; border: 15px solid #0a4b8f; padding: 50px; background-color: #fdfbf7;">
    
    <!-- Logo -->
    <div style="margin-bottom: 30px;">
        <div style="width: 100px; height: 100px; background-color: #0a4b8f; border-radius: 50%; display: inline-block; text-align: center; line-height: 100px; font-weight: bold; color: #fff; font-family: sans-serif;">LOGO</div>
    </div>

    <h1 style="font-size: 50px; color: #0a4b8f; margin: 0; text-transform: uppercase; letter-spacing: 2px;">Certificate of Completion</h1>
    
    <p style="font-size: 20px; font-style: italic; margin: 30px 0;">This is to certify that</p>
    
    <h2 style="font-size: 40px; border-bottom: 2px solid #0a4b8f; display: inline-block; padding: 0 40px 10px; margin: 0;">{{ student.name }}</h2>
    
    <p style="font-size: 20px; margin: 30px 0;">has successfully completed the requirements for the</p>
    
    <h3 style="font-size: 30px; font-weight: normal; margin: 0; color: #2c3e50;">{{ course.name }} Program</h3>
    
    <p style="font-size: 16px; margin: 20px 0 50px;">Awarded on this day, {{ awarded_date }}</p>

    <!-- Signatures Table -->
    <table style="width: 100%; border: none; margin-top: 50px;">
        <tbody>
            <tr>
                <td style="width: 33%; text-align: center; vertical-align: bottom; border: none;">
                    <div style="border-bottom: 1px solid #000; width: 80%; margin: 0 auto 10px; height: 40px;"></div>
                    <strong>{{ instructor.name }}</strong><br>
                    Lead Instructor
                </td>
                <td style="width: 34%; text-align: center; border: none;">
                    <div style="width: 80px; height: 80px; border: 2px dashed #0a4b8f; border-radius: 50%; display: inline-block; line-height: 80px; color: #0a4b8f; font-size: 12px; font-family: sans-serif;">SEAL</div>
                </td>
                <td style="width: 33%; text-align: center; vertical-align: bottom; border: none;">
                    <div style="border-bottom: 1px solid #000; width: 80%; margin: 0 auto 10px; height: 40px;"></div>
                    <strong>{{ director.name }}</strong><br>
                    Program Director
                </td>
            </tr>
        </tbody>
    </table>
</div>
HTML;
    }
}
