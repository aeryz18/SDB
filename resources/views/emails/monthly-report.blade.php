<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DryBox AI Monthly Report</title>
</head>
<body style="margin:0;padding:0;background:#f1f5f9;font-family:'Segoe UI',Arial,sans-serif;">

<table width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:32px 16px;">
  <tr>
    <td align="center">
      <table width="600" cellpadding="0" cellspacing="0" style="max-width:600px;width:100%;">

        {{-- Header --}}
        <tr>
          <td style="background:#0d1b3e;border-radius:12px 12px 0 0;padding:28px 32px;text-align:center;">
            <span style="font-size:28px;font-weight:700;color:#ffffff;letter-spacing:-0.5px;">
              DryBox <span style="color:#60a5fa;">AI</span>
            </span>
            <p style="margin:6px 0 0;color:#94a3b8;font-size:13px;">Smart Dry Storage Monitoring</p>
          </td>
        </tr>

        {{-- Badge --}}
        <tr>
          <td style="background:#ffffff;padding:0 32px;">
            <div style="margin-top:28px;text-align:center;">
              <span style="display:inline-block;background:#eff6ff;border:1px solid #93c5fd;color:#2563eb;font-size:11px;font-weight:700;letter-spacing:1px;padding:6px 16px;border-radius:999px;">
                MONTHLY CONDITION REPORT
              </span>
            </div>
          </td>
        </tr>

        {{-- Body --}}
        <tr>
          <td style="background:#ffffff;padding:20px 32px 32px;">

            <p style="margin:20px 0 8px;font-size:16px;color:#0f172a;font-weight:600;text-align:center;">
              {{ $device->name }} — {{ $from->format('F Y') }}
            </p>
            <p style="margin:0;font-size:13px;color:#64748b;text-align:center;">
              Key stats for the month are below. Download the full sensor history and alerts as a CSV from the Analytics page on the web dashboard.
            </p>

            <table width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
              <tr>
                <td style="padding:16px 20px;border-bottom:1px solid #e2e8f0;">
                  <span style="color:#64748b;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Device</span><br>
                  <span style="color:#0f172a;font-size:14px;font-weight:500;">{{ $device->name }}</span>
                  @if($device->location)
                    <span style="color:#94a3b8;font-size:13px;"> — {{ $device->location }}</span>
                  @endif
                </td>
              </tr>
              <tr>
                <td style="padding:16px 20px;">
                  <span style="color:#64748b;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Period</span><br>
                  <span style="color:#0f172a;font-size:14px;">{{ $from->format('d M Y') }} – {{ $to->format('d M Y') }}</span>
                </td>
              </tr>
            </table>

            <table width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
              <tr>
                <td style="padding:14px 10px;width:50%;text-align:center;border-right:1px solid #e2e8f0;border-bottom:1px solid #e2e8f0;">
                  <span style="color:#64748b;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Avg Temperature</span><br>
                  <span style="color:#0f172a;font-size:18px;font-weight:700;">{{ $stats['avg_temp'] ?? '—' }}{{ $stats['avg_temp'] !== null ? '°C' : '' }}</span>
                </td>
                <td style="padding:14px 10px;width:50%;text-align:center;border-bottom:1px solid #e2e8f0;">
                  <span style="color:#64748b;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Peak Temperature</span><br>
                  <span style="color:#0f172a;font-size:18px;font-weight:700;">{{ $stats['peak_temp'] ?? '—' }}{{ $stats['peak_temp'] !== null ? '°C' : '' }}</span>
                </td>
              </tr>
              <tr>
                <td style="padding:14px 10px;width:50%;text-align:center;border-right:1px solid #e2e8f0;">
                  <span style="color:#64748b;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Avg Humidity</span><br>
                  <span style="color:#0f172a;font-size:18px;font-weight:700;">{{ $stats['avg_humidity'] ?? '—' }}{{ $stats['avg_humidity'] !== null ? '%' : '' }}</span>
                </td>
                <td style="padding:14px 10px;width:50%;text-align:center;">
                  <span style="color:#64748b;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Peak Humidity</span><br>
                  <span style="color:#0f172a;font-size:18px;font-weight:700;">{{ $stats['peak_humidity'] ?? '—' }}{{ $stats['peak_humidity'] !== null ? '%' : '' }}</span>
                </td>
              </tr>
              <tr>
                <td colspan="2" style="padding:14px 10px;text-align:center;border-top:1px solid #e2e8f0;">
                  <span style="color:#64748b;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Avg Door Opens / Day</span><br>
                  <span style="color:#0f172a;font-size:18px;font-weight:700;">{{ $stats['avg_door_opens_per_day'] }}</span>
                </td>
              </tr>
            </table>

            <div style="text-align:center;margin:28px 0 8px;">
              <a href="{{ config('app.url') }}/analytics"
                 style="display:inline-block;background:linear-gradient(135deg,#2b5bb5,#0061a4);color:#ffffff;font-size:14px;font-weight:600;padding:12px 28px;border-radius:8px;text-decoration:none;">
                View Analytics
              </a>
            </div>

          </td>
        </tr>

        {{-- Footer --}}
        <tr>
          <td style="background:#f8fafc;border-top:1px solid #e2e8f0;border-radius:0 0 12px 12px;padding:20px 32px;text-align:center;">
            <p style="margin:0;color:#94a3b8;font-size:12px;">
              You're receiving this because your email is set as a notification recipient for <strong>{{ $device->name }}</strong>.<br>
              DryBox AI — Smart Dry Storage Monitoring
            </p>
          </td>
        </tr>

      </table>
    </td>
  </tr>
</table>

</body>
</html>
