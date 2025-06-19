<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
    "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title>@yield('title', 'Email from ' . config('app.name'))</title>
    <style type="text/css">
        /* Reset styles */
        body, table, td, p, a, li, blockquote {
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }

        table, td {
            mso-table-lspace: 0pt;
            mso-table-rspace: 0pt;
        }

        img {
            -ms-interpolation-mode: bicubic;
            border: 0;
            height: auto;
            line-height: 100%;
            outline: none;
            text-decoration: none;
        }

        /* Main styles */
        body {
            height: 100% !important;
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            background-color: #f4f4f4;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
        }

        .email-header {
            background-color: #2563eb;
            padding: 20px;
            text-align: center;
        }

        .email-header h1 {
            color: #ffffff;
            margin: 0;
            font-size: 24px;
            font-weight: 600;
        }

        .email-body {
            padding: 30px 20px;
            line-height: 1.6;
            color: #374151;
        }

        .email-footer {
            background-color: #f9fafb;
            padding: 20px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }

        .email-footer p {
            margin: 0;
            font-size: 14px;
            color: #6b7280;
        }

        .btn {
            display: inline-block;
            padding: 12px 24px;
            background-color: #2563eb;
            color: #ffffff !important;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            margin: 15px 0;
        }

        .btn:hover {
            background-color: #1d4ed8;
        }

        .btn-secondary {
            background-color: #6b7280;
        }

        .btn-secondary:hover {
            background-color: #4b5563;
        }

        .order-summary {
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .order-item:last-child {
            border-bottom: none;
            font-weight: 600;
            font-size: 16px;
        }

        .status-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .status-approved {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-processing {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-completed {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .highlight-box {
            background-color: #eff6ff;
            border-left: 4px solid #2563eb;
            padding: 15px;
            margin: 20px 0;
        }

        /* Mobile responsive */
        @media screen and (max-width: 600px) {
            .email-container {
                width: 100% !important;
            }

            .email-body {
                padding: 20px 15px !important;
            }

            .order-item {
                flex-direction: column;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
<table border="0" cellpadding="0" cellspacing="0" width="100%">
    <tr>
        <td align="center">
            <div class="email-container">
                <!-- Header -->
                <div class="email-header">
                    <h1>{{ config('app.name') }}</h1>
                </div>

                <!-- Body -->
                <div class="email-body">
                    @yield('content')
                </div>

                <!-- Footer -->
                <div class="email-footer">
                    <p>Thank you for choosing {{ config('app.name') }}</p>
                    <p>If you have any questions, please contact our support team.</p>
                    <p>&copy; {{ date('Y') }} {{ config('app.name') }}. All rights reserved.</p>
                </div>
            </div>
        </td>
    </tr>
</table>
</body>
</html>
