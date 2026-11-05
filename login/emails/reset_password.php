<!DOCTYPE html>
<html lang="uz">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>{{TENANT_NAME}} — Parolni tiklash</title>
</head>
<body style="margin:0;padding:0;background-color:{{PRIMARY_COLOR}};font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background-color:{{PRIMARY_COLOR}};padding:40px 20px;">
<tr><td align="center">
<table width="600" cellpadding="0" cellspacing="0" style="background-color:{{SECONDARY_COLOR}};border-radius:12px;border:1px solid rgba(100,255,218,0.15);overflow:hidden;">
  <tr><td style="background-color:{{SECONDARY_COLOR}};padding:32px 40px 24px;text-align:center;border-bottom:1px solid rgba(100,255,218,0.1);">
    <h1 style="color:{{ACCENT_COLOR}};font-size:24px;margin:0 0 4px;">{{TENANT_NAME}}</h1>
    <p style="color:#8892b0;font-size:13px;margin:0;">Parolni tiklash</p>
  </td></tr>
  <tr><td style="padding:32px 40px;">
    <p style="color:#ccd6f6;font-size:15px;line-height:1.6;margin:0 0 20px;">Assalomu alikum <strong style="color:#e6f1ff;">{{NAME}}</strong>,</p>
    <p style="color:#ccd6f6;font-size:15px;line-height:1.6;margin:0 0 28px;">Sizning parolni tiklash kodingiz:</p>
    <table width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:0 0 28px;">
      <div style="background-color:rgba(100,255,218,0.08);border:2px dashed rgba(100,255,218,0.3);border-radius:10px;padding:18px 0;display:inline-block;min-width:280px;">
        <span style="color:{{ACCENT_COLOR}};font-size:32px;font-weight:700;letter-spacing:10px;font-family:'Courier New',monospace;">{{CODE}}</span>
      </div>
    </td></tr></table>
    <p style="color:#8892b0;font-size:13px;line-height:1.5;margin:0 0 8px;">Bu kod <strong style="color:#ccd6f6;">{{MINUTES}} daqiqa</strong> davomida amal qiladi.</p>
    <p style="color:#8892b0;font-size:13px;line-height:1.5;margin:0 0 16px;">Agar siz parolni tiklashni so'ramagan bo'lsangiz, bu xabarni e'tiborsiz qoldiring. Sizning parolingiz o'zgarmaydi.</p>
    <div style="background-color:rgba(239,68,68,0.08);border:1px solid rgba(239,68,68,0.15);border-radius:6px;padding:12px 16px;margin:0;">
      <p style="color:#ef4444;font-size:13px;margin:0;">⚠ Agar siz bu so'rovni yubormagan bo'lsangiz, darhol biz bilan bog'laning.</p>
    </div>
  </td></tr>
  <tr><td style="background-color:rgba(100,255,218,0.04);padding:20px 40px;text-align:center;border-top:1px solid rgba(100,255,218,0.1);">
    <p style="color:#5a6580;font-size:12px;margin:0;">© <?= date('Y') ?> {{TENANT_NAME}}. Barcha huquqlar himoyalangan.</p>
  </td></tr>
</table>
</td></tr>
</table>
</body>
</html>
