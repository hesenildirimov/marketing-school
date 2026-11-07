{{-- 3 dilli sahə: name, label, value (array), textarea --}}
<fieldset class="tri"><legend>{{ $label }}</legend>
@foreach(['az', 'en', 'ru'] as $l)
  <label for="f-{{ $name }}-{{ $l }}" class="small">{{ strtoupper($l) }}</label>
  @if($textarea ?? false)<textarea id="f-{{ $name }}-{{ $l }}" name="{{ $name }}_{{ $l }}" rows="3" @if($l === 'az' && ($required ?? false)) required @endif>{{ old($name.'_'.$l, $value[$l] ?? '') }}</textarea>
  @else<input type="text" id="f-{{ $name }}-{{ $l }}" name="{{ $name }}_{{ $l }}" value="{{ old($name.'_'.$l, $value[$l] ?? '') }}" @if($l === 'az' && ($required ?? false)) required @endif maxlength="200">@endif
@endforeach
</fieldset>
