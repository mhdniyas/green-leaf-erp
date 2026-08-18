<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Green Leaf ERP Database Backup</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            margin: 0;
            padding: 30px 15px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05);
        }
        .header {
            background-color: #064e3b;
            background: linear-gradient(135deg, #064e3b 0%, #047857 100%);
            padding: 24px 30px;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 20px;
            font-weight: 700;
            letter-spacing: -0.025em;
        }
        .header p {
            margin: 6px 0 0 0;
            font-size: 13px;
            color: #a7f3d0;
        }
        .content {
            padding: 30px;
        }
        .alert-box {
            background-color: #ecfdf5;
            border: 1px solid #a7f3d0;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
        }
        .alert-box p {
            margin: 0;
            font-size: 14px;
            color: #065f46;
            font-weight: 600;
        }
        .meta-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 24px;
        }
        .meta-table td {
            padding: 10px 12px;
            font-size: 13px;
            border-bottom: 1px solid #f1f5f9;
        }
        .meta-table td.label {
            color: #64748b;
            font-weight: 600;
            width: 38%;
        }
        .meta-table td.value {
            color: #0f172a;
            font-weight: 700;
        }
        .footer {
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            padding: 20px 30px;
            font-size: 12px;
            color: #94a3b8;
            text-align: center;
        }
        .badge {
            display: inline-block;
            background-color: #e2e8f0;
            color: #334155;
            font-family: monospace;
            padding: 3px 8px;
            border-radius: 6px;
            font-size: 12px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Green Leaf ERP</h1>
            <p>Automated & On-Demand Database Backup</p>
        </div>
        <div class="content">
            <div class="alert-box">
                <p>✓ A full database backup archive is attached to this email.</p>
            </div>

            <table class="meta-table">
                <tr>
                    <td class="label">Backup File:</td>
                    <td class="value"><span class="badge">{{ $fileName }}</span></td>
                </tr>
                <tr>
                    <td class="label">Archive Size:</td>
                    <td class="value">{{ round($fileSizeBytes / 1024 / 1024, 2) > 0.01 ? round($fileSizeBytes / 1024 / 1024, 2) . ' MB' : round($fileSizeBytes / 1024, 2) . ' KB' }}</td>
                </tr>
                <tr>
                    <td class="label">Database:</td>
                    <td class="value">{{ $metadata['database_name'] ?? 'Primary Database' }} ({{ strtoupper($metadata['driver'] ?? 'SQL') }})</td>
                </tr>
                <tr>
                    <td class="label">Tables Included:</td>
                    <td class="value">{{ $metadata['table_count'] ?? 'All' }} tables</td>
                </tr>
                <tr>
                    <td class="label">Requested By:</td>
                    <td class="value">{{ $metadata['triggered_by'] ?? 'Administrator' }} {{ !empty($metadata['triggered_by_email']) ? '(' . $metadata['triggered_by_email'] . ')' : '' }}</td>
                </tr>
                <tr>
                    <td class="label">Generated At:</td>
                    <td class="value">{{ $metadata['created_at'] ?? now()->toDateTimeString() }}</td>
                </tr>
            </table>

            <p style="font-size: 12px; color: #64748b; line-height: 1.5; margin: 0;">
                <strong>Security Notice:</strong> This email contains sensitive database contents. Please store this backup file securely and do not forward this email to unauthorized recipients.
            </p>
        </div>
        <div class="footer">
            Green Leaf Fresh ERP &middot; Automated System Notification
        </div>
    </div>
</body>
</html>
