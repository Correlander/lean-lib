@if (($showContributionMessage ?? true) && !$hasContributions)
    <div class="alert alert-info" role="status">
        No enabled plugins have contributed To-do tabs or sidebar sections yet. Contributions will appear here when plugins register them with the Library.
    </div>
@endif

@if (!empty($guiSurfaces))
    @if (!empty($editorTitle))
        <header class="leantimelib-gui-editor-heading">
            <h3>{{ $editorTitle }}</h3>
            @if (!empty($editorDescription)) <p>{{ $editorDescription }}</p> @endif
        </header>
    @endif
    <label class="lt-library-editor-selector">Editing
        <select aria-label="Choose interface area to customize" data-gui-surface-selector>
            @foreach ($guiSurfaces as $index => $surface)
                <option value="{{ $surface['id'] }}" @if ($index === 0) selected @endif>{{ $surface['label'] }}</option>
            @endforeach
        </select>
    </label>
    @foreach ($guiSurfaces as $index => $surface)
        <section class="lt-library-gui-surface" data-gui-surface="{{ $surface['id'] }}" @if ($index !== 0) hidden @endif>
            @if ($surface['description'] !== '')
                <p class="lt-library-workspace__hint">{{ $surface['description'] }}</p>
            @endif
            {!! $surface['editorHtml'] !!}
        </section>
    @endforeach
@else
    <div class="alert alert-info" role="status">No enabled plugins have registered an editable GUI surface yet.</div>
@endif
