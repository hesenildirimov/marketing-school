{{-- @include('partials.field', ['name' => 'email', 'type' => 'email', 'label' => ..., 'attrs' => '...']) --}}
<label for="f-{{ $name }}">{{ $label }}</label>
<input id="f-{{ $name }}" name="{{ $name }}" type="{{ $type ?? 'text' }}" @if(($type ?? 'text') !== 'password') value="{{ old($name, $value ?? '') }}" @endif {!! $attrs ?? '' !!} @error($name) aria-invalid="true" aria-describedby="e-{{ $name }}" @enderror>
@error($name)<p class="err" id="e-{{ $name }}">{{ $message }}</p>@enderror
