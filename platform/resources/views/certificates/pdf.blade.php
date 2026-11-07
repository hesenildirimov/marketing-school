<!DOCTYPE html>
<html lang="az"><head><meta charset="utf-8">
<style>
  @page { margin: 0; }
  body { font-family: "DejaVu Sans", sans-serif; color: #0E1324; margin: 0; }
  .frame { margin: 28px; border: 6px solid #FFC400; padding: 40px 56px; height: 470px; position: relative; background: #FFFBE8; }
  .brand { font-weight: bold; font-size: 18px; letter-spacing: 1px; }
  .brand span { background: #FFC400; padding: 2px 8px; margin-right: 6px; }
  h1 { font-size: 46px; letter-spacing: 8px; margin: 26px 0 8px; }
  .name { font-size: 32px; font-weight: bold; margin: 10px 0; border-bottom: 2px solid #FFC400; display: inline-block; padding-bottom: 4px; }
  .course { font-size: 22px; font-weight: bold; margin: 8px 0 18px; }
  .meta { font-size: 12px; color: #434B61; }
  table.meta td { padding: 3px 18px 3px 0; }
  .foot { position: absolute; bottom: 30px; left: 56px; right: 56px; font-size: 10px; color: #5F6783; }
</style></head><body>
<div class="frame">
  <div class="brand"><span>M</span>MARKETING SCHOOL</div>
  <h1>{{ __('assess.pdf_title', [], 'az') }}</h1>
  <div class="meta">{{ __('assess.pdf_text', [], 'az') }} / {{ __('assess.pdf_text', [], 'en') }}</div>
  <div class="name">{{ $s['name'] }}</div>
  <div class="meta">{{ __('assess.pdf_completed', [], 'az') }}</div>
  <div class="course">{{ tr_field($s['course'], 'az') }}@if(! empty($s['course']['en'])) / {{ $s['course']['en'] }}@endif</div>
  <table class="meta">
    <tr><td>{{ __('assess.pdf_result', [], 'az') }}:</td><td><b>{{ $s['earned'] }} / {{ $s['possible'] }} ({{ $s['percent'] }}%)</b></td></tr>
    <tr><td>{{ __('assess.pdf_instructor', [], 'az') }}:</td><td>{{ implode(', ', $s['instructors']) ?: '—' }}</td></tr>
    <tr><td>{{ __('assess.pdf_date', [], 'az') }}:</td><td>{{ $s['date'] }}</td></tr>
    <tr><td>{{ __('assess.pdf_id', [], 'az') }}:</td><td>{{ $c->verify_id }}</td></tr>
  </table>
  <div class="foot">{{ $url }}</div>
</div>
</body></html>
