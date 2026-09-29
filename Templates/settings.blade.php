@extends($layout)

@section('content')
    <div class="maincontent">
        <div class="maincontentinner">
            <h1>Leantime Library</h1>
            <p>Arrange To-do tabs and plugin-contributed inline sections. Leantime's built-in modal sections stay in their native positions.</p>

            @if (!empty($error))
                <div class="alert alert-danger" role="alert">{{ $error }}</div>
            @endif

            @if (count($tabs) === 0 && count($sections) === 0)
                <div class="alert alert-info" role="status">
                    No enabled plugins have contributed To-do tabs or inline sections yet. Contributions will appear here when plugins register them with the Library.
                </div>
            @else
                <form method="post" action="{{ BASE_URL }}/LeantimeLib/settings">
                    @csrf
                    @if (count($tabs) > 0)
                        <h2>To-do tabs</h2>
                        <p>Drag tabs into the order you want. Use the up and down buttons to reorder with a keyboard.</p>
                        <ol class="lt-library-sortable" data-library-sortable>
                            @foreach ($tabs as $tab)
                                <li class="lt-library-sortable__item" draggable="true" data-order-id="{{ $tab['id'] }}">
                                    <input type="hidden" name="tabOrder[]" value="{{ $tab['id'] }}">
                                    <span class="lt-library-sortable__handle" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span>
                                    @if ($tab['icon'] !== '') <i class="{{ $tab['icon'] }}" aria-hidden="true"></i> @endif
                                    <span class="lt-library-sortable__label">{{ $tab['label'] }}</span>
                                    <div class="lt-library-sortable__controls">
                                        <button type="button" class="btn btn-default" data-move="up" aria-label="Move {{ $tab['label'] }} up">↑</button>
                                        <button type="button" class="btn btn-default" data-move="down" aria-label="Move {{ $tab['label'] }} down">↓</button>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    @if (count($sections) > 0)
                        <h2>Inline sections after Schedule</h2>
                        <p>These plugin-provided sections share Leantime's supported after-Schedule insertion point. Drag to set their order there.</p>
                        <ol class="lt-library-sortable" data-library-sortable>
                            @foreach ($sections as $section)
                                <li class="lt-library-sortable__item" draggable="true" data-order-id="{{ $section['id'] }}">
                                    <input type="hidden" name="sectionOrder[]" value="{{ $section['id'] }}">
                                    <span class="lt-library-sortable__handle" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span>
                                    @if ($section['icon'] !== '') <i class="{{ $section['icon'] }}" aria-hidden="true"></i> @endif
                                    <span class="lt-library-sortable__label">{{ $section['label'] }}</span>
                                    <div class="lt-library-sortable__controls">
                                        <button type="button" class="btn btn-default" data-move="up" aria-label="Move {{ $section['label'] }} up">↑</button>
                                        <button type="button" class="btn btn-default" data-move="down" aria-label="Move {{ $section['label'] }} down">↓</button>
                                    </div>
                                </li>
                            @endforeach
                        </ol>
                    @endif

                    <button class="btn btn-primary" type="submit">Save To-do layout</button>
                </form>
            @endif
        </div>
    </div>
@endsection
