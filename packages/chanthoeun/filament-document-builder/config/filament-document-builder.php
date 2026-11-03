<?php

return [
    // Define the default paper size and orientation for new templates
    'default_paper_size' => 'a4',
    'default_orientation' => 'portrait',

    // Define the default margins for new templates (in mm)
    'default_margins' => [
        'top' => 16,
        'bottom' => 16,
        'left' => 15,
        'right' => 15,
        'header' => 9,
        'footer' => 9,
    ],

    // Define the predefined templates for the TinyMCE editor
    'templates' => [
        [
            'title' => 'Layout - 1 Column',
            'description' => 'A table with 1 column taking full width',
            'content' => '<table style="width: 100%; border-collapse: collapse; border: none;"><tbody><tr><td style="width: 100%; padding: 5px; vertical-align: top; border: none;">Column 1</td></tr></tbody></table><p><br></p>',
        ],
        [
            'title' => 'Layout - 2 Columns',
            'description' => 'A table with 2 equal columns',
            'content' => '<table style="width: 100%; border-collapse: collapse; border: none;"><tbody><tr><td style="width: 50%; padding: 5px; vertical-align: top; border: none;">Column 1</td><td style="width: 50%; padding: 5px; vertical-align: top; border: none;">Column 2</td></tr></tbody></table><p><br></p>',
        ],
        [
            'title' => 'Layout - 3 Columns',
            'description' => 'A table with 3 equal columns',
            'content' => '<table style="width: 100%; border-collapse: collapse; border: none;"><tbody><tr><td style="width: 33.33%; padding: 5px; vertical-align: top; border: none;">Column 1</td><td style="width: 33.33%; padding: 5px; vertical-align: top; border: none;">Column 2</td><td style="width: 33.33%; padding: 5px; vertical-align: top; border: none;">Column 3</td></tr></tbody></table><p><br></p>',
        ],
        [
            'title' => 'Layout - 4 Columns',
            'description' => 'A table with 4 equal columns',
            'content' => '<table style="width: 100%; border-collapse: collapse; border: none;"><tbody><tr><td style="width: 25%; padding: 5px; vertical-align: top; border: none;">Column 1</td><td style="width: 25%; padding: 5px; vertical-align: top; border: none;">Column 2</td><td style="width: 25%; padding: 5px; vertical-align: top; border: none;">Column 3</td><td style="width: 25%; padding: 5px; vertical-align: top; border: none;">Column 4</td></tr></tbody></table><p><br></p>',
        ],
        [
            'title' => 'Layout - 1/3 Left, 2/3 Right',
            'description' => '2 Columns: smaller left, larger right',
            'content' => '<table style="width: 100%; border-collapse: collapse; border: none;"><tbody><tr><td style="width: 33.33%; padding: 5px; vertical-align: top; border: none;">Left Sidebar</td><td style="width: 66.66%; padding: 5px; vertical-align: top; border: none;">Main Content</td></tr></tbody></table><p><br></p>',
        ],
        [
            'title' => 'Layout - 2/3 Left, 1/3 Right',
            'description' => '2 Columns: larger left, smaller right',
            'content' => '<table style="width: 100%; border-collapse: collapse; border: none;"><tbody><tr><td style="width: 66.66%; padding: 5px; vertical-align: top; border: none;">Main Content</td><td style="width: 33.33%; padding: 5px; vertical-align: top; border: none;">Right Sidebar</td></tr></tbody></table><p><br></p>',
        ],
        [
            'title' => 'Header - Logo & Title',
            'description' => 'A professional document header',
            'content' => '<table style="width: 100%; border-collapse: collapse; border-bottom: 2px solid #000; margin-bottom: 20px;"><tbody><tr><td style="width: 20%; padding: 10px; vertical-align: middle; border: none;"><div style="display: inline-block; width: 80px; height: 80px; border: 1px solid #000; border-radius: 50%; text-align: center; line-height: 80px;">LOGO</div></td><td style="width: 80%; padding: 10px; vertical-align: middle; text-align: right; border: none;"><h2 style="margin: 0;">COMPANY NAME</h2><p style="margin: 0; color: #555;">Company Address &bull; Contact Info &bull; Email</p></td></tr></tbody></table><p><br></p>',
        ],
        [
            'title' => 'Component - Invoice/Receipt Table',
            'description' => 'A standardized 5-column item table',
            'content' => '<table style="width: 100%; border-collapse: collapse; border: 1px solid #000; margin-bottom: 20px;"><thead><tr style="background-color: #f2f2f2;"><th style="border: 1px solid #000; padding: 8px; text-align: center; width: 5%;">No.</th><th style="border: 1px solid #000; padding: 8px; text-align: left; width: 50%;">Description</th><th style="border: 1px solid #000; padding: 8px; text-align: center; width: 15%;">Qty</th><th style="border: 1px solid #000; padding: 8px; text-align: right; width: 15%;">Unit Price</th><th style="border: 1px solid #000; padding: 8px; text-align: right; width: 15%;">Total</th></tr></thead><tbody><tr><td style="border: 1px solid #000; padding: 8px; text-align: center;">1</td><td style="border: 1px solid #000; padding: 8px;">Item Description</td><td style="border: 1px solid #000; padding: 8px; text-align: center;">1</td><td style="border: 1px solid #000; padding: 8px; text-align: right;">$0.00</td><td style="border: 1px solid #000; padding: 8px; text-align: right;">$0.00</td></tr><tr><td colspan="4" style="border: 1px solid #000; padding: 8px; text-align: right; font-weight: bold;">Grand Total:</td><td style="border: 1px solid #000; padding: 8px; text-align: right; font-weight: bold;">$0.00</td></tr></tbody></table><p><br></p>',
        ],
        [
            'title' => 'Element - Signatures (2 Persons)',
            'description' => 'Signature block for 2 parties',
            'content' => '<table style="width: 100%; border-collapse: collapse; border: none; margin-top: 40px;"><tbody><tr><td style="width: 50%; padding: 5px; text-align: center; vertical-align: bottom; border: none;"><div style="display: inline-block; width: 200px; border-bottom: 1px solid #000; padding-bottom: 5px;">ហត្ថលេខា / Signature 1</div><p style="margin-top: 5px;">Name / Title</p></td><td style="width: 50%; padding: 5px; text-align: center; vertical-align: bottom; border: none;"><div style="display: inline-block; width: 200px; border-bottom: 1px solid #000; padding-bottom: 5px;">ហត្ថលេខា / Signature 2</div><p style="margin-top: 5px;">Name / Title</p></td></tr></tbody></table><p><br></p>',
        ],
        [
            'title' => 'Shape - Circle (Logo)',
            'description' => 'A circular shape for logos or avatars',
            'content' => '<div style="display: inline-block; width: 80px; height: 80px; border: 1px solid #000; border-radius: 50%; text-align: center;">LOGO</div>',
        ],
        [
            'title' => 'Shape - Square Box',
            'description' => 'A simple square box',
            'content' => '<div style="display: inline-block; width: 80px; height: 80px; border: 1px solid #000; text-align: center;">BOX</div>',
        ],
        [
            'title' => 'Shape - Rectangle Photo Box (4x6)',
            'description' => '4x6 Photo Box for Khmer forms',
            'content' => '<div style="display: inline-block; width: 80px; height: 100px; border: 1px solid #000; text-align: center;">រូបថត<br>៤x៦</div>',
        ],
        [
            'title' => 'Element - Checkbox (Small Square)',
            'description' => 'Small square for checkboxes',
            'content' => '<div style="display: inline-block; width: 16px; height: 16px; border: 1px solid #000; text-align: center;"></div>',
        ],
        [
            'title' => 'Shape - Rounded Rectangle',
            'description' => 'A rectangle with rounded corners',
            'content' => '<div style="display: inline-block; width: 120px; height: 60px; border: 1px solid #000; border-radius: 10px; text-align: center;">TEXT</div>',
        ],
        [
            'title' => 'Shape - Oval',
            'description' => 'An oval shape',
            'content' => '<div style="display: inline-block; width: 120px; height: 60px; border: 1px solid #000; border-radius: 50%; text-align: center;">OVAL</div>',
        ],
        [
            'title' => 'Element - Signature Area',
            'description' => 'A line for signatures',
            'content' => '<div style="display: inline-block; width: 200px; text-align: center; border-bottom: 1px solid #000; padding-bottom: 5px; margin-top: 40px;">ហត្ថលេខា / Signature</div>',
        ],
    ],
];
