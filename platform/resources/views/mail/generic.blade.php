<!DOCTYPE html>
<html lang="{{ $lang }}"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head>
<body style="margin:0;background:#FFFBE8;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#0E1324">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#fff;border:1px solid #E6EAF3;border-radius:14px">
<tr><td style="padding:22px 26px 6px;font-weight:700;font-size:17px"><span style="display:inline-block;background:#FFC400;border-radius:6px;padding:2px 7px;margin-right:6px">M</span>Marketing School</td></tr>
<tr><td style="padding:10px 26px 24px;font-size:15px;line-height:1.55">
<p style="margin:0 0 12px">{{ $greeting }}</p>
@foreach($lines as $line)<p style="margin:0 0 12px">{{ $line }}</p>@endforeach
@if($actionUrl)<p style="margin:20px 0"><a href="{{ $actionUrl }}" style="display:inline-block;background:#FFC400;color:#0E1324;text-decoration:none;font-weight:700;padding:11px 20px;border-radius:10px">{{ $actionLabel }}</a></p>
<p style="margin:0 0 12px;font-size:12px;color:#5F6783;word-break:break-all">{{ $actionUrl }}</p>@endif
</td></tr></table>
<p style="font-size:12px;color:#5F6783;max-width:560px">{{ $footer }}</p>
</td></tr></table></body></html>
