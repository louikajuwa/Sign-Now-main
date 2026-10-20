<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta http-equiv="X-UA-Compatible" content="IE=edge"/>
<title>Sign-Now - Document Completed</title>
<!--[if mso]>
<noscript>
<xml>
<o:OfficeDocumentSettings>
<o:PixelsPerInch>96</o:PixelsPerInch>
</o:OfficeDocumentSettings>
</xml>
</noscript>
<![endif]-->
<style type="text/css">
@import url('https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Sans:wght@300;400;500&display=swap');
body, table, td, p, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
img { -ms-interpolation-mode: bicubic; border: 0; outline: none; text-decoration: none; }
body { margin: 0 !important; padding: 0 !important; background-color: #f0f2f5; }
a[x-apple-data-detectors] { color: inherit !important; text-decoration: none !important; }
</style>
</head>
<body style="margin:0;padding:0;background-color:#f0f2f5;font-family:'DM Sans',Arial,sans-serif;">

<table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f0f2f5;">
  <tr>
    <td align="center" style="padding:32px 16px;">

      <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="600" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,0.12);">

        {{-- HEADER --}}
        <tr>
          <td bgcolor="#0d2060" style="background-color:#0b1340;background:linear-gradient(135deg,#0b1340 0%,#0d2060 60%,#0f3080 100%);padding:0;">
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
              <tr>
                <td align="left" valign="middle" bgcolor="#0d2060" style="padding:22px 28px 18px;">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td valign="middle" style="padding-right:14px;">
                        <img src="{{ asset('favicon.ico') }}" width="48" height="48" alt="Sign-Now" style="display:block;width:48px;height:48px;border:0;outline:none;border-radius:10px;" />
                      </td>
                      <td valign="middle">
                        <div style="font-family:'Syne',Arial,sans-serif;font-size:22px;font-weight:800;color:#ffffff;letter-spacing:-0.5px;line-height:1.2;margin-bottom:2px;">Ring<span style="color:#00c8ff;">Q</span></div>
                        <div style="font-size:10px;color:rgba(255,255,255,0.55);font-weight:300;letter-spacing:0.8px;">Customer Centric Solutions</div>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
              <tr>
                <td style="background-color:#091030;padding:13px 36px;">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0">
                    <tr>
                      <td width="8" height="8" bgcolor="#16a34a" style="width:8px;height:8px;background-color:#16a34a;border-radius:4px;vertical-align:middle;" valign="middle">&nbsp;</td>
                      <td width="10" style="width:10px;">&nbsp;</td>
                      <td style="font-family:'Syne',Arial,sans-serif;font-size:11px;font-weight:600;color:#e2e8f0;letter-spacing:2.5px;text-transform:uppercase;vertical-align:middle;" valign="middle">Document Completed</td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        {{-- BODY --}}
        <tr>
          <td style="background-color:#fafbff;padding:40px 36px 36px;">

            <p style="font-family:'Syne',Arial,sans-serif;font-size:22px;font-weight:700;color:#0d1640;margin:0 0 8px 0;padding:0;">
              Hi, <span style="color:#0055ff;">{{ $recipientEmail }}</span>
            </p>
            <p style="font-size:14px;line-height:1.75;color:#4a4f72;margin:0 0 32px 0;padding:0;font-weight:400;">
              Your document has been completed and finalized. Please find the signed document attached to this email.
            </p>

            {{-- Document Details Card --}}
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#ffffff;border:1px solid #e4e8f8;border-radius:16px;overflow:hidden;margin-bottom:28px;">
              <tr>
                <td style="background-color:#0b1340;background:linear-gradient(90deg,#0b1340 0%,#0d2060 100%);padding:14px 24px;">
                  <span style="font-family:'Syne',Arial,sans-serif;font-size:13px;font-weight:600;color:#ffffff;letter-spacing:0.5px;">Document Details</span>
                </td>
              </tr>
              <tr>
                <td style="padding:0;border-bottom:1px solid #f0f2fc;">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                      <td width="110" style="width:110px;padding:14px 0 14px 24px;vertical-align:middle;" valign="middle">
                        <span style="font-size:11px;font-weight:600;color:#9fa5cc;letter-spacing:1.2px;text-transform:uppercase;">Document</span>
                      </td>
                      <td style="padding:14px 24px 14px 0;vertical-align:middle;" valign="middle">
                        <span style="display:inline-block;background-color:#eef2ff;border:1px solid #c7d2fe;border-radius:6px;padding:3px 10px;font-size:12px;font-weight:600;color:#3730a3;font-family:'DM Sans',Arial,monospace;">{{ $docTitle }}</span>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
              <tr>
                <td style="padding:0;border-bottom:1px solid #f0f2fc;">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                      <td width="110" style="width:110px;padding:14px 0 14px 24px;vertical-align:middle;" valign="middle">
                        <span style="font-size:11px;font-weight:600;color:#9fa5cc;letter-spacing:1.2px;text-transform:uppercase;">Status</span>
                      </td>
                      <td style="padding:14px 24px 14px 0;vertical-align:middle;" valign="middle">
                        <span style="display:inline-block;background-color:#dcfce7;border:1px solid #86efac;border-radius:6px;padding:3px 10px;font-size:12px;font-weight:600;color:#166534;">Completed</span>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
              <tr>
                <td style="padding:0;">
                  <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%">
                    <tr>
                      <td width="110" style="width:110px;padding:14px 0 14px 24px;vertical-align:middle;" valign="middle">
                        <span style="font-size:11px;font-weight:600;color:#9fa5cc;letter-spacing:1.2px;text-transform:uppercase;">Recipient</span>
                      </td>
                      <td style="padding:14px 24px 14px 0;vertical-align:middle;" valign="middle">
                        <span style="font-size:14px;font-weight:500;color:#0055ff;">{{ $recipientEmail }}</span>
                      </td>
                    </tr>
                  </table>
                </td>
              </tr>
            </table>

            {{-- Notice --}}
            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="background-color:#f0fdf4;border:1px solid #bbf7d0;border-left:4px solid #16a34a;border-radius:10px;margin-bottom:28px;">
              <tr>
                <td style="padding:14px 18px;">
                  <p style="font-size:13px;color:#166534;line-height:1.55;margin:0;padding:0;">
                    &#9989;&nbsp; This document has been <strong style="font-weight:700;">fully signed and finalized</strong>. All parties have completed their signatures.
                  </p>
                </td>
              </tr>
            </table>

            <table role="presentation" cellpadding="0" cellspacing="0" border="0" width="100%" style="margin-bottom:24px;">
              <tr>
                <td style="border-top:1px solid #e4e8f8;font-size:0;line-height:0;">&nbsp;</td>
              </tr>
            </table>

            <p style="font-size:14px;color:#4a4f72;line-height:1.7;margin:0;padding:0;">
              Best regards,<br>
              <strong style="font-family:'Syne',Arial,sans-serif;font-weight:700;color:#0d1640;font-size:15px;">The Sign-Now Team</strong>
            </p>

          </td>
        </tr>

        {{-- FOOTER --}}
        <tr>
          <td style="background-color:#1e293b;padding:24px 36px;text-align:center;" align="center">
            <p style="font-size:12px;color:#cbd5e1;line-height:1.6;margin:0 0 10px 0;padding:0;">
              This is an automated notification from the Sign-Now system.<br>Please do not reply directly to this email.
            </p>
            <p style="font-size:11px;color:#94a3b8;margin:0 0 8px 0;padding:0;">
              If you believe you received this email in error, please
              <a href="mailto:{{ config('mail.from.address') }}" style="color:#00c8ff;text-decoration:none;">contact us</a>.
            </p>
            <p style="font-size:11px;color:#64748b;margin:0;padding:0;letter-spacing:0.3px;">
              &copy; {{ date('Y') }} Sign-Now. All Rights Reserved.
            </p>
          </td>
        </tr>

      </table>

    </td>
  </tr>
</table>

</body>
</html>
