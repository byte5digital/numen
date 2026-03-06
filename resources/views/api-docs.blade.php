<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Numen API Documentation</title>
    <meta name="robots" content="noindex">
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; }

        body {
            margin: 0;
            background: #1a1a2e;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
        }

        #header {
            background: linear-gradient(135deg, #0f0f1a 0%, #1a1a2e 100%);
            border-bottom: 1px solid rgba(139, 92, 246, 0.3);
            padding: 16px 24px;
            display: flex;
            align-items: center;
            gap: 16px;
        }

        #header .logo {
            font-size: 22px;
            font-weight: 700;
            color: #fff;
            letter-spacing: -0.5px;
        }

        #header .logo span {
            color: #8b5cf6;
        }

        #header .subtitle {
            font-size: 13px;
            color: rgba(255,255,255,0.45);
            margin-top: 2px;
        }

        #header .spec-link {
            margin-left: auto;
            font-size: 12px;
            color: rgba(139, 92, 246, 0.8);
            text-decoration: none;
            border: 1px solid rgba(139, 92, 246, 0.4);
            padding: 5px 12px;
            border-radius: 6px;
            transition: all 0.15s;
        }

        #header .spec-link:hover {
            background: rgba(139, 92, 246, 0.15);
            color: #a78bfa;
        }

        #swagger-ui {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 16px 64px;
        }

        /* Dark theme overrides */
        .swagger-ui { color: #e2e8f0; }

        .swagger-ui .topbar { display: none; }

        .swagger-ui .info { margin: 32px 0 24px; }
        .swagger-ui .info .title { color: #f1f5f9; font-size: 28px; }
        .swagger-ui .info p,
        .swagger-ui .info li { color: #94a3b8; }
        .swagger-ui .info a { color: #a78bfa; }

        .swagger-ui .scheme-container {
            background: #0f172a;
            border: 1px solid rgba(139,92,246,0.2);
            border-radius: 8px;
            padding: 16px;
            margin-bottom: 24px;
            box-shadow: none;
        }

        .swagger-ui .opblock-tag {
            border-bottom: 1px solid rgba(255,255,255,0.08);
            color: #e2e8f0;
        }
        .swagger-ui .opblock-tag:hover { background: rgba(255,255,255,0.03); }

        .swagger-ui .opblock {
            border-radius: 8px;
            margin: 8px 0;
            border: 1px solid rgba(255,255,255,0.1);
            background: #0f172a;
            box-shadow: none;
        }

        .swagger-ui .opblock.opblock-get    { border-color: rgba(59,130,246,0.4);  background: rgba(59,130,246,0.05); }
        .swagger-ui .opblock.opblock-post   { border-color: rgba(34,197,94,0.4);   background: rgba(34,197,94,0.05); }
        .swagger-ui .opblock.opblock-put    { border-color: rgba(234,179,8,0.4);   background: rgba(234,179,8,0.05); }
        .swagger-ui .opblock.opblock-patch  { border-color: rgba(249,115,22,0.4);  background: rgba(249,115,22,0.05); }
        .swagger-ui .opblock.opblock-delete { border-color: rgba(239,68,68,0.4);   background: rgba(239,68,68,0.05); }

        .swagger-ui .opblock .opblock-summary-path { color: #e2e8f0; }
        .swagger-ui .opblock .opblock-summary-description { color: #94a3b8; }
        .swagger-ui .opblock-body-add-btn { color: #a78bfa; }

        .swagger-ui .opblock-section-header {
            background: rgba(255,255,255,0.04);
            border-bottom: 1px solid rgba(255,255,255,0.08);
        }
        .swagger-ui .opblock-section-header h4 { color: #cbd5e1; }

        .swagger-ui table thead tr th,
        .swagger-ui table thead tr td { color: #94a3b8; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .swagger-ui .parameter__name { color: #e2e8f0; }
        .swagger-ui .parameter__type { color: #a78bfa; }

        .swagger-ui .response-col_status { color: #e2e8f0; }
        .swagger-ui .response-col_description { color: #94a3b8; }

        .swagger-ui .model-title { color: #e2e8f0; }
        .swagger-ui .model { color: #94a3b8; }
        .swagger-ui .prop-name { color: #a78bfa; }
        .swagger-ui .prop-type { color: #34d399; }

        .swagger-ui input[type=text],
        .swagger-ui input[type=password],
        .swagger-ui textarea,
        .swagger-ui select {
            background: #1e293b;
            border: 1px solid rgba(255,255,255,0.15);
            color: #e2e8f0;
            border-radius: 6px;
        }

        .swagger-ui .btn {
            border-radius: 6px;
            font-weight: 500;
        }
        .swagger-ui .btn.execute {
            background: #7c3aed;
            border-color: #7c3aed;
            color: #fff;
        }
        .swagger-ui .btn.execute:hover { background: #6d28d9; border-color: #6d28d9; }
        .swagger-ui .btn.try-out__btn {
            border-color: rgba(139,92,246,0.5);
            color: #a78bfa;
        }

        .swagger-ui .highlight-code {
            background: #0f172a !important;
            border-radius: 6px;
        }
        .swagger-ui .microlight { background: #0f172a; color: #94a3b8; }

        .swagger-ui .auth-wrapper { color: #e2e8f0; }
        .swagger-ui .auth-container h4 { color: #e2e8f0; }
        .swagger-ui .dialog-ux .modal-ux { background: #1e293b; border: 1px solid rgba(139,92,246,0.3); }
        .swagger-ui .dialog-ux .modal-ux-header { border-bottom: 1px solid rgba(255,255,255,0.1); }
        .swagger-ui .dialog-ux .modal-ux-header h3 { color: #e2e8f0; }

        .swagger-ui .servers > label select { background: #1e293b; color: #e2e8f0; }
        .swagger-ui section.models { border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; }
        .swagger-ui section.models h4 { color: #e2e8f0; }
        .swagger-ui .model-container { background: #0f172a; }
        .swagger-ui .models-control { background: rgba(255,255,255,0.03); }
    </style>
</head>
<body>
    <div id="header">
        <div>
            <div class="logo">numen<span>.</span></div>
            <div class="subtitle">API Documentation</div>
        </div>
        <a class="spec-link" href="{{ route('api.documentation.spec') }}" target="_blank">OpenAPI Spec (YAML)</a>
    </div>

    <div id="swagger-ui"></div>

    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
    <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
    <script>
        SwaggerUIBundle({
            url: "{{ route('api.documentation.spec') }}",
            dom_id: '#swagger-ui',
            presets: [
                SwaggerUIBundle.presets.apis,
                SwaggerUIStandalonePreset,
            ],
            layout: 'StandaloneLayout',
            deepLinking: true,
            displayRequestDuration: true,
            persistAuthorization: true,
            tryItOutEnabled: true,
            requestInterceptor: function(request) {
                return request;
            },
        });
    </script>
</body>
</html>
