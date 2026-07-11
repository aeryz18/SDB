<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>DryBox AI Alert</title>
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

        {{-- Alert badge --}}
        <tr>
          <td style="background:#ffffff;padding:0 32px;">
            <div style="margin-top:28px;text-align:center;">
              @php
                $colors = [
                  'humidity_crit' => ['bg'=>'#fef2f2','border'=>'#fca5a5','text'=>'#dc2626','label'=>'CRITICAL HUMIDITY'],
                  'humidity_warn' => ['bg'=>'#fffbeb','border'=>'#fcd34d','text'=>'#d97706','label'=>'HIGH HUMIDITY'],
                  'temp'          => ['bg'=>'#fff7ed','border'=>'#fb923c','text'=>'#ea580c','label'=>'TEMPERATURE ALERT'],
                  'silica_due'      => ['bg'=>'#eff6ff','border'=>'#93c5fd','text'=>'#2563eb','label'=>'SILICA GEL DUE'],
                  'silica_upcoming' => ['bg'=>'#fffbeb','border'=>'#fcd34d','text'=>'#d97706','label'=>'SILICA GEL DUE SOON'],
                  'silica_drift'    => ['bg'=>'#f0fdfa','border'=>'#5eead4','text'=>'#0d9488','label'=>'SILICA GEL DRIFT DETECTED'],
                  'tamper'          => ['bg'=>'#fdf4ff','border'=>'#e879f9','text'=>'#a21caf','label'=>'SECURITY ALERT'],
                ];
                $c = $colors[$alert->type] ?? ['bg'=>'#f8fafc','border'=>'#cbd5e1','text'=>'#475569','label'=>strtoupper($alert->type)];
              @endphp
              <span style="display:inline-block;background:{{ $c['bg'] }};border:1px solid {{ $c['border'] }};color:{{ $c['text'] }};font-size:11px;font-weight:700;letter-spacing:1px;padding:6px 16px;border-radius:999px;">
                {{ $c['label'] }}
              </span>
            </div>
          </td>
        </tr>

        {{-- Body --}}
        <tr>
          <td style="background:#ffffff;padding:20px 32px 32px;">

            <p style="margin:20px 0 8px;font-size:16px;color:#0f172a;font-weight:600;text-align:center;">
              {{ $alert->message }}
            </p>

            {{-- Device info --}}
            <table width="100%" cellpadding="0" cellspacing="0" style="margin:24px 0;background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;">
              <tr>
                <td style="padding:16px 20px;border-bottom:1px solid #e2e8f0;">
                  <span style="color:#64748b;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Device</span><br>
                  <span style="color:#0f172a;font-size:14px;font-weight:500;">{{ $alert->device->name }}</span>
                  @if($alert->device->location)
                    <span style="color:#94a3b8;font-size:13px;"> — {{ $alert->device->location }}</span>
                  @endif
                </td>
              </tr>
              @if($alert->value !== null)
              <tr>
                <td style="padding:16px 20px;border-bottom:1px solid #e2e8f0;">
                  <span style="color:#64748b;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Measured Value</span><br>
                  <span style="color:{{ $c['text'] }};font-size:22px;font-weight:700;">
                    {{ number_format($alert->value, 1) }}{{ in_array($alert->type, ['humidity_warn','humidity_crit']) ? '%' : '°C' }}
                  </span>
                </td>
              </tr>
              @endif
              <tr>
                <td style="padding:16px 20px;">
                  <span style="color:#64748b;font-size:12px;font-weight:600;text-transform:uppercase;letter-spacing:.5px;">Time</span><br>
                  <span style="color:#0f172a;font-size:14px;">{{ $alert->created_at->format('D, d M Y  H:i:s') }}</span>
                </td>
              </tr>
            </table>

            {{-- CTA --}}
            <div style="text-align:center;margin:28px 0 8px;">
              <a href="{{ config('app.url') }}/dashboard"
                 style="display:inline-block;background:linear-gradient(135deg,#2b5bb5,#0061a4);color:#ffffff;font-size:14px;font-weight:600;padding:12px 28px;border-radius:8px;text-decoration:none;">
                View Dashboard
              </a>
            </div>

          </td>
        </tr>

        {{-- Footer --}}
        <tr>
          <td style="background:#f8fafc;border-top:1px solid #e2e8f0;border-radius:0 0 12px 12px;padding:20px 32px;text-align:center;">
            <p style="margin:0;color:#94a3b8;font-size:12px;">
              You're receiving this because your email is set as a notification recipient for <strong>{{ $alert->device->name }}</strong>.<br>
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
