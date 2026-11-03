var customShapesRetries = 0;

function registerCustomShapes() {
    if (typeof tinymce !== 'undefined') {
        tinymce.PluginManager.add('custom_shapes', function (editor, url) {

            // Custom templates dropdown for TinyMCE versions without the legacy template plugin.
            editor.ui.registry.addMenuButton('document_templates', {
                text: 'Templates',
                icon: 'template',
                fetch: function (callback) {
                    var templates = editor.getParam('document_templates', []);
                    var items = [];

                    if (!Array.isArray(templates) || templates.length === 0) {
                        items.push({
                            type: 'menuitem',
                            text: 'No templates configured',
                            disabled: true,
                            onAction: function () {}
                        });
                    } else {
                        templates.forEach(function(template) {
                            items.push({
                                type: 'menuitem',
                                text: template.title || 'Untitled template',
                                onAction: function () {
                                    editor.insertContent(template.content || '');
                                }
                            });
                        });
                    }

                    callback(items);
                }
            });

            // ── Insert Variable ──────────────────────────────────────────
            editor.ui.registry.addMenuButton('insert_variable', {
                text: 'Insert Variable',
                icon: 'sourcecode',
                fetch: function (callback) {
                    var vars = editor.getParam('document_variables', []);
                    var items = [];

                    if (vars.length === 0) {
                        items.push({
                            type: 'menuitem',
                            text: 'No model selected',
                            disabled: true,
                            onAction: function () {}
                        });
                    } else {
                        vars.forEach(function(v) {
                            items.push({
                                type: 'menuitem',
                                text: '{{ ' + v + ' }}',
                                onAction: function () {
                                    editor.insertContent('{{ ' + v + ' }}');
                                }
                            });
                        });
                    }
                    callback(items);
                }
            });

            // ── Insert QR Code ───────────────────────────────────────────
            editor.ui.registry.addButton('insert_qrcode', {
                text: 'QR Code',
                icon: 'image',
                tooltip: 'Insert QR Code',
                onAction: function () {
                    var vars = editor.getParam('document_variables', []);

                    if (vars.length === 0) {
                        editor.notificationManager.open({
                            text: 'No variables available. Please select a model first.',
                            type: 'warning',
                            timeout: 3000
                        });
                        return;
                    }

                    var variableItems = vars.map(function(v) {
                        return { value: v, text: v };
                    });

                    editor.windowManager.open({
                        title: 'Insert QR Code',
                        body: {
                            type: 'panel',
                            items: [
                                {
                                    type: 'selectbox',
                                    name: 'variable',
                                    label: 'Variable',
                                    items: variableItems
                                },
                                {
                                    type: 'input',
                                    name: 'size',
                                    label: 'Size (px)',
                                    inputMode: 'numeric',
                                    placeholder: '100'
                                }
                            ]
                        },
                        buttons: [
                            {
                                type: 'cancel',
                                name: 'cancel',
                                text: 'Cancel'
                            },
                            {
                                type: 'submit',
                                name: 'submit',
                                text: 'Insert',
                                primary: true
                            }
                        ],
                        initialData: {
                            variable: vars[0],
                            size: '100'
                        },
                        onSubmit: function (api) {
                            var data = api.getData();
                            var variable = data.variable;
                            var size = parseInt(data.size, 10) || 100;

                            size = Math.max(10, Math.min(1000, size));

                            editor.insertContent(
                                '{{#qrcode ' + variable + ' size=' + size + '}}'
                            );
                            api.close();
                        }
                    });
                }
            });

            // ── Insert Barcode ───────────────────────────────────────────
            editor.ui.registry.addButton('insert_barcode', {
                text: 'Barcode',
                icon: 'image',
                tooltip: 'Insert Barcode',
                onAction: function () {
                    var vars = editor.getParam('document_variables', []);

                    if (vars.length === 0) {
                        editor.notificationManager.open({
                            text: 'No variables available. Please select a model first.',
                            type: 'warning',
                            timeout: 3000
                        });
                        return;
                    }

                    var variableItems = vars.map(function(v) {
                        return { value: v, text: v };
                    });

                    var barcodeTypes = [
                        { value: 'C128', text: 'Code 128 (General)' },
                        { value: 'C128A', text: 'Code 128 A' },
                        { value: 'C128B', text: 'Code 128 B' },
                        { value: 'C128C', text: 'Code 128 C (Numeric)' },
                        { value: 'C39', text: 'Code 39' },
                        { value: 'EAN13', text: 'EAN-13' },
                        { value: 'EAN8', text: 'EAN-8' },
                        { value: 'UPCA', text: 'UPC-A' },
                        { value: 'UPCE', text: 'UPC-E' },
                        { value: 'I25', text: 'Interleaved 2 of 5' },
                    ];

                    editor.windowManager.open({
                        title: 'Insert Barcode',
                        body: {
                            type: 'panel',
                            items: [
                                {
                                    type: 'selectbox',
                                    name: 'variable',
                                    label: 'Variable',
                                    items: variableItems
                                },
                                {
                                    type: 'selectbox',
                                    name: 'type',
                                    label: 'Barcode Type',
                                    items: barcodeTypes
                                },
                                {
                                    type: 'input',
                                    name: 'width',
                                    label: 'Width factor (px per bar)',
                                    inputMode: 'numeric',
                                    placeholder: '2'
                                },
                                {
                                    type: 'input',
                                    name: 'height',
                                    label: 'Height (px)',
                                    inputMode: 'numeric',
                                    placeholder: '30'
                                }
                            ]
                        },
                        buttons: [
                            {
                                type: 'cancel',
                                name: 'cancel',
                                text: 'Cancel'
                            },
                            {
                                type: 'submit',
                                name: 'submit',
                                text: 'Insert',
                                primary: true
                            }
                        ],
                        initialData: {
                            variable: vars[0],
                            type: 'C128',
                            width: '2',
                            height: '30'
                        },
                        onSubmit: function (api) {
                            var data = api.getData();
                            var variable = data.variable;
                            var type = data.type || 'C128';
                            var width = parseInt(data.width, 10) || 2;
                            var height = parseInt(data.height, 10) || 30;

                            width = Math.max(1, Math.min(10, width));
                            height = Math.max(10, Math.min(200, height));

                            editor.insertContent(
                                '{{#barcode ' + variable + ' type=' + type + ' width=' + width + ' height=' + height + '}}'
                            );
                            api.close();
                        }
                    });
                }
            });

        });
    } else if (customShapesRetries < 50) {
        customShapesRetries++;
        setTimeout(registerCustomShapes, 100);
    }
}
registerCustomShapes();
