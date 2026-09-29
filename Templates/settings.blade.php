@extends($layout)

@section('content')
    <div class="maincontent">
        <div class="maincontentinner">
            <h1>Leantime Library</h1>
            <p>Arrange the native To-do tabs and sidebar sections together with contributions from enabled plugins. Enable or hide plugin contributions here; provider plugins supply their content while the Library owns placement and ordering.</p>

            @if (!empty($error))
                <div class="alert alert-danger" role="alert">{{ $error }}</div>
            @endif

            @php
                $pluginTabs = array_filter($tabs, static fn ($tab) => !$tab['builtin']);
                $pluginSections = array_filter($sections, static fn ($section) => !$section['builtin']);
            @endphp
            @if (count($pluginTabs) === 0 && count($pluginSections) === 0)
                <div class="alert alert-info" role="status">
                    No enabled plugins have contributed To-do tabs or sidebar sections yet. Contributions will appear here when plugins register them with the Library.
                </div>
            @endif

            <form method="post" action="{{ BASE_URL }}/LeantimeLib/settings">
                @csrf
                <h2>To-do tabs</h2>
                <p>Drag to set the order of Details, Files, Time Tracking, and plugin tabs. Core tabs stay enabled; plugin tabs can be hidden here.</p>
                <ol class="lt-library-sortable" data-library-sortable>
                    @foreach ($tabs as $tab)
                        <li class="lt-library-sortable__item" draggable="true" data-order-id="{{ $tab['id'] }}">
                            <input type="hidden" name="tabOrder[]" value="{{ $tab['id'] }}">
                            <span class="lt-library-sortable__handle" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span>
                            @if ($tab['icon'] !== '') <i class="{{ $tab['icon'] }}" aria-hidden="true"></i> @endif
                            <span class="lt-library-sortable__label">{{ $tab['label'] }}</span>
                            @if ($tab['builtin'])
                                <span class="lt-library-sortable__status">Leantime tab</span>
                            @else
                                <label class="lt-library-sortable__toggle">
                                    <input type="checkbox" name="tabEnabled[]" value="{{ $tab['id'] }}" @if ($tab['enabled']) checked @endif>
                                    Show in To-do modal
                                </label>
                            @endif
                            <div class="lt-library-sortable__controls">
                                <button type="button" class="btn btn-default" data-move="up" aria-label="Move {{ $tab['label'] }} up">↑</button>
                                <button type="button" class="btn btn-default" data-move="down" aria-label="Move {{ $tab['label'] }} down">↓</button>
                            </div>
                        </li>
                    @endforeach
                </ol>

                <h2>To-do details sidebar</h2>
                <p>Drag Organization, Schedule, and plugin sections to control their order. Plugin sections can be hidden here.</p>
                <ol class="lt-library-sortable" data-library-sortable>
                    @foreach ($sections as $section)
                        <li class="lt-library-sortable__item" draggable="true" data-order-id="{{ $section['id'] }}">
                            <input type="hidden" name="sectionOrder[]" value="{{ $section['id'] }}">
                            <span class="lt-library-sortable__handle" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span>
                            @if ($section['icon'] !== '') <i class="{{ $section['icon'] }}" aria-hidden="true"></i> @endif
                            <span class="lt-library-sortable__label">{{ $section['label'] }}</span>
                            @if ($section['builtin'])
                                <span class="lt-library-sortable__status">Leantime section</span>
                            @else
                                <label class="lt-library-sortable__toggle">
                                    <input type="checkbox" name="sectionEnabled[]" value="{{ $section['id'] }}" @if ($section['enabled']) checked @endif>
                                    Show in To-do modal
                                </label>
                            @endif
                            <div class="lt-library-sortable__controls">
                                <button type="button" class="btn btn-default" data-move="up" aria-label="Move {{ $section['label'] }} up">↑</button>
                                <button type="button" class="btn btn-default" data-move="down" aria-label="Move {{ $section['label'] }} down">↓</button>
                            </div>
                        </li>
                    @endforeach
                </ol>

                <button class="btn btn-primary" type="submit">Save To-do layout</button>
            </form>
        </div>
    </div>
@endsection
