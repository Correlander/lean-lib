@extends($layout)

@section('content')
    <div class="maincontent">
        <div class="maincontentinner">
            <h1>Leantime Library</h1>
            <p>Arrange tabs contributed by enabled plugins in the To-do detail modal.</p>

            @if (!empty($error))
                <div class="alert alert-danger" role="alert">{{ $error }}</div>
            @endif

            @if (count($tabs) === 0)
                <div class="alert alert-info" role="status">
                    No enabled plugins have contributed To-do tabs yet. Tabs will appear here when a plugin registers one with the Library.
                </div>
            @else
                <form method="post" action="{{ BASE_URL }}/LeantimeLib/settings">
                    @csrf
                    <p>Drag tabs into the order you want. Use the up and down buttons to reorder with a keyboard.</p>
                    <ol class="lt-library-tab-order" data-library-sortable>
                        @foreach ($tabs as $tab)
                            <li class="lt-library-tab-order__item" draggable="true" data-tab-id="{{ $tab['id'] }}">
                                <input type="hidden" name="tabOrder[]" value="{{ $tab['id'] }}">
                                <span class="lt-library-tab-order__handle" aria-hidden="true"><i class="fa-solid fa-grip-vertical"></i></span>
                                @if ($tab['icon'] !== '')
                                    <i class="{{ $tab['icon'] }}" aria-hidden="true"></i>
                                @endif
                                <span class="lt-library-tab-order__label">{{ $tab['label'] }}</span>
                                <div class="lt-library-tab-order__controls">
                                    <button type="button" class="btn btn-default" data-move="up" aria-label="Move {{ $tab['label'] }} up">↑</button>
                                    <button type="button" class="btn btn-default" data-move="down" aria-label="Move {{ $tab['label'] }} down">↓</button>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                    <button class="btn btn-primary" type="submit">Save tab order</button>
                </form>
            @endif
        </div>
    </div>
@endsection
