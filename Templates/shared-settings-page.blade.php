<section class="leantimelib-shared-settings {{ $definition['sectionClass'] }}" data-settings-api-version="1">
    <header class="leantimelib-shared-settings__header {{ $definition['headerClass'] }}">
        <div class="leantimelib-shared-settings__header-copy {{ $definition['headerCopyClass'] }}">
            <h1>{{ $definition['title'] }}</h1>
            @if ($definition['description'] !== '')
                <p>{{ $definition['description'] }}</p>
            @endif
        </div>
        @if ($definition['headerActions'])
            <div class="leantimelib-shared-settings__header-actions">{!! ($definition['headerActions'])() !!}</div>
        @elseif ($definition['supportUrl'])
            <a href="{{ $definition['supportUrl'] }}" target="_blank" rel="noopener noreferrer">Support</a>
        @endif
    </header>
    @if ($definition['author'] !== '' || $definition['version'] !== '' || $definition['homepage'] !== '')
        <footer class="leantimelib-shared-settings__metadata">
            @if ($definition['author'] !== '') <span>By {{ $definition['author'] }}</span> @endif
            @if ($definition['version'] !== '') <span>Version {{ $definition['version'] }}</span> @endif
            @if ($definition['homepage'] !== '') <a href="{{ $definition['homepage'] }}" target="_blank" rel="noopener noreferrer">Project home</a> @endif
        </footer>
    @endif

    @foreach ($definition['blocks'] as $block)
        @if ($block['type'] === 'section')
            <section class="leantimelib-shared-settings__section">
                <h2 class="{{ $block['class'] ?? '' }}">{{ $block['text'] ?? '' }}</h2>
                @if (!empty($block['description'])) <p class="{{ $block['class'] ?? '' }}">{{ $block['description'] }}</p> @endif
            </section>
        @elseif ($block['type'] === 'title')
            <h2 class="leantimelib-shared-settings__title {{ $block['class'] ?? '' }}">{{ $block['text'] ?? '' }}</h2>
        @elseif ($block['type'] === 'description')
            <p class="leantimelib-shared-settings__description {{ $block['class'] ?? '' }}">{{ $block['text'] ?? '' }}</p>
        @elseif ($block['type'] === 'custom')
            {!! ($block['render'])($values, $errors) !!}
        @elseif ($block['type'] === 'action')
            <a class="button" href="{{ $block['actionUrl'] }}">{{ $block['label'] }}</a>
        @else
            @php
                $id = $block['id'];
                $hasError = array_key_exists($id, $errors);
                $error = $hasError ? (is_array($errors[$id]) ? reset($errors[$id]) : $errors[$id]) : null;
                $rawValue = $values[$id] ?? ($block['default'] ?? '');
                $value = is_scalar($rawValue) ? $rawValue : '';
            @endphp
            <div class="leantimelib-shared-settings__field leantimelib-shared-settings__field--{{ $block['type'] }}" data-field-type="{{ $block['type'] }}">
                @if ($block['type'] === 'checkbox')
                    <label class="{{ $block['class'] ?? '' }}">
                        <input type="checkbox" name="{{ $block['name'] ?? $id }}" value="1" @if (filter_var($value, FILTER_VALIDATE_BOOLEAN)) checked @endif>
                        <span><strong>{{ $block['label'] }}</strong>@if (!empty($block['help'])) <small>{{ $block['help'] }}</small>@endif</span>
                    </label>
                @else
                    <label for="settings-{{ $id }}">{{ $block['label'] }}</label>
                    @if ($block['type'] === 'select')
                        <select id="settings-{{ $id }}" name="{{ $block['name'] ?? $id }}">
                            @foreach ($block['options'] as $optionValue => $optionLabel)
                                <option value="{{ $optionValue }}" @if ((string) $value === (string) $optionValue) selected @endif>{{ $optionLabel }}</option>
                            @endforeach
                        </select>
                    @else
                        <input
                            id="settings-{{ $id }}"
                            type="{{ $block['type'] === 'secret' ? 'password' : $block['type'] }}"
                            name="{{ $block['name'] ?? $id }}"
                            value="{{ $block['type'] === 'secret' ? '' : $value }}"
                            @if (isset($block['placeholder'])) placeholder="{{ $block['placeholder'] }}" @endif
                            @if (isset($block['min'])) min="{{ $block['min'] }}" @endif
                            @if (isset($block['max'])) max="{{ $block['max'] }}" @endif
                            @if (isset($block['maxlength'])) maxlength="{{ $block['maxlength'] }}" @endif
                            @if (!empty($block['required'])) required @endif
                            @if (isset($block['autocomplete'])) autocomplete="{{ $block['autocomplete'] }}" @endif
                            @if (!empty($block['readonly'])) readonly @endif
                            @if ($block['type'] === 'secret' && !empty($values[$id.'Configured'])) placeholder="A value is already saved; leave blank to keep it or enter a new value" @endif
                        >
                    @endif
                    @if (!empty($block['help'])) <small>{{ $block['help'] }}</small> @endif
                @endif
                @if ($error) <small class="text-danger" role="alert">{{ $error }}</small> @endif
            </div>
        @endif
    @endforeach
</section>
