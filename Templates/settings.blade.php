@extends($layout)

@section('content')
    <div class="maincontent">
        <div class="maincontentinner">
            @if (!empty($error))
                <div class="alert alert-danger" role="alert">{{ $error }}</div>
            @endif

            <form method="post" action="{{ BASE_URL }}/LeantimeLib/settings" data-library-settings-form>
                @csrf
                {!! $settingsContent !!}
                <p class="lt-library-autosave-status" data-autosave-status role="status" aria-live="polite">Changes automatically saved.</p>
            </form>
        </div>
    </div>
@endsection
