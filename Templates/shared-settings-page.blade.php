<section class="leantimelib-shared-settings {{ $definition['sectionClass'] }}" data-settings-api-version="2">
    <header class="leantimelib-shared-settings__header {{ $definition['headerClass'] }}">
        <div class="leantimelib-shared-settings__header-copy {{ $definition['headerCopyClass'] }}">
            <h1>{{ $definition['title'] }}</h1>
            <p>{{ $definition['description'] !== '' ? $definition['description'] : 'Description not specified' }}</p>
        </div>
        <aside class="leantimelib-shared-settings__identity">
            {!! $pluginMetadataHtml !!}
        </aside>
    </header>

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
        @elseif ($block['type'] === 'alert')
            <div class="alert alert-{{ $block['tone'] ?? 'danger' }}" role="alert">{{ $block['text'] }}</div>
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

    @if ($definition['footer'] || $definition['footerAction'])
        <footer class="leantimelib-shared-settings__footer">
            <hr>
            <div class="leantimelib-shared-settings__footer-inner">
                @if ($definition['footer'])
                    @if ($definition['footer']['autosave'])
                        <p class="lt-library-autosave-status" data-autosave-status role="status" aria-live="polite">Changes automatically saved.</p>
                    @else
                        <button class="btn btn-primary" type="submit">{{ $definition['footer']['submitLabel'] }}</button>
                    @endif
                @endif
                @if ($definition['footerAction'])
                    <div class="leantimelib-shared-settings__footer-action">
                        <button type="button" class="lt-library-update-check" data-plugin-metadata-sync data-endpoint="{{ $definition['footerAction']['endpoint'] }}" data-csrf="{{ $definition['footerAction']['csrfToken'] }}" title="Refresh stored plugin metadata from installed composer.json files. Does not download or update plugin code.">
                            <span>{{ $definition['footerAction']['label'] }}</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                        </button>
                        <p class="lt-library-update-check__status" data-plugin-metadata-status role="status" aria-live="polite" hidden></p>
                    </div>
                @endif
            </div>
        </footer>
    @endif
</section>
