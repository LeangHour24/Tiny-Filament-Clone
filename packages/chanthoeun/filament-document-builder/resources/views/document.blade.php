<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Document</title>
    <style>
        body {
            font-family: 'khmerbattambang', sans-serif;
            margin: 0;
            padding: 0;
            color: #000;
            font-size: 14px;
            line-height: 1.5;
        }
        .document-container {
            width: 100%;
        }
        /* Basic Tailwind-like classes for common styling */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .font-bold { font-weight: bold; }
        .text-xl { font-size: 1.25rem; }
        .text-2xl { font-size: 1.5rem; }
        .mt-4 { margin-top: 1rem; }
        .mb-4 { margin-bottom: 1rem; }
        .w-full { width: 100%; }
        
        *, *::before, *::after {
            box-sizing: border-box;
        }
        
        /* Table styles */
        table {
            width: 100%;
            /* Do not force border-collapse globally as it breaks border-radius in mPDF */
            border-spacing: 0;
        }
        
        /* Apply collapse only to tables that likely need it (data tables) */
        table[border="1"], 
        table[style*="collapse"] {
            border-collapse: collapse;
        }

        th, td {
            padding: 5px;
            vertical-align: top;
        }

        /* Fix border-radius for mPDF by forcing separate borders on elements with border-radius */
        [style*="border-radius"] {
            border-collapse: separate !important;
        }

        /* Ensure images and inline blocks behave properly */
        img {
            max-width: 100%;
            height: auto;
        }

    </style>
</head>
<body>
    <div class="document-container">
        {!! $htmlContent !!}
    </div>
</body>
</html>
