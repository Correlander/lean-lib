<section class="leantimelib-shared-settings {{ $definition['sectionClass'] }}" data-settings-api-version="2">
    <header class="leantimelib-shared-settings__header {{ $definition['headerClass'] }}">
        <div class="leantimelib-shared-settings__header-copy {{ $definition['headerCopyClass'] }}">
            <h1>{{ $definition['title'] }}</h1>
            <p>{{ $definition['description'] !== '' ? $definition['description'] : 'Description not specified' }}</p>
        </div>
        <aside class="leantimelib-shared-settings__identity">
            <div class="leantimelib-shared-settings__actions">
                @if ($definition['supportUrl'])
                    <a class="leantimelib-shared-settings__support" href="{{ $definition['supportUrl'] }}" target="_blank" rel="noopener noreferrer"><i class="fa-solid fa-circle-question" aria-hidden="true"></i><span>Support</span></a>
                @else
                    <span class="leantimelib-shared-settings__support leantimelib-shared-settings__placeholder" aria-disabled="true"><i class="fa-solid fa-circle-question" aria-hidden="true"></i><span>No Support Link Defined</span></span>
                @endif
                @if ($definition['contributionsUrl'])
                    <a class="leantimelib-shared-settings__contributions" href="{{ $definition['contributionsUrl'] }}" target="_blank" rel="noopener noreferrer" aria-label="Contributions and donations for {{ $definition['title'] }}" title="Contributions"><i class="fa-brands fa-github" aria-hidden="true"></i></a>
                @else
                    <span class="leantimelib-shared-settings__contributions leantimelib-shared-settings__contributions--missing leantimelib-shared-settings__placeholder">No Contributions Link Defined</span>
                @endif
            </div>
            <hr>
            <div class="leantimelib-shared-settings__metadata">
                <div class="leantimelib-shared-settings__metadata-primary">
                    <div class="leantimelib-shared-settings__authors">
                        @if ($definition['authors'] !== [])
                            <span>By
                                @foreach ($definition['authors'] as $index => $author)
                                    @if ($index > 0), @endif
                                    @if ($author['homepage'])<a href="{{ $author['homepage'] }}" target="_blank" rel="noopener noreferrer">{{ $author['name'] }}</a>@else{{ $author['name'] }}@endif
                                @endforeach
                            </span>
                        @else
                            <span>Author not specified</span>
                        @endif
                    </div>
                    <div class="leantimelib-shared-settings__emails">
                        @if ($definition['emails'] !== [])
                            <span>{{ count($definition['emails']) > 1 ? 'Emails: ' : 'Email: ' }}</span>
                            @foreach ($definition['emails'] as $index => $email)
                                @if ($index > 0), @endif<a href="mailto:{{ $email }}">{{ $email }}</a>
                            @endforeach
                        @else
                            <span>No author email defined</span>
                        @endif
                    </div>
                </div>
                <div class="leantimelib-shared-settings__metadata-secondary">
                    @if ($definition['version'] !== '')
                        @if ($definition['sourceUrl'])
                            <a href="{{ $definition['sourceUrl'] }}" target="_blank" rel="noopener noreferrer">Version {{ $definition['version'] }}</a>
                        @else
                            <span>Version {{ $definition['version'] }}</span>
                        @endif
                    @else
                        <span>Version not specified</span>
                    @endif
                    <span>{{ $definition['license'] !== '' ? 'License: '.$definition['license'] : 'License not specified' }}</span>
                    @if (! $definition['sourceUrl'])
                        <span class="leantimelib-shared-settings__placeholder">No source link defined</span>
                    @endif
                </div>
            </div>
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

    @if ($definition['footerAction'])
        <footer class="leantimelib-shared-settings__footer">
            <hr>
            <div class="leantimelib-shared-settings__footer-inner">
                <button type="button" class="lt-library-update-check" data-plugin-metadata-sync data-endpoint="{{ $definition['footerAction']['endpoint'] }}" data-csrf="{{ $definition['footerAction']['csrfToken'] }}" title="Refresh stored plugin metadata from installed composer.json files. Does not download or update plugin code.">
                    <span>{{ $definition['footerAction']['label'] }}</span><i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </button>
                <p class="lt-library-update-check__status" data-plugin-metadata-status role="status" aria-live="polite" hidden></p>
            </div>
        </footer>
    @endif
</section>
